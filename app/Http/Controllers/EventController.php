<?php
namespace App\Http\Controllers;

use App\Services\{EventParticipation, Sharing, Workflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    private function manager(Request $request): void { abort_unless($request->user()->manager(), 403); }

    public function index(Request $request)
    {
        $this->manager($request);
        $filters = $request->validate(['q' => 'nullable|string|max:255', 'status' => 'nullable|in:all,draft,published,cancelled']);
        $filters = array_replace(['q' => '', 'status' => 'all'], array_filter($filters, fn ($value) => $value !== null));
        $query = DB::table('events');
        if ($filters['status'] !== 'all') $query->where('status', $filters['status']);
        if ($filters['q'] !== '') $query->whereRaw("title LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%']);
        foreach (['registered_count' => ['registered', 'attended', 'absent'], 'attended_count' => ['attended']] as $alias => $statuses) {
            $query->selectSub(DB::table('event_registrations')->selectRaw('COUNT(*)')->whereColumn('event_id', 'events.id')->whereIn('status', $statuses), $alias);
        }
        $events = $query->addSelect('events.*')->latest('events.id')->paginate(15)->withQueryString();
        foreach ($events as $event) $event->registration_token = EventParticipation::token((int)$event->id);
        return view('admin.events', ['events' => $events, 'filters' => $filters, 'module' => 'events', 'meta' => config('modules.events'), 'options' => []]);
    }

    public function qr(Request $request, string $id)
    {
        $this->manager($request);
        return Sharing::qr(url('/events/'.EventParticipation::token((int)$id)), 'event-'.$id.'-qr.svg', $request->boolean('download'));
    }

    public function show(Request $request, string $token)
    {
        $event = DB::table('events')->where('registration_token', $token)->where('status', 'published')->first(); abort_unless($event, 404);
        $registered = DB::table('event_registrations')->where('event_id', $event->id)->where('status', '!=', 'cancelled')->count();
        $own = $request->user()?->role === 'participant' ? DB::table('event_registrations')->where('event_id', $event->id)->where('user_id', $request->user()->id)->first() : null;
        return view('public.event-registration', ['event' => $event, 'registered' => $registered, 'registration' => $own]);
    }

    public function register(Request $request, string $token)
    {
        $event = DB::table('events')->where('registration_token', $token)->where('status', 'published')->first(); abort_unless($event, 404);
        $data = $request->user() ? [] : $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255', 'phone' => 'nullable|string|max:40']);
        $id = EventParticipation::register((int)$event->id, $request->user(), $data);
        Workflow::insert('audit_logs', ['user_id' => $request->user()?->id, 'module' => 'event_registrations', 'action' => 'register', 'record_id' => $id]);
        return redirect('/events/'.$token)->with('success', 'Registration received. Your place at the event is reserved.');
    }

    public function attendance(Request $request, string $id)
    {
        $this->manager($request); $event = DB::table('events')->find($id); abort_unless($event, 404);
        $filters = $request->validate(['q' => 'nullable|string|max:255', 'status' => 'nullable|in:all,registered,attended,absent,cancelled']);
        $filters = array_replace(['q' => '', 'status' => 'all'], array_filter($filters, fn ($value) => $value !== null));
        $query = $this->roster((int)$id);
        if ($filters['status'] !== 'all') $query->where('event_registrations.status', $filters['status']);
        if ($filters['q'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->whereRaw("COALESCE(participant_name, users.name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("COALESCE(participant_email, users.email) LIKE ? ESCAPE '!'", [$pattern]));
        }
        $counts = DB::table('event_registrations')->where('event_id', $id)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        return view('admin.event-attendance', ['event' => $event, 'registrations' => $query->orderBy('event_registrations.id')->paginate(25)->withQueryString(), 'counts' => $counts, 'filters' => $filters]);
    }

    private function roster(int $id)
    {
        return DB::table('event_registrations')->leftJoin('users', 'users.id', '=', 'event_registrations.user_id')->where('event_id', $id)
            ->select('event_registrations.*', DB::raw('COALESCE(participant_name, users.name) as name'), DB::raw('COALESCE(participant_email, users.email) as email'), DB::raw('COALESCE(participant_phone, users.phone) as phone'));
    }

    public function mark(Request $request, string $id, string $registration)
    {
        $this->manager($request); $data = $request->validate(['status' => 'required|in:registered,attended,absent,cancelled']);
        EventParticipation::mark((int)$id, (int)$registration, $data['status'], $request->user());
        return back()->with('success', 'Attendance updated.');
    }

    public function export(Request $request, string $id)
    {
        $this->manager($request); abort_unless(DB::table('events')->where('id', $id)->exists(), 404);
        return response()->streamDownload(function () use ($id) {
            $out = fopen('php://output', 'w'); fputcsv($out, ['Name', 'Email', 'Phone', 'Status', 'Registered', 'Attended', 'Attendance recorded'], ',', '"', '');
            foreach ($this->roster((int)$id)->orderBy('event_registrations.id')->cursor() as $row) fputcsv($out, array_map([Sharing::class, 'csvCell'], [$row->name, $row->email, $row->phone, $row->status, $row->created_at, $row->attended_at, $row->attendance_recorded_at]), ',', '"', '');
            fclose($out);
        }, 'event-'.$id.'-attendance.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
