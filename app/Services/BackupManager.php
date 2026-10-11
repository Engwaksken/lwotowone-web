<?php

namespace App\Services;

use Illuminate\Support\Facades\{DB,Schema,Storage};
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class BackupManager
{
    public function create(string $destination): array
    {
        if (!extension_loaded('zip')) {
            throw new RuntimeException('The PHP zip extension is required to create backups.');
        }

        $name = 'backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.zip';
        $relativePath = 'backups/'.$name;
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $absolutePath = $disk->path($relativePath);
        $zip = new ZipArchive();

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        try {
            $database = [];
            foreach (Schema::getTableListing() as $table) {
                $database[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            }
            $zip->addFromString('database.json', json_encode($database, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            $zip->addFromString('manifest.json', json_encode([
                'created_at' => now()->toIso8601String(),
                'application' => config('app.name'),
                'database_connection' => config('database.default'),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            $this->addDirectory($zip, storage_path('app/private'), 'storage/private', ['backups']);
            $this->addDirectory($zip, storage_path('app/public'), 'storage/public');
            $this->addDirectory($zip, public_path('site-branding'), 'site-branding');
        } catch (\Throwable $exception) {
            $zip->close();
            $disk->delete($relativePath);
            throw $exception;
        }

        $zip->close();

        $result = ['name' => $name, 'size' => $disk->size($relativePath), 'local_path' => $relativePath];
        if (in_array($destination, ['remote', 'both'], true)) {
            try {
                $this->uploadToS3($absolutePath, $name);
            } catch (\Throwable $exception) {
                if ($destination === 'remote') {
                    $disk->delete($relativePath);
                }
                throw $exception;
            }
            $result['remote_key'] = $this->remoteKey($name);
        }

        if ($destination === 'remote') {
            $disk->delete($relativePath);
            $result['local_path'] = null;
        }

        return $result;
    }

    private function addDirectory(ZipArchive $zip, string $root, string $archivePrefix, array $exclude = []): void
    {
        if (!is_dir($root)) {
            return;
        }

        $base = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($base)));
            if (in_array(explode('/', $relative)[0], $exclude, true)) {
                continue;
            }
            $zip->addFile($file->getPathname(), $archivePrefix.'/'.$relative);
        }
    }

    private function uploadToS3(string $filePath, string $name): void
    {
        $settings = DB::table('settings')->pluck('value', 'key');
        $endpoint = rtrim((string) $settings->get('backup_s3_endpoint'), '/');
        $bucket = (string) $settings->get('backup_s3_bucket');
        $region = (string) $settings->get('backup_s3_region', 'auto');
        $accessKey = (string) $settings->get('backup_s3_access_key');
        $secret = $this->decrypt((string) $settings->get('backup_s3_secret'));
        $prefix = trim((string) $settings->get('backup_s3_prefix', 'backups'), '/');

        if (! $endpoint || ! $bucket || ! $region || ! $accessKey || ! $secret) {
            throw new RuntimeException('Configure the S3-compatible endpoint, bucket, region, access key and secret first.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for remote backups.');
        }

        $key = ($prefix ? $prefix.'/' : '').$name;
        $parts = parse_url($endpoint);
        if (!isset($parts['host']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('The S3 endpoint must be an HTTPS URL without credentials, query parameters or fragments.');
        }
        $host = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $pathSegments = array_filter(explode('/', trim(($parts['path'] ?? '').'/'.$bucket.'/'.$key, '/')), 'strlen');
        $uri = '/'.implode('/', array_map('rawurlencode', $pathSegments));
        $url = $endpoint.'/'.$bucket.'/'.($prefix ? $prefix.'/' : '').rawurlencode($name);
        $payloadHash = hash_file('sha256', $filePath);
        $amzDate = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $canonicalHeaders = "host:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "PUT\n$uri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        $scope = "$date/$region/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n".hash('sha256', $canonicalRequest);
        $dateKey = hash_hmac('sha256', $date, 'AWS4'.$secret, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = "AWS4-HMAC-SHA256 Credential=$accessKey/$scope, SignedHeaders=$signedHeaders, Signature=$signature";

        $stream = fopen($filePath, 'rb');
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_PUT => true,
            CURLOPT_INFILE => $stream,
            CURLOPT_INFILESIZE => filesize($filePath),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_HTTPHEADER => [
                'Authorization: '.$authorization,
                'Host: '.$host,
                'x-amz-content-sha256: '.$payloadHash,
                'x-amz-date: '.$amzDate,
                'Content-Type: application/zip',
            ],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($stream);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Remote backup upload failed'.($error ? ': '.$error : ' (HTTP '.$status.').'));
        }
    }

    private function remoteKey(string $name): string
    {
        $settings = DB::table('settings')->pluck('value', 'key');
        $prefix = trim((string) $settings->get('backup_s3_prefix', 'backups'), '/');
        return ($prefix ? $prefix.'/' : '').$name;
    }

    private function decrypt(string $value): string
    {
        if (str_starts_with($value, 'enc:')) {
            return \Illuminate\Support\Facades\Crypt::decryptString(substr($value, 4));
        }
        return $value;
    }
}
