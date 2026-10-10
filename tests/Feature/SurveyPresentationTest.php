<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SurveyPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::create(['name' => 'Survey manager', 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => 'manager', 'status' => 'active']);
    }

    private function survey(User $manager, array $extra = []): object
    {
        $id = Workflow::insert('surveys', array_replace(['title' => 'Feedback survey', 'description' => 'Message for respondents', 'token' => (string)Str::uuid(), 'status' => 'published', 'anonymous' => true, 'created_by' => $manager->id, 'questions' => json_encode([['id' => (string)Str::uuid(), 'label' => 'Your feedback', 'type' => 'short_text', 'required' => true, 'help' => '', 'options' => []]])], $extra));
        return DB::table('surveys')->find($id);
    }

    private function response(object $survey, bool $anonymous, string $when): void
    {
        Workflow::insert('survey_responses', ['survey_id' => $survey->id, 'submission_token' => (string)Str::uuid(), 'anonymous' => $anonymous, 'respondent_name' => $anonymous ? null : 'Named respondent', 'respondent_email' => $anonymous ? null : 'named@example.test', 'questions_snapshot' => $survey->questions, 'answers' => '{}', 'submitted_at' => $when]);
    }

    private function xpath(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true); $document = new \DOMDocument;
        $document->loadHTML($html); libxml_clear_errors(); libxml_use_internal_errors($previous);
        return new \DOMXPath($document);
    }

    public function test_survey_statistics_cover_all_pages_and_respect_opening_windows(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(12, 0)); $manager = $this->manager();
        for ($i = 0; $i < 23; $i++) $this->survey($manager, ['status' => 'draft']);
        $open = $this->survey($manager, ['opens_at' => now(), 'closes_at' => now()->addHour()]);
        $this->survey($manager, ['opens_at' => now()->addHour()]); $this->survey($manager, ['closes_at' => now()]);
        $named = $this->survey($manager, ['status' => 'closed', 'anonymous' => false]);
        $this->response($open, true, now()->toDateTimeString()); $this->response($open, true, now()->subDay()->toDateTimeString()); $this->response($named, false, now()->toDateTimeString());
        $expected = ['total' => 27, 'open' => 1, 'drafts' => 23, 'responses' => 3, 'anonymous_responses' => 2];
        $this->actingAs($manager)->get('/admin/mel/surveys?page=2')->assertOk()->assertViewHas('surveyStats', $expected)->assertSee('Total surveys')->assertSee('Open for responses')->assertSee('2 anonymous');
        $this->get('/admin/mel')->assertOk()->assertViewHas('surveyStats', $expected)->assertSee('aria-label="Survey statistics"', false);
        $this->get('/admin/mel/surveys/'.$open->id.'/responses')->assertOk()->assertViewHas('responseStats', ['total' => 2, 'today' => 1, 'anonymous' => 2, 'named' => 0])->assertSee('Responses today');
    }

    public function test_survey_validation_and_success_messages_are_inside_the_response_form(): void
    {
        $survey = $this->survey($this->manager()); $question = json_decode($survey->questions, true)[0]; $token = (string)Str::uuid();
        $this->from('/surveys/'.$survey->token)->post('/surveys/'.$survey->token, ['submission_token' => $token, 'answers' => []])->assertSessionHasErrors();
        $html = $this->get('/surveys/'.$survey->token)->assertOk()->getContent(); $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//form[contains(@class,"public-response-form")]//div[@data-form-feedback and @role="alert"]')->length);
        $this->assertSame(0, $xpath->query('//main/div[@data-form-feedback]')->length);
        $this->assertSame(1, $xpath->query('//form[contains(@class,"public-response-form")]//section[contains(@class,"survey-form-intro")]')->length);
        $this->post('/surveys/'.$survey->token, ['submission_token' => $token, 'answers' => [$question['id'] => 'Useful learning']])->assertSessionHasNoErrors();
        $html = $this->get('/surveys/'.$survey->token)->assertOk()->assertSee('Your survey response has been recorded')->getContent(); $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//form[contains(@class,"public-response-form")]//div[@data-form-feedback and @role="status"]')->length);
        $this->assertSame(0, $xpath->query('//main/div[@data-form-feedback]')->length);
        $this->assertSame(0, $xpath->query('//form[contains(@class,"public-response-form")]//button[@type="submit"]')->length);
    }

    public function test_create_modal_errors_and_editor_success_feedback_are_form_local(): void
    {
        $this->actingAs($this->manager())->from('/admin/mel/surveys')->post('/admin/mel/surveys', ['title' => '', 'anonymous' => 1, '_modal_id' => 'create-survey'])->assertSessionHasErrors('title');
        $html = $this->get('/admin/mel/surveys')->assertOk()->assertSee('data-reopen-dialog="create-survey"', false)->getContent(); $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//dialog[@id="create-survey"]/form//div[@data-form-feedback and @role="alert"]')->length);
        $this->assertSame(0, $xpath->query('//main/div[@data-form-feedback]')->length);
        $this->post('/admin/mel/surveys', ['title' => 'New feedback survey', 'anonymous' => 1, '_modal_id' => 'create-survey'])->assertSessionHasNoErrors();
        $survey = DB::table('surveys')->first(); $html = $this->get('/admin/mel/surveys/'.$survey->id.'/edit')->assertOk()->getContent(); $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//form[@data-survey-builder]//div[@data-form-feedback and @role="status"]')->length);
        $this->assertSame(0, $xpath->query('//main/div[@data-form-feedback]')->length);
    }

    public function test_frontend_header_search_is_closed_by_default_and_keeps_search_query(): void
    {
        $html = $this->get('/search?q=workshop')->assertOk()->getContent(); $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//header//details[@data-header-search and not(@open)]')->length);
        $this->assertSame(1, $xpath->query('//header//details[@data-header-search]/summary[@aria-label="Open site search"]')->length);
        $this->assertSame('workshop', $xpath->query('//header//details[@data-header-search]//input[@name="q"]')->item(0)->getAttribute('value'));
        $this->assertSame('/search', $xpath->query('//header//form[@role="search"]')->item(0)->getAttribute('action'));
    }
}
