<?php
namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Site-wide free access: learners use all materials and events free until an admin-set expiry passes. */
class FreeAccess
{
    public const SETTING = 'free_access_ends_at';

    public static function endsAt(): ?Carbon
    {
        $value = DB::table('settings')->where('key', self::SETTING)->value('value');
        return $value ? Carbon::parse($value) : null;
    }

    public static function active(): bool
    {
        $ends = self::endsAt();
        return $ends === null || now()->lt($ends);
    }
}
