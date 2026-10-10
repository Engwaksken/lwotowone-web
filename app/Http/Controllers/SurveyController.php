<?php
namespace App\Http\Controllers;

use App\Services\{Sharing, SurveyForms, Workflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SurveyController extends Controller
{
    private function manager(Request $request): void { abort_unless($request->user()->manager(), 403); }
    private function survey(string $id): object { $survey = DB::table('surveys')->find($id); abort_unless($survey, 404); return $survey; }

    public function index(Request $request)
    {
        $this->manager($request);
        $surveys = DB::table('surveys')->select('surveys.*')->selectSub(DB::table('survey_responses')->selectRaw('COUNT(*)')->whereColumn('survey_id', 'surveys.id'), 'response_count')->latest()->paginate(20)->withQueryString();
        return view('admin.surveys', ['surveys' => $surveys]);
    }

    public function create(Request $request)
    {
        $this->manager($request); $data = $request->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'anonymous' => 'required|boolean']);
        $id = DB::transaction(function () use ($request, $data) {
            $id = Workflow::insert('surveys', $data + ['token' => (string)Str::uuid(), 'status' => 'draft', 'created_by' => $request->user()->id, 'questions' => json_encode([['id' => (string)Str::uuid(), 'label' => 'Your feedback', 'type' => 'long_text', 'required' => true, 'help' => '', 'options' => []]], JSON_THROW_ON_ERROR)]);
            Workflow::insert('audit_logs', ['user_id' => $request->user()->id, 'module' => 'surveys', 'action' => 'create', 'record_id' => $id]); return $id;
        });
        return redirect('/admin/mel/surveys/'.$id.'/edit')->with('success', 'Survey created as a draft. Add your questions and publish it when ready.');
    }

    public function edit(Request $request, string $id)
    {
        $this->manager($request); $survey = $this->survey($id);
        return view('admin.survey-editor', ['survey' => $survey, 'questions' => json_decode($survey->questions, true), 'locked' => DB::table('survey_responses')->where('survey_id', $id)->exists()]);
    }

    public function update(Request $request, string $id)
    {
        $this->manager($request);
        $data = $request->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'status' => 'required|in:draft,published,closed', 'anonymous' => 'sometimes|required|boolean', 'opens_at' => 'nullable|date', 'closes_at' => $request->filled('opens_at')?'nullable|date|after:opens_at':'nullable|date']);
        DB::transaction(function () use ($request, $id, $data) {
            $survey = DB::table('surveys')->where('id', $id)->lockForUpdate()->first(); abort_unless($survey, 404);
            $locked = DB::table('survey_responses')->where('survey_id', $id)->exists(); $questions = json_decode($survey->questions, true);
            if ($request->exists('questions') || !$locked) $questions = SurveyForms::questions($request);
            if ($locked && ($questions !== json_decode($survey->questions, true) || (isset($data['anonymous']) && (bool)$data['anonymous'] !== (bool)$survey->anonymous))) {
                throw ValidationException::withMessages(['questions' => 'Questions and identity mode are locked after the first response. Duplicate this survey to make a revised form.']);
            }
            DB::table('surveys')->where('id', $id)->update($data + ['questions' => json_encode($questions, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            Workflow::insert('audit_logs', ['user_id' => $request->user()->id, 'module' => 'surveys', 'action' => 'update', 'record_id' => $id]);
        });
        return back()->with('success', 'Survey saved.');
    }

    public function duplicate(Request $request, string $id)
    {
        $this->manager($request); $survey = $this->survey($id);
        $newId = Workflow::insert('surveys', ['title' => mb_substr($survey->title.' (copy)', 0, 255), 'description' => $survey->description, 'anonymous' => $survey->anonymous, 'questions' => $survey->questions, 'token' => (string)Str::uuid(), 'status' => 'draft', 'created_by' => $request->user()->id]);
        Workflow::insert('audit_logs', ['user_id' => $request->user()->id, 'module' => 'surveys', 'action' => 'duplicate', 'record_id' => $newId]);
        return redirect('/admin/mel/surveys/'.$newId.'/edit')->with('success', 'Draft copy created with a new share link.');
    }

    public function qr(Request $request, string $id)
    {
        $this->manager($request); $survey = $this->survey($id);
        return Sharing::qr(url('/surveys/'.$survey->token), 'survey-'.$id.'-qr.svg', $request->boolean('download'));
    }

    public function show(Request $request, string $token)
    {
        $survey = DB::table('surveys')->where('token', $token)->whereIn('status', ['published', 'closed'])->first(); abort_unless($survey, 404);
        return view('public.survey', ['survey' => $survey, 'questions' => json_decode($survey->questions, true), 'open' => SurveyForms::open($survey)]);
    }

    public function submit(Request $request, string $token)
    {
        $data = $request->validate(['submission_token' => 'required|uuid']);
        DB::transaction(function () use ($request, $token, $data) {
            $survey = DB::table('surveys')->where('token', $token)->lockForUpdate()->first(); abort_unless($survey && $survey->status !== 'draft', 404);
            if (!SurveyForms::open($survey)) throw ValidationException::withMessages(['survey' => 'This survey is not currently accepting responses.']);
            if (DB::table('survey_responses')->where('survey_id', $survey->id)->where('submission_token', $data['submission_token'])->exists()) return;
            $questions = json_decode($survey->questions, true); $answers = SurveyForms::answers($questions, $request->input('answers'));
            $identity = $survey->anonymous ? ['user_id' => null, 'respondent_name' => null, 'respondent_email' => null] : $request->validate(['respondent_name' => 'required|string|max:255', 'respondent_email' => 'required|email|max:255']) + ['user_id' => $request->user()?->status === 'active' ? $request->user()->id : null];
            // Anonymous submissions deliberately store no account, name, email, IP or user-agent, and create no user-linked audit entry.
            Workflow::insert('survey_responses', $identity + ['survey_id' => $survey->id, 'anonymous' => (bool)$survey->anonymous, 'submission_token' => $data['submission_token'], 'answers' => json_encode($answers, JSON_THROW_ON_ERROR), 'questions_snapshot' => $survey->questions, 'submitted_at' => now()]);
        });
        return redirect('/surveys/'.$token)->with('survey_received', $token)->with('success', 'Thank you. Your survey response has been recorded.');
    }

    public function responses(Request $request, string $id)
    {
        $this->manager($request); $survey = $this->survey($id); $questions = json_decode($survey->questions, true);
        $responses = DB::table('survey_responses')->where('survey_id', $id)->latest('submitted_at')->latest('id')->paginate(25)->withQueryString();
        return view('admin.survey-responses', ['survey' => $survey, 'responses' => $responses, 'summary' => SurveyForms::summary($questions, DB::table('survey_responses')->where('survey_id', $id)->select('answers')->cursor())]);
    }

    public function export(Request $request, string $id)
    {
        $this->manager($request); $survey = $this->survey($id); $questions = json_decode($survey->questions, true);
        return response()->streamDownload(function () use ($id, $questions) {
            $out = fopen('php://output', 'w'); fputcsv($out, array_map([Sharing::class, 'csvCell'], array_merge(['Response', 'Submitted', 'Mode', 'Name', 'Email'], array_column($questions, 'label'))), ',', '"', '');
            foreach (DB::table('survey_responses')->where('survey_id', $id)->orderBy('id')->cursor() as $response) {
                $answers = json_decode($response->answers, true); $row = [$response->id, $response->submitted_at, $response->anonymous ? 'Anonymous' : 'Named', $response->anonymous ? '' : $response->respondent_name, $response->anonymous ? '' : $response->respondent_email];
                foreach ($questions as $question) $row[] = $answers[$question['id']] ?? '';
                fputcsv($out, array_map([Sharing::class, 'csvCell'], $row), ',', '"', '');
            }
            fclose($out);
        }, 'survey-'.$id.'-responses.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
