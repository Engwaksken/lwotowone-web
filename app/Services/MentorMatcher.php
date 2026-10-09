<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MentorMatcher
{
    public function rank(User $learner, Collection $mentors): Collection
    {
        if ($mentors->isEmpty()) {
            return $mentors;
        }

        $course = $learner->selected_course_id
            ? \Illuminate\Support\Facades\DB::table('courses')->where('id', $learner->selected_course_id)->value('title')
            : null;
        $interests = trim(implode(' ', array_filter([
            $learner->transformation_objective,
            $learner->expertise,
            $course,
        ])));
        $ranked = $mentors->map(function ($mentor) use ($interests) {
            $text = strtolower(trim(($mentor->expertise ?? '').' '.($mentor->bio ?? '')));
            $terms = array_unique(array_filter(preg_split('/[^a-z0-9]+/i', strtolower($interests)) ?: [], fn ($term) => strlen($term) > 3));
            $score = count(array_filter($terms, fn ($term) => str_contains($text, $term)));
            $mentor->match_score = $score;
            $mentor->match_reason = $score > 0
                ? 'Their experience connects with your learning interests.'
                : 'Explore their experience and see whether it fits your goals.';
            return $mentor;
        })->sortByDesc('match_score')->values();

        if ($interests === '') {
            return $ranked;
        }

        $candidates = $mentors->map(fn ($mentor) => [
            'id' => (int) $mentor->id,
            'expertise' => (string) ($mentor->expertise ?? ''),
            'bio' => mb_substr((string) ($mentor->bio ?? ''), 0, 500),
        ])->values();
        $answer = app(AiAssistant::class)->complete(
            'You recommend suitable human mentors to learners. Use only the supplied candidates. Return valid JSON only with a "matches" array containing up to 3 objects: {"id": integer, "reason": short string}. Never invent candidate IDs.',
            json_encode(['learner_interests' => mb_substr($interests, 0, 1200), 'mentors' => $candidates], JSON_THROW_ON_ERROR),
            350,
        );
        if (!$answer) {
            return $ranked;
        }

        $json = json_decode($answer, true);
        if (!is_array($json)) {
            preg_match('/\{.*\}/s', $answer, $match);
            $json = isset($match[0]) ? json_decode($match[0], true) : null;
        }
        $matches = collect(is_array($json) ? ($json['matches'] ?? []) : [])
            ->filter(fn ($item) => is_array($item) && isset($item['id']))
            ->unique(fn ($item) => (int) $item['id'])
            ->take(3);
        if ($matches->isEmpty()) {
            return $ranked;
        }

        $ordered = $matches->map(function ($match) use ($mentors) {
            $mentor = $mentors->firstWhere('id', (int) $match['id']);
            if (!$mentor) {
                return null;
            }
            $mentor->match_reason = mb_substr(strip_tags((string) ($match['reason'] ?? 'A potential fit for your learning goals.')), 0, 220);
            $mentor->ai_recommended = true;
            return $mentor;
        })->filter();

        return $ordered->concat($ranked->reject(fn ($mentor) => $ordered->contains('id', $mentor->id)))->values();
    }
}
