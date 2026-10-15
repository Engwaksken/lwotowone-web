<?php
namespace App\Services;

use App\Models\User;

/** How much of the learner profile is filled in; learning stays locked until it is complete. */
class ProfileProgress
{
    public static function steps(User $user): array
    {
        $answered = (bool)$user->profile_complete;
        return [
            'Full name' => filled($user->name),
            'Phone number' => filled($user->phone),
            'Course choice' => filled($user->selected_course_id),
            'Gender' => filled($user->gender),
            'Age' => filled($user->learner_age),
            'Location' => filled($user->location),
            'Urban or rural' => filled($user->urban_rural),
            'Refugee status' => $answered && (!$user->refugee || filled($user->settlement)),
            'Disability status' => $answered && (!$user->pwd || filled($user->impairment)),
            'Education level' => filled($user->education_level),
            'Employment status' => $user->employed !== null && (!$user->employed || filled($user->employer_name)),
        ];
    }

    public static function percent(User $user): int
    {
        if ($user->profile_complete) return 100;
        $steps = self::steps($user);
        return min(99, (int)floor(100 * count(array_filter($steps)) / count($steps)));
    }

    public static function missing(User $user): array
    {
        return $user->profile_complete ? [] : array_keys(array_filter(self::steps($user), fn ($done) => !$done));
    }
}
