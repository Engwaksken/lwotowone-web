<?php

namespace App\Http\Controllers;

use App\Services\Downloads;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadController extends Controller
{
    private function manager(Request $r): void
    {
        abort_unless($r->user()->manager(), 403);
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->role === 'participant', 403, 'Participant access required.');
        $d = $r->validate(['item_type'=>'required|in:'.implode(',', Downloads::TYPES), 'item_id'=>'required|integer', 'message'=>'nullable|string|max:1000']);
        app(Downloads::class)->request($r->user(), $d['item_type'], (int) $d['item_id'], trim((string) ($d['message'] ?? '')) ?: null);
        return back()->with('success', 'Download request sent. You will be notified when it is reviewed.');
    }

    /** The approved file itself, or one of the lesson's attached files. */
    public function download(Request $r, string $id, ?string $attachment = null)
    {
        $request = DB::table('download_requests')->where('id', $id)->where('user_id', $r->user()->id)->first();
        abort_unless($request, 404);
        abort_unless(Downloads::isActive($request), 403, 'This download is not available. Send a new request from the lesson.');
        $item = Downloads::item($request->item_type, (int) $request->item_id);
        abort_unless($item && Downloads::learnerCanOpen($r->user(), $request->item_type, $item), 403, 'This content is no longer available to you.');
        if ($attachment !== null) {
            $file = Downloads::attachments($request->item_type, (int) $item->id)->firstWhere('id', (int) $attachment);
            abort_unless($file && Storage::disk('local')->exists($file->file_path), 404);
            return $this->send($file->file_path, $file->original_name);
        }
        abort_unless($item->file_path && Storage::disk('local')->exists($item->file_path), 404);
        app(Downloads::class)->record($request, $r);
        return $this->send($item->file_path, Str::slug(Downloads::title($item)).'.'.pathinfo($item->file_path, PATHINFO_EXTENSION));
    }

    private function send(string $path, string $name)
    {
        return Storage::disk('local')->download($path, $name, ['Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff']);
    }

    public function index(Request $r)
    {
        $this->manager($r);
        $filters = $r->validate(['status'=>'nullable|in:pending,approved,rejected,revoked,all', 'q'=>'nullable|string|max:255']);
        $filters = ['status'=>$filters['status'] ?? 'pending', 'q'=>$filters['q'] ?? ''];
        $rows = DB::table('download_requests')->join('users', 'users.id', '=', 'download_requests.user_id')
            ->select('download_requests.*', 'users.name', 'users.email', 'users.learner_no')
            ->orderByRaw("CASE WHEN download_requests.status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('download_requests.updated_at');
        if ($filters['status'] !== 'all') $rows->where('download_requests.status', $filters['status']);
        if ($filters['q'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $rows->where(fn ($q) => $q->whereRaw("users.name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("users.email LIKE ? ESCAPE '!'", [$pattern]));
        }
        $rows = $rows->paginate(25)->withQueryString();
        $items = collect(Downloads::TYPES)->mapWithKeys(fn ($type) => [$type => DB::table($type)->whereIn('id', $rows->getCollection()->where('item_type', $type)->pluck('item_id'))->get()->keyBy('id')]);
        $courses = DB::table('courses')->whereIn('id', $items->flatten(1)->pluck('course_id')->unique())->pluck('title', 'id');
        $counts = DB::table('download_requests')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        return view('admin.download-requests', compact('rows', 'items', 'courses', 'counts', 'filters'));
    }

    public function decide(Request $r, string $id, string $decision)
    {
        $this->manager($r);
        abort_unless(in_array($decision, ['approve', 'reject', 'revoke'], true), 404);
        $d = $r->validate(['decision_note'=>'nullable|string|max:1000']);
        $note = trim((string) ($d['decision_note'] ?? '')) ?: null;
        $request = app(Downloads::class)->decide((int) $id, $decision, (int) $r->user()->id, $note);
        $item = Downloads::item($request->item_type, (int) $request->item_id);
        $title = $item ? Downloads::title($item) : 'learning content';
        [$subject, $body] = match ($decision) {
            'approve' => ['Download approved', "You can now download {$title} from your course. The download works ".Downloads::MAX_DOWNLOADS.' times until '.\Illuminate\Support\Carbon::parse($request->expires_at)->format('d M Y').'.'],
            'reject' => ['Download request declined', "Your request to download {$title} was not approved.".($note ? " Reason: {$note}" : '')],
            'revoke' => ['Download access withdrawn', "Download access to {$title} has been withdrawn.".($note ? " Reason: {$note}" : '')],
        };
        Workflow::notify((int) $request->user_id, $subject, $body);
        return back()->with('success', ['approve'=>'Download approved.', 'reject'=>'Request rejected.', 'revoke'=>'Download access withdrawn.'][$decision]);
    }
}
