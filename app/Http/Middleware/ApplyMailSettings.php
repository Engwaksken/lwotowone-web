<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Config,Crypt,DB,Schema};

class ApplyMailSettings
{
    public function handle(Request $request, Closure $next)
    {
        self::apply();
        return $next($request);
    }

    public static function apply(): void
    {
        if (Schema::hasTable('settings')) {
            $settings = DB::table('settings')->whereIn('key', [
                'mail_driver', 'mail_host', 'mail_port', 'mail_scheme', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name',
            ])->pluck('value', 'key');

            if ($settings->has('mail_driver')) {
                Config::set('mail.default', $settings->get('mail_driver'));
                Config::set('mail.from.address', $settings->get('mail_from_address', config('mail.from.address')));
                Config::set('mail.from.name', $settings->get('mail_from_name', config('mail.from.name')));

                if ($settings->get('mail_driver') === 'smtp') {
                    $password = (string) $settings->get('mail_password', '');
                    try {
                        if (str_starts_with($password, 'enc:')) {
                            $password = Crypt::decryptString(substr($password, 4));
                        }
                    } catch (\Throwable) {
                        $password = '';
                    }
                    Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
                        'transport' => 'smtp',
                        'host' => $settings->get('mail_host'),
                        'port' => (int) $settings->get('mail_port', 587),
                        'scheme' => $settings->get('mail_scheme') ?: null,
                        'username' => $settings->get('mail_username') ?: null,
                        'password' => $password ?: null,
                    ]));
                }
                app('mail.manager')->purge();
            }
        }
    }
}
