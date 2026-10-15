<?php namespace Tests; abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase {
    /** Close the site-wide free access period so payment rules apply. */
    protected function endFreeAccess(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(['key' => \App\Services\FreeAccess::SETTING], ['value' => now()->subDay()->toDateTimeString(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
