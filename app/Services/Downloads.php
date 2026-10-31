<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Learners watch and read content inside the platform; saving a file needs an approved request.
 * An approval allows MAX_DOWNLOADS downloads within VALID_DAYS days.
 */
class Downloads
{
    public const VALID_DAYS = 7;
    public const MAX_DOWNLOADS = 3;
    public const TYPES = ['lessons', 'resources'];

    /** Tools that save files rather than play them in a page. Easy to disguise, so this is only one layer. */
    private const DOWNLOADER_AGENTS = '/internet download manager|\bidm\b|idman|wget|curl\/|aria2|axel|jdownloader|free download manager|\bfdm\b|download master|downloadstudio|flashget|getright|orbit|eagleget|motrix|xdman|httrack|python-requests|python-urllib|go-http-client|libwww|java\//i';

    public static function item(string $type, int $id): ?object
    {
        return in_array($type, self::TYPES, true) ? DB::table($type)->find($id) : null;
    }

    public static function attachments(string $type, int $id)
    {
        return $type === 'lessons'
            ? DB::table('learning_attachments')->where('entity_type', 'lesson')->where('entity_id', $id)->orderBy('id')->get()
            : collect();
    }

    /** Only uploaded files (and a lesson's attached files) can be downloaded; written text and links cannot. */
    public static function downloadable(string $type, object $item): bool
    {
        $file = $item->file_path && LearningContent::source($item, $type) === 'upload';
        return $file || self::attachments($type, (int) $item->id)->isNotEmpty();
    }

    public static function title(object $item): string
    {
        return ($item->title ?: $item->title_lg) ?: 'Learning content';
    }

    /** The learner may open this item in the platform right now. */
    public static function learnerCanOpen(User $user, string $type, object $item): bool
    {
        if (($item->status ?? null) !== 'published') return false;
        if (!LearningAccess::enrollment($user, (int) $item->course_id)) return false;
        return $type === 'lessons'
            ? LearningAccess::inLanguage($user, $item) && LearningAccess::lessonAllowed($user, $item)
            : LearningAccess::resourceAllowed($user, $item);
    }

    public static function isActive(?object $request): bool
    {
        return $request
            && $request->status === 'approved'
            && $request->expires_at && now()->lt($request->expires_at)
            && (int) $request->downloads_used < self::MAX_DOWNLOADS;
    }

    /** What the learner sees: pending, approved (active), used_up, expired, rejected, revoked, or null when never asked. */
    public static function state(?object $request): ?string
    {
        if (!$request) return null;
        if ($request->status !== 'approved') return $request->status;
        if ((int) $request->downloads_used >= self::MAX_DOWNLOADS) return 'used_up';
        return $request->expires_at && now()->lt($request->expires_at) ? 'approved' : 'expired';
    }

    public static function forUser(User $user)
    {
        return DB::table('download_requests')->where('user_id', $user->id)->get()->keyBy(fn ($row) => $row->item_type.'-'.$row->item_id);
    }

    public function request(User $user, string $type, int $id, ?string $message = null): void
    {
        $item = self::item($type, $id);
        if (!$item || !self::learnerCanOpen($user, $type, $item)) {
            throw ValidationException::withMessages(['download'=>'Open this content in your course before requesting a download.']);
        }
        if (!self::downloadable($type, $item)) {
            throw ValidationException::withMessages(['download'=>'This content has no file to download.']);
        }
        DB::transaction(function () use ($user, $type, $id, $message) {
            $existing = DB::table('download_requests')->where('user_id', $user->id)->where('item_type', $type)->where('item_id', $id)->lockForUpdate()->first();
            $state = self::state($existing);
            if ($state === 'pending') throw ValidationException::withMessages(['download'=>'Your download request is already waiting for review.']);
            if ($state === 'approved') throw ValidationException::withMessages(['download'=>'Your download is already approved.']);
            DB::table('download_requests')->updateOrInsert(['user_id'=>$user->id, 'item_type'=>$type, 'item_id'=>$id], ['status'=>'pending', 'message'=>$message, 'decision_note'=>null, 'decided_by'=>null, 'decided_at'=>null, 'expires_at'=>null, 'downloads_used'=>0, 'last_downloaded_at'=>null, 'created_at'=>$existing?->created_at ?? now(), 'updated_at'=>now()]);
        });
    }

    public function decide(int $requestId, string $decision, int $actorId, ?string $note = null): object
    {
        return DB::transaction(function () use ($requestId, $decision, $actorId, $note) {
            $request = DB::table('download_requests')->where('id', $requestId)->lockForUpdate()->first();
            abort_unless($request, 404);
            $allowed = ['approve'=>'pending', 'reject'=>'pending', 'revoke'=>'approved'][$decision];
            if ($request->status !== $allowed) {
                throw ValidationException::withMessages(['download'=>'This request has already been '.$request->status.'.']);
            }
            $status = ['approve'=>'approved', 'reject'=>'rejected', 'revoke'=>'revoked'][$decision];
            DB::table('download_requests')->where('id', $requestId)->update(['status'=>$status, 'decision_note'=>$note, 'decided_by'=>$actorId, 'decided_at'=>now(), 'expires_at'=>$decision === 'approve' ? now()->addDays(self::VALID_DAYS) : $request->expires_at, 'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['user_id'=>$actorId, 'action'=>$decision.'-download', 'module'=>'downloads', 'record_id'=>$requestId, 'created_at'=>now(), 'updated_at'=>now()]);
            return DB::table('download_requests')->find($requestId);
        });
    }

    /**
     * Counts a download. Download managers fetch one file in several byte ranges and resume interrupted
     * downloads, so only a request that starts at the first byte counts.
     */
    public function record(object $request, Request $http): void
    {
        $range = (string) $http->header('Range', '');
        if ($range !== '' && !preg_match('/^bytes=0-/', $range)) return;
        $updated = DB::table('download_requests')->where('id', $request->id)->where('status', 'approved')->where('downloads_used', '<', self::MAX_DOWNLOADS)
            ->update(['downloads_used'=>DB::raw('downloads_used + 1'), 'last_downloaded_at'=>now(), 'updated_at'=>now()]);
        abort_unless($updated === 1, 403, 'This download is no longer available. Send a new request.');
    }

    /**
     * Streaming routes only answer the page's own player. Opening the file link directly, "Save link as"
     * and download-manager tools are refused. Browsers send Sec-Fetch-* headers; media stacks such as
     * iOS AppleCoreMedia do not, so a missing header is allowed unless the client names itself as a downloader.
     */
    public static function streamingAllowed(Request $request): bool
    {
        if (preg_match(self::DOWNLOADER_AGENTS, (string) $request->userAgent())) return false;
        $dest = $request->header('Sec-Fetch-Dest');
        $site = $request->header('Sec-Fetch-Site');
        if ($site !== null && !in_array($site, ['same-origin', 'none'], true)) return false;
        if ($dest === null) return true;
        if ($request->header('Sec-Fetch-User') === '?1') return false;
        return in_array($dest, ['video', 'audio', 'image', 'iframe', 'embed', 'object', 'empty', 'track'], true);
    }
}
