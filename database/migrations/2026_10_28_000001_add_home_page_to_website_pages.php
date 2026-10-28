<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds the home page to Website pages, carrying over the headline and intro that were only stored in settings. */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pages') || DB::table('pages')->where('slug', 'home')->exists()) return;
        $settings = Schema::hasTable('settings') ? DB::table('settings')->whereIn('key', ['hero_title', 'hero_summary'])->pluck('value', 'key') : collect();
        $title = trim(preg_replace('/\s+/', ' ', (string) ($settings['hero_title'] ?? ''))) ?: 'Skills you can use on Monday morning.';
        $lead = trim((string) ($settings['hero_summary'] ?? '')) ?: 'We run hands-on courses in farming, trades and small business for young people in Uganda — and we stay with you after the course, with mentors and leads on work.';
        DB::table('pages')->insert([
            'title' => mb_substr($title, 0, 255),
            'slug' => 'home',
            'body' => $lead."\n\n"
                ."Most of our learners already have an idea — a poultry unit, a tailoring stall, a phone repair bench. What’s missing is the know-how and someone to ask when things go wrong. So our courses are short and practical, built around the work itself.\n\n"
                ."When a course ends you can still book time with a mentor. We post job and enterprise opportunities as they come in, and you can come back for the next course when you’re ready.",
            'status' => 'published',
            'meta_description' => 'Explore practical learning, mentorship and enterprise support with Lwotowone Enterprises Ltd in Uganda.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) DB::table('pages')->where('slug', 'home')->delete();
    }
};
