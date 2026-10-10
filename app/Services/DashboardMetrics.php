<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owner-level metrics for the staff dashboard.
 *
 * The returned shape is a contract with resources/views/admin/dashboard.blade.php.
 * Keys must not be renamed without updating the view.
 */
class DashboardMetrics
{
    public function forUser(User $user): array
    {
        $now = Carbon::now();

        return [
            'attention' => $this->attention($now),
            'money' => $user->manager() ? $this->money($now) : null,
            'growth' => $this->growth($now),
            'programmes' => $this->programmes(),
            'events' => $this->events($now),
            'activity' => $this->activity(),
        ];
    }

    private function attention(Carbon $now): array
    {
        return [
            'learners_awaiting_payment' => $this->learnersAwaitingPayment($now),
            'applications_to_review' => DB::table('applications')->whereIn('status', ['submitted', 'reviewing'])->count(),
            'submissions_to_review' => DB::table('submissions')->where('status', 'submitted')->count(),
            'practice_logs_pending' => DB::table('practice_logs')->where('status', 'pending')->count(),
            'bookings_requested' => DB::table('bookings')->where('status', 'requested')->count(),
            'contacts_new' => DB::table('contacts')->where('status', 'new')->count(),
        ];
    }

    /**
     * Participants who completed their profile, have not paid, and are not inside
     * their 24-hour free access window.
     */
    private function learnersAwaitingPayment(Carbon $now): int
    {
        return DB::table('users')
            ->where('role', 'participant')
            ->where('profile_complete', true)
            ->where('learning_access_paid', false)
            ->where(function ($query) use ($now) {
                $query->whereNull('full_access_until')->orWhere('full_access_until', '<=', $now);
            })
            ->count();
    }

    private function money(Carbon $now): array
    {
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        return [
            'payments_this_month_count' => DB::table('enrolments')
                ->whereNotNull('payment_confirmed_at')
                ->whereBetween('payment_confirmed_at', [$monthStart, $monthEnd])
                ->count(),
            // No learner payment table or amount column exists, so no value is reported.
            'payments_this_month_amount' => null,
            'trials_ending_7_days' => DB::table('enrolments')
                ->whereNotNull('trial_started_at')
                ->whereNull('payment_confirmed_at')
                ->whereBetween('trial_expires_at', [$now, $now->copy()->addDays(7)])
                ->count(),
        ];
    }

    private function growth(Carbon $now): array
    {
        $participants = DB::table('users')->where('role', 'participant');
        $total = (clone $participants)->count();
        $profileComplete = (clone $participants)->where('profile_complete', true)->count();
        $paid = (clone $participants)->where('learning_access_paid', true)->count();

        return [
            'registrations_this_month' => (clone $participants)
                ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->count(),
            'participants_total' => $total,
            'profile_complete_rate' => $total > 0 ? round($profileComplete * 100 / $total, 1) : 0.0,
            'funnel' => [
                'registered' => $total,
                'profile_complete' => $profileComplete,
                'paid' => $paid,
            ],
        ];
    }

    private function programmes(): array
    {
        $enrolments = DB::table('enrolments')->count();
        // A course certificate is the only recorded completion, so completion means a recommendation.
        $completed = DB::table('enrolments')
            ->join('course_certificates', function ($join) {
                $join->on('course_certificates.user_id', '=', 'enrolments.user_id')
                    ->on('course_certificates.course_id', '=', 'enrolments.course_id');
            })
            ->count();

        return [
            'active_cohorts' => DB::table('mel_cohorts')->where('active', true)->count(),
            'course_completion_rate' => $enrolments > 0 ? round($completed * 100 / $enrolments, 1) : 0.0,
            'mel_learners' => DB::table('users')->whereNotNull('learner_no')->count(),
        ];
    }

    private function events(Carbon $now): array
    {
        $upcoming = DB::table('events')
            ->where('status', 'published')
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->limit(5)
            ->get(['id', 'title', 'starts_at', 'capacity']);

        $registrations = DB::table('event_registrations')
            ->where('status', '!=', 'cancelled')
            ->whereIn('event_id', $upcoming->pluck('id'))
            ->groupBy('event_id')
            ->select('event_id', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'event_id');

        return [
            'upcoming' => $upcoming->map(fn ($event) => [
                'id' => (int) $event->id,
                'title' => (string) $event->title,
                'starts_at' => (string) $event->starts_at,
                'registered' => (int) ($registrations[$event->id] ?? 0),
                'capacity' => (int) $event->capacity > 0 ? (int) $event->capacity : null,
            ])->values()->all(),
            'open_calls' => DB::table('application_calls')
                ->where('status', 'published')
                ->where('opens_at', '<=', $now)
                ->where('closes_at', '>', $now)
                ->count(),
        ];
    }

    private function activity(): array
    {
        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->orderByDesc('audit_logs.id')
            ->limit(8)
            ->get([
                'audit_logs.action',
                'audit_logs.module',
                'audit_logs.created_at',
                'users.name as actor',
            ])
            ->map(fn ($entry) => [
                'action' => trim($entry->action.' '.$entry->module),
                'actor' => $entry->actor ?: 'System',
                'at' => (string) $entry->created_at,
            ])
            ->values()
            ->all();
    }
}
