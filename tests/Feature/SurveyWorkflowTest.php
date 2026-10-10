<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\{Sharing, SurveyForms, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SurveyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'manager'): User
    {
        return User::create(['name' => 'Test '.$role, 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active']);
    }

    private function questions(): array
    {
        return [
            ['id' => (string)Str::uuid(), 'label' => 'Rate the workshop', 'type' => 'rating', 'required' => true, 'help' => '', 'options' => []],
            ['id' => (string)Str::uuid(), 'label' => 'Topics covered', 'type' => 'multiple_choice', 'required' => true, 'help' => '', 'options' => ['Farming', 'Business']],
            ['id' => (string)Str::uuid(), 'label' => 'Your feedback', 'type' => 'long_text', 'required' => false, 'help' => '', 'options' => []],
        ];
    }

    private function survey(bool $anonymous = true): object
    {
        $id = Workflow::insert('surveys', ['title' => 'Participant feedback', 'description' => 'Help us improve', 'token' => (string)Str::uuid(), 'status' => 'published', 'anonymous' => $anonymous, 'questions' => json_encode($this->questions()), 'created_by' => $this->user()->id]);
        return DB::table('surveys')->find($id);
    }

    private function response(object $survey): array
    {
        $questions = json_decode($survey->questions, true);
        return ['submission_token' => (string)Str::uuid(), 'answers' => [$questions[0]['id'] => 4, $questions[1]['id'] => ['Farming', 'Business'], $questions[2]['id'] => 'Useful workshop']];
    }

    public function test_manager_creates_builds_publishes_and_shares_survey_from_mel(): void
    {
        $this->actingAs($this->user())->get('/admin/mel')->assertOk()->assertSee('MEL surveys')->assertSee('Create survey');
        $this->post('/admin/mel/surveys', ['title' => 'Field feedback', 'description' => 'Tell us more', 'anonymous' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $survey = DB::table('surveys')->first(); $this->assertSame('draft', $survey->status);
        $this->get('/admin/mel/surveys/'.$survey->id.'/edit')->assertOk()->assertSee('Add question')->assertSee('Multiple choice');
        $this->put('/admin/mel/surveys/'.$survey->id, ['title' => $survey->title, 'description' => $survey->description, 'anonymous' => 1, 'status' => 'published', 'questions' => $this->questions()])->assertSessionHasNoErrors();
        $this->assertSame($survey->token, DB::table('surveys')->value('token'));
        $this->get('/admin/mel/surveys')->assertOk()->assertSee($survey->title)->assertSee(url('/surveys/'.$survey->token));
        $svg = $this->get('/admin/mel/surveys/'.$survey->id.'/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->getContent();
        $this->assertSame(Sharing::qr(url('/surveys/'.$survey->token), 'unused')->getContent(), $svg);
    }

    public function test_anonymous_submission_discards_identity_even_for_signed_in_users_and_replay_is_idempotent(): void
    {
        $survey = $this->survey(); $participant = $this->user('participant'); $data = $this->response($survey) + ['respondent_name' => 'Identity must not be stored', 'respondent_email' => 'secret@example.test', 'user_id' => $participant->id, 'anonymous' => 0];
        $this->actingAs($participant)->get('/surveys/'.$survey->token)->assertOk()->assertDontSee('name="respondent_email"', false);
        $this->post('/surveys/'.$survey->token, $data)->assertRedirect()->assertSessionHasNoErrors(); $this->post('/surveys/'.$survey->token, $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('survey_responses', 1); $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseHas('survey_responses', ['survey_id' => $survey->id, 'anonymous' => true, 'user_id' => null, 'respondent_name' => null, 'respondent_email' => null]);
        $row = DB::table('survey_responses')->first(); $this->assertStringNotContainsString('secret@example.test', json_encode($row));
        $this->actingAs($this->user())->get('/admin/mel/surveys/'.$survey->id.'/responses')->assertOk()->assertSee('Anonymous')->assertSee('Average:')->assertSee('Useful workshop')->assertDontSee('secret@example.test');
        $csv = $this->get('/admin/mel/surveys/'.$survey->id.'/responses.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Anonymous', $csv); $this->assertStringNotContainsString($participant->email, $csv);
    }

    public function test_guest_named_surveys_require_identity_and_track_responses_and_choice_totals(): void
    {
        $survey = $this->survey(false); $data = $this->response($survey);
        $this->post('/surveys/'.$survey->token, $data)->assertSessionHasErrors(['respondent_name', 'respondent_email']);
        $this->post('/surveys/'.$survey->token, $data + ['respondent_name' => 'Guest respondent', 'respondent_email' => 'guest@example.test'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('survey_responses', ['respondent_name' => 'Guest respondent', 'respondent_email' => 'guest@example.test', 'user_id' => null, 'anonymous' => false]);
        $summary = SurveyForms::summary(json_decode($survey->questions, true), DB::table('survey_responses')->get());
        $this->assertSame(4.0, $summary[0]['average']); $this->assertSame(['Farming' => 1, 'Business' => 1], $summary[1]['choices']);
    }

    public function test_unknown_missing_out_of_range_and_invalid_choice_answers_are_rejected(): void
    {
        $survey = $this->survey(); $questions = json_decode($survey->questions, true);
        foreach ([[], [$questions[0]['id'] => 6, $questions[1]['id'] => ['Farming']], [$questions[0]['id'] => 3, $questions[1]['id'] => ['Unknown']], [$questions[0]['id'] => 3, $questions[1]['id'] => ['Farming', 'Farming']], ['invented' => 'Unexpected']] as $answers) {
            $this->post('/surveys/'.$survey->token, ['submission_token' => (string)Str::uuid(), 'answers' => $answers])->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('survey_responses', 0);
    }

    public function test_definition_and_anonymity_are_locked_after_responses_but_duplication_allows_revision(): void
    {
        $survey = $this->survey(); $this->post('/surveys/'.$survey->token, $this->response($survey))->assertSessionHasNoErrors();
        $this->actingAs($this->user()); $questions = json_decode($survey->questions, true); $questions[0]['label'] = 'Changed question';
        $base = ['title' => $survey->title, 'description' => $survey->description, 'status' => 'published'];
        $this->put('/admin/mel/surveys/'.$survey->id, $base + ['questions' => $questions])->assertSessionHasErrors('questions');
        $this->put('/admin/mel/surveys/'.$survey->id, $base + ['anonymous' => 0])->assertSessionHasErrors('questions');
        $this->put('/admin/mel/surveys/'.$survey->id, array_replace($base, ['status' => 'closed']))->assertSessionHasNoErrors();
        $this->post('/admin/mel/surveys/'.$survey->id.'/duplicate')->assertRedirect(); $copy = DB::table('surveys')->latest('id')->first();
        $this->assertNotSame($survey->token, $copy->token); $this->assertSame('draft', $copy->status); $this->assertSame($survey->questions, $copy->questions);
        $this->assertDatabaseCount('survey_responses', 1);
    }

    public function test_opening_and_closing_windows_and_drafts_are_enforced_at_submission(): void
    {
        $survey = $this->survey(); $url = '/surveys/'.$survey->token; $data = $this->response($survey);
        DB::table('surveys')->where('id', $survey->id)->update(['status' => 'draft']); $this->get($url)->assertNotFound(); $this->post($url, $data)->assertNotFound();
        DB::table('surveys')->where('id', $survey->id)->update(['status' => 'published', 'opens_at' => now()->addHour(), 'closes_at' => now()->addHours(2)]);
        $this->get($url)->assertOk()->assertSee('not currently accepting'); $this->post($url, $data)->assertSessionHasErrors('survey');
        $this->travel(1)->hours(); $this->post($url, $data)->assertSessionHasNoErrors();
        $this->travel(1)->hours(); $this->post($url, $this->response($survey))->assertSessionHasErrors('survey'); $this->assertDatabaseCount('survey_responses', 1);
    }

    public function test_staff_permissions_and_csv_formula_escaping(): void
    {
        $survey = $this->survey(); $data = $this->response($survey); $questions = json_decode($survey->questions, true); $data['answers'][$questions[2]['id']] = '=HYPERLINK("https://example.test")';
        $this->post('/surveys/'.$survey->token, $data)->assertSessionHasNoErrors();
        foreach (['participant', 'instructor', 'mentor'] as $role) {
            $this->actingAs($this->user($role));
            foreach (['edit', 'responses', 'responses.csv', 'qr.svg'] as $suffix) $this->get('/admin/mel/surveys/'.$survey->id.'/'.$suffix)->assertForbidden();
            $this->post('/admin/mel/surveys', ['title' => 'Blocked', 'anonymous' => 1])->assertForbidden();
            $this->put('/admin/mel/surveys/'.$survey->id, [])->assertForbidden();
        }
        $this->actingAs($this->user())->get('/admin/mel/surveys/'.$survey->id.'/responses.csv')->assertOk();
        $csv = $this->get('/admin/mel/surveys/'.$survey->id.'/responses.csv')->streamedContent(); $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_question_builder_normalizes_choices_and_validates_number_date_and_single_choice_answers(): void
    {
        $survey = $this->survey(); $this->actingAs($this->user());
        $questions = [];
        foreach (['short_text', 'number', 'date', 'single_choice'] as $type) $questions[] = ['id' => (string)Str::uuid(), 'label' => 'Question '.$type, 'type' => $type, 'required' => true, 'help' => '', 'options_text' => $type === 'single_choice' ? "First\r\nSecond" : ''];
        $this->put('/admin/mel/surveys/'.$survey->id, ['title' => $survey->title, 'status' => 'published', 'anonymous' => 1, 'questions' => $questions])->assertSessionHasNoErrors();
        $survey = DB::table('surveys')->find($survey->id); $saved = json_decode($survey->questions, true); $this->assertSame(['First', 'Second'], $saved[3]['options']);
        $answers = [$saved[0]['id'] => 'Feedback', $saved[1]['id'] => '12.5', $saved[2]['id'] => '2026-11-01', $saved[3]['id'] => 'First'];
        $this->post('/surveys/'.$survey->token, ['submission_token' => (string)Str::uuid(), 'answers' => $answers])->assertSessionHasNoErrors();
        foreach ([1 => 'not a number', 2 => '2026-02-31', 3 => 'Unknown'] as $index => $value) {
            $invalid = $answers; $invalid[$saved[$index]['id']] = $value;
            $this->post('/surveys/'.$survey->token, ['submission_token' => (string)Str::uuid(), 'answers' => $invalid])->assertSessionHasErrors('answers.'.$saved[$index]['id']);
        }
        $this->assertDatabaseCount('survey_responses', 1);
    }

    public function test_duplicate_or_missing_question_choices_are_rejected_and_response_labels_remain_literal(): void
    {
        $survey = $this->survey(); $this->actingAs($this->user()); $questions = json_decode($survey->questions, true);
        $base = ['title' => $survey->title, 'status' => 'published', 'anonymous' => 1];
        $questions[1]['options'] = ['Same', 'Same']; $this->put('/admin/mel/surveys/'.$survey->id, $base + ['questions' => $questions])->assertSessionHasErrors();
        $questions = json_decode($survey->questions, true); $questions[2]['label'] = 'Course Id';
        $this->put('/admin/mel/surveys/'.$survey->id, $base + ['questions' => $questions])->assertSessionHasNoErrors();
        $survey = DB::table('surveys')->find($survey->id); $data = $this->response($survey); $data['answers'][$questions[2]['id']] = 'Arbitrary feedback, not a database reference';
        $this->post('/surveys/'.$survey->token, $data)->assertSessionHasNoErrors();
        $this->get('/admin/mel/surveys/'.$survey->id.'/responses')->assertOk()->assertSee('Arbitrary feedback, not a database reference');
    }
}
