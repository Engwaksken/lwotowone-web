<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\KnowledgeGameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgeGameTest extends TestCase
{
    use RefreshDatabase;

    private function participant(string $email): User
    {
        return User::create([
            'name' => 'Learner',
            'email' => $email,
            'password' => 'A-long-test-password',
            'role' => 'participant',
            'status' => 'active',
        ]);
    }

    public function test_learner_can_play_server_scored_topic_quizzes_and_review_history(): void
    {
        (new KnowledgeGameSeeder())->run();
        $learner = $this->participant('quiz-learner@example.test');
        $token = $learner->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/knowledge-game/topics')
            ->assertOk()
            ->assertJsonPath('topics.0.id', 'farming')
            ->assertJsonPath('topics.0.question_count', 10)
            ->assertJsonPath('topics.1.id', 'poultry');

        $started = $this->withToken($token)->postJson('/api/knowledge-game/attempts', [
            'topic' => 'poultry',
        ])->assertCreated()
            ->assertJsonPath('topic', 'poultry')
            ->assertJsonPath('total_questions', 5)
            ->assertJsonMissingPath('questions.0.correct_option')
            ->assertJsonMissingPath('questions.0.explanation');

        $answers = [];
        foreach ($started->json('questions') as $question) {
            $answers[(string) $question['id']] = DB::table('knowledge_game_questions')
                ->where('id', $question['id'])
                ->value('correct_option');
        }
        $attemptId = $started->json('attempt_id');
        $this->withToken($token)->postJson("/api/knowledge-game/attempts/$attemptId/submit", [
            'answers' => $answers,
        ])->assertOk()
            ->assertJsonPath('score', 5)
            ->assertJsonPath('percentage', 100)
            ->assertJsonPath('questions.0.correct', true)
            ->assertJsonStructure(['questions' => [['explanation', 'correct_answer']]]);

        $this->withToken($token)->postJson("/api/knowledge-game/attempts/$attemptId/submit", [
            'answers' => $answers,
        ])->assertStatus(409);
        $this->withToken($token)->getJson('/api/knowledge-game/history')
            ->assertOk()
            ->assertJsonPath('attempts.0.topic_title', 'Poultry')
            ->assertJsonPath('attempts.0.score', 5);
    }

    public function test_quiz_attempts_are_owned_by_participants_and_answers_must_be_complete(): void
    {
        (new KnowledgeGameSeeder())->run();
        $learner = $this->participant('quiz-owner@example.test');
        $otherLearner = $this->participant('quiz-other@example.test');
        $token = $learner->createToken('mobile')->plainTextToken;
        $attempt = $this->withToken($token)->postJson('/api/knowledge-game/attempts', [
            'topic' => 'farming',
        ])->assertCreated()->json('attempt_id');

        $this->withToken($token)
            ->postJson("/api/knowledge-game/attempts/$attempt/submit", ['answers' => []])
            ->assertUnprocessable();
        $this->withToken($token)->postJson('/api/knowledge-game/attempts', [
            'topic' => 'gardening',
        ])->assertUnprocessable();
        $this->actingAs($otherLearner, 'sanctum')
            ->postJson("/api/knowledge-game/attempts/$attempt/submit", ['answers' => []])
            ->assertNotFound();

        $manager = User::create([
            'name' => 'Manager',
            'email' => 'quiz-manager@example.test',
            'password' => 'A-long-test-password',
            'role' => 'manager',
            'status' => 'active',
        ]);
        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/knowledge-game/topics')->assertForbidden();
    }
}
