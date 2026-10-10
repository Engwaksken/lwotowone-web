<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class SurveyStatistics
{
    public static function overview(): array
    {
        return [
            'total' => DB::table('surveys')->count(),
            'open' => DB::table('surveys')->where('status', 'published')->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()))->count(),
            'drafts' => DB::table('surveys')->where('status', 'draft')->count(),
            'responses' => DB::table('survey_responses')->count(),
            'anonymous_responses' => DB::table('survey_responses')->where('anonymous', true)->count(),
        ];
    }

    public static function responses(int $surveyId): array
    {
        $responses = DB::table('survey_responses')->where('survey_id', $surveyId);
        return [
            'total' => (clone $responses)->count(),
            'today' => (clone $responses)->where('submitted_at', '>=', now()->startOfDay())->where('submitted_at', '<', now()->startOfDay()->addDay())->count(),
            'anonymous' => (clone $responses)->where('anonymous', true)->count(),
            'named' => (clone $responses)->where('anonymous', false)->count(),
        ];
    }
}
