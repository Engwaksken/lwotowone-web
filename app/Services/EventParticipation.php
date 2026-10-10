<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventParticipation
{
    public static function token(int $eventId): string
    {
        return DB::transaction(function () use ($eventId) {
            $event = DB::table('events')->where('id', $eventId)->lockForUpdate()->first(); abort_unless($event, 404);
            if ($event->registration_token) return $event->registration_token;
            $token = (string)Str::uuid(); DB::table('events')->where('id', $eventId)->update(['registration_token' => $token]); return $token;
        });
    }

    public static function register(int $eventId, ?User $user, array $participant = []): int
    {
        if ($user) abort_unless($user->role === 'participant' && $user->status === 'active', 403);
        return DB::transaction(function () use ($eventId, $user, $participant) {
            $event = DB::table('events')->where('id', $eventId)->lockForUpdate()->first(); abort_unless($event, 404);
            if ($event->status !== 'published' || now()->gte($event->starts_at)) Workflow::invalid('event_id', 'Event registration is closed.');
            $data = ['participant_name' => $user?->name ?? $participant['name'], 'participant_email' => strtolower(trim($user?->email ?? $participant['email'])), 'participant_phone' => $user?->phone ?? ($participant['phone'] ?? null)];
            $existing = DB::table('event_registrations')->where('event_id', $eventId)->where(function ($query) use ($user, $data) {
                $query->where('participant_email', $data['participant_email']); if ($user) $query->orWhere('user_id', $user->id);
            })->first();
            if ($existing && $existing->status !== 'cancelled') Workflow::invalid('event_id', 'This participant is already registered.');
            if ($event->capacity > 0 && DB::table('event_registrations')->where('event_id', $eventId)->where('status', '!=', 'cancelled')->count() >= $event->capacity) Workflow::invalid('event_id', 'This event is full.');
            if ($existing) {
                DB::table('event_registrations')->where('id', $existing->id)->update($data + ['status' => 'registered', 'attended_at' => null, 'attendance_recorded_at' => null, 'attendance_recorded_by' => null, 'updated_at' => now()]);
                return $existing->id;
            }
            return Workflow::insert('event_registrations', $data + ['event_id' => $eventId, 'user_id' => $user?->id, 'status' => 'registered']);
        });
    }

    public static function mark(int $eventId, int $registrationId, string $status, User $staff): void
    {
        abort_unless($staff->manager(), 403);
        abort_unless(in_array($status, ['registered', 'attended', 'absent', 'cancelled'], true), 422);
        DB::transaction(function () use ($eventId, $registrationId, $status, $staff) {
            $event = DB::table('events')->where('id', $eventId)->lockForUpdate()->first(); abort_unless($event, 404);
            $registration = DB::table('event_registrations')->where('event_id', $eventId)->where('id', $registrationId)->lockForUpdate()->first(); abort_unless($registration, 404);
            if (in_array($status, ['attended', 'absent'], true)) {
                if (now()->lt($event->starts_at) || $event->status !== 'published') Workflow::invalid('status', 'Record attendance after a published event has started.');
                if ($registration->status === 'cancelled') Workflow::invalid('status', 'A cancelled registration cannot be marked present or absent.');
            }
            if ($status === 'registered' && $registration->status === 'cancelled') {
                if ($event->status !== 'published' || now()->gte($event->starts_at)) Workflow::invalid('status', 'Registration is closed.');
                if ($event->capacity > 0 && DB::table('event_registrations')->where('event_id', $eventId)->where('status', '!=', 'cancelled')->count() >= $event->capacity) Workflow::invalid('status', 'This event is full.');
            }
            if ($registration->status === $status) return;
            DB::table('event_registrations')->where('id', $registrationId)->update(['status' => $status, 'attended_at' => $status === 'attended' ? now() : null, 'attendance_recorded_at' => now(), 'attendance_recorded_by' => $staff->id, 'updated_at' => now()]);
            Workflow::insert('audit_logs', ['user_id' => $staff->id, 'module' => 'event_registrations', 'action' => $status, 'record_id' => $registrationId]);
        });
    }
}
