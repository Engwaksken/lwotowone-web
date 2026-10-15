<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Terms of use and privacy notice shown at signup, from published website pages with built-in fallbacks. */
class Policies
{
    private const DEFAULTS = [
        'terms' => ['title' => 'Terms of use', 'body' => "By creating an account you agree to use Lwotowone services lawfully and respectfully, to give accurate information about yourself, and to keep your password private.\n\nCourse materials are provided for your personal learning. Do not copy, resell or share them without permission.\n\nWe may suspend accounts that misuse the platform, harass other people or try to access information that is not theirs.\n\nContact us if you have questions about these terms."],
        'privacy' => ['title' => 'Privacy notice', 'body' => "We collect the details you give us, such as your name, email, phone number and learner profile, to run your training, track progress and connect you with mentors and opportunities.\n\nYour information is shared only with Lwotowone staff, instructors, mentors and partners who support your learning, or when the law requires it.\n\nWe keep your data secure and only for as long as needed for these purposes.\n\nYou can ask to see, correct or delete your information by contacting us."],
    ];

    /** @return array<string, array{title: string, body: string}> */
    public static function all(): array
    {
        $pages = DB::table('pages')->whereIn('slug', array_keys(self::DEFAULTS))->where('status', 'published')->get()->keyBy('slug');
        $out = [];
        foreach (self::DEFAULTS as $slug => $default) {
            $page = $pages->get($slug);
            $out[$slug] = ['title' => $page->title ?? $default['title'], 'body' => $page->body ?? $default['body']];
        }
        return $out;
    }
}
