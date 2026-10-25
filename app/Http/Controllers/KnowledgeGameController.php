<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KnowledgeGameController extends Controller
{
    private const TOPICS = [
        'farming' => 'Farming',
        'poultry' => 'Poultry',
    ];

    public function topics(Request $request): array
    {
        $this->requireParticipant($request);

        $counts = DB::table('knowledge_game_questions')
            ->where('active', true)
            ->select('topic', DB::raw('COUNT(*) as question_count'))
            ->groupBy('topic')
            ->pluck('question_count', 'topic');

        return ['topics' => collect(self::TOPICS)->map(
            fn (string $title, string $id): array => [
                'id' => $id,
                'title' => $title,
                'question_count' => (int) ($counts[$id] ?? 0),
            ],
        )->values()->all()];
    }

    public function start(Request $request)
    {
        $this->requireParticipant($request);
        $data = $request->validate(['topic' => ['required', 'in:farming,poultry']]);
        $questions = DB::table('knowledge_game_questions')
            ->where('topic', $data['topic'])
            ->where('active', true)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        abort_if($questions->isEmpty(), 422, 'There are no questions available for this topic yet.');

        $now = now();
        $questionIds = $questions->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $attemptId = DB::table('knowledge_game_attempts')->insertGetId([
            'user_id' => $request->user()->id,
            'topic' => $data['topic'],
            'question_ids' => json_encode($questionIds, JSON_THROW_ON_ERROR),
            'total_questions' => count($questionIds),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'attempt_id' => $attemptId,
            'topic' => $data['topic'],
            'total_questions' => count($questionIds),
            'questions' => $questions->map(fn ($question): array => [
                'id' => (int) $question->id,
                'question' => $question->question,
                'options' => json_decode($question->options, true, 512, JSON_THROW_ON_ERROR),
            ])->values()->all(),
        ], 201);
    }

    public function submit(Request $request, int $attemptId)
    {
        $this->requireParticipant($request);
        $attempt = DB::table('knowledge_game_attempts')
            ->where('id', $attemptId)
            ->where('user_id', $request->user()->id)
            ->first();
        abort_unless($attempt, 404);
        abort_if($attempt->completed_at, 409, 'This quiz has already been submitted.');

        $questionIds = json_decode($attempt->question_ids, true, 512, JSON_THROW_ON_ERROR);
        $rules = ['answers' => ['required', 'array', 'size:'.count($questionIds)]];
        foreach ($questionIds as $questionId) {
            $rules['answers.'.$questionId] = ['required', 'string', 'in:a,b,c,d'];
        }
        $data = $request->validate($rules);
        $answers = $data['answers'];
        $providedIds = array_map('intval', array_keys($answers));
        $expectedIds = array_map('intval', $questionIds);
        sort($providedIds);
        sort($expectedIds);
        abort_unless($providedIds === $expectedIds, 422, 'Answer every question in this quiz.');

        $questions = DB::table('knowledge_game_questions')
            ->where('topic', $attempt->topic)
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy('id');
        abort_unless($questions->count() === count($questionIds), 409, 'This quiz is no longer available.');

        $score = 0;
        $review = [];
        foreach ($questionIds as $questionId) {
            $question = $questions->get($questionId);
            $options = json_decode($question->options, true, 512, JSON_THROW_ON_ERROR);
            $selected = strtolower($answers[$questionId]);
            $correctOption = strtolower($question->correct_option);
            $correct = $selected === $correctOption;
            if ($correct) {
                $score++;
            }
            $review[] = [
                'id' => (int) $questionId,
                'question' => $question->question,
                'selected_option' => $selected,
                'selected_answer' => $options[$selected] ?? '',
                'correct_option' => $correctOption,
                'correct_answer' => $options[$correctOption] ?? '',
                'correct' => $correct,
                'explanation' => $question->explanation,
            ];
        }

        $completedAt = now();
        $updated = DB::table('knowledge_game_attempts')
            ->where('id', $attemptId)
            ->whereNull('completed_at')
            ->update([
                'answers' => json_encode($answers, JSON_THROW_ON_ERROR),
                'score' => $score,
                'completed_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        abort_unless($updated === 1, 409, 'This quiz has already been submitted.');

        return [
            'attempt_id' => $attemptId,
            'topic' => $attempt->topic,
            'score' => $score,
            'total_questions' => count($questionIds),
            'percentage' => (int) round($score * 100 / count($questionIds)),
            'questions' => $review,
        ];
    }

    public function history(Request $request): array
    {
        $this->requireParticipant($request);
        $attempts = DB::table('knowledge_game_attempts')
            ->where('user_id', $request->user()->id)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit(20)
            ->get(['id', 'topic', 'score', 'total_questions', 'completed_at']);

        return ['attempts' => $attempts->map(fn ($attempt): array => [
            'id' => (int) $attempt->id,
            'topic' => $attempt->topic,
            'topic_title' => self::TOPICS[$attempt->topic] ?? $attempt->topic,
            'score' => (int) $attempt->score,
            'total_questions' => (int) $attempt->total_questions,
            'completed_at' => $attempt->completed_at,
        ])->all()];
    }

    private function requireParticipant(Request $request): void
    {
        abort_unless($request->user()?->role === 'participant', 403);
    }
}
