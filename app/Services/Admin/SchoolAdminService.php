<?php

namespace App\Services\Admin;

use App\Models\Agent;
use App\Models\School;
use App\Models\SchoolParent;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Session as SchoolSession;
use App\Models\Term;
use App\Models\TermSummary;
use App\Models\TermPaymentTransaction;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SchoolAdminService
{
    public function getDashboardSummary(): array
    {
        $totalPaymentReceived = (float) TermPaymentTransaction::query()
            ->where('status', 'success')
            ->sum('amount');

        $totalOutstandingBalance = (float) Term::query()
            ->selectRaw('COALESCE(SUM((COALESCE(amount_due, 0) + COALESCE(midterm_amount_due, 0)) - (COALESCE(amount_paid, 0) + COALESCE(midterm_amount_paid, 0))), 0) as outstanding')
            ->value('outstanding');

        return [
            'schools_total' => School::query()->count(),
            'active_schools' => School::query()->where('status', 'active')->count(),
            'students_total' => Student::query()->count(),
            'staff_total' => Staff::query()->count(),
            'parents_total' => SchoolParent::query()->count(),
            'agents_total' => Agent::query()->count(),
            'pending_agents' => Agent::query()->where('status', 'pending')->count(),
            'payment_received_total' => $totalPaymentReceived,
            'outstanding_balance_total' => max(0, $totalOutstandingBalance),
        ];
    }

    public function listSchools(?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $search = trim((string) $search);

        $schools = School::query()
            ->withCount(['students', 'staff', 'parents'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('subdomain', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $summaries = $this->paymentSummariesForSchools($schools->getCollection()->pluck('id'));

        $schools->setCollection(
            $schools->getCollection()->map(function (School $school) use ($summaries) {
                return $this->formatSchool($school, $summaries[$school->id] ?? $this->emptyPaymentSummary());
            })
        );

        return $schools;
    }

    public function getSchoolDetails(School $school): array
    {
        $schoolId = (string) $school->getKey();

        if ($schoolId !== '') {
            // Refresh with counts using an explicit query to avoid loadCount edge cases.
            $school = School::query()
                ->withCount(['students', 'staff', 'parents'])
                ->with([
                    'currentSession' => function ($query) {
                        $query->select('id', 'school_id', 'name', 'status', 'start_date', 'end_date', 'created_at', 'updated_at');
                    },
                    'currentTerm' => function ($query) {
                        $query
                            ->select('id', 'school_id', 'session_id', 'name', 'term_number', 'status', 'payment_status', 'start_date', 'end_date', 'created_at', 'updated_at')
                            ->with('session:id,name');
                    },
                    'users' => function ($query) {
                        $query
                            ->select('id', 'school_id', 'name', 'email', 'role', 'status', 'last_login', 'email_verified_at', 'phone')
                            ->with('roles:id,name');
                    },
                ])
                ->find($schoolId) ?? $school;
        }

        $summary = $this->paymentSummariesForSchools(collect([$schoolId]))[$schoolId]
            ?? $this->emptyPaymentSummary();

        $configuredSessionId = trim((string) ($school->current_session_id ?? ''));

        $schoolSessions = SchoolSession::query()
            ->where('school_id', $school->id)
            ->when($configuredSessionId !== '', function ($query) use ($configuredSessionId) {
                $query->orWhere('id', $configuredSessionId);
            })
            ->orderByRaw("CASE WHEN status IN ('active', 'current', 'ongoing') THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SchoolSession $session) => $this->formatSession($session))
            ->filter()
            ->values();

        $schoolTerms = $this->schoolTermsQuery($school)
            ->with('session:id,name')
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Term $term) {
                $amountDue = (float) (($term->amount_due ?? 0) + ($term->midterm_amount_due ?? 0));
                $amountPaid = (float) (($term->amount_paid ?? 0) + ($term->midterm_amount_paid ?? 0));

                return [
                    'id' => $term->id,
                    'name' => $term->name,
                    'term_number' => (int) ($term->term_number ?? 0),
                    'session' => $term->session?->name,
                    'status' => $term->status,
                    'payment_status' => $term->payment_status,
                    'amount_due' => $amountDue,
                    'amount_paid' => $amountPaid,
                    'outstanding_balance' => max(0, $amountDue - $amountPaid),
                    'start_date' => $term->start_date,
                    'end_date' => $term->end_date,
                ];
            })
            ->values();
        $recentTerms = $schoolTerms->take(6)->values();

        $recentTransactions = TermPaymentTransaction::query()
            ->where('school_id', $school->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (TermPaymentTransaction $transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'provider' => $transaction->provider,
                    'paid_at' => $transaction->paid_at,
                    'created_at' => $transaction->created_at,
                ];
            })
            ->values();

        [$summaryTerm, $summarySession] = $this->resolveCurrentContextFromSummaries($school);
        $currentTerm = $this->resolveCurrentTerm($school, $summaryTerm);
        $currentSession = $this->resolveCurrentSession($school, $currentTerm, $summarySession);

        $schoolAdmins = $this->resolveSchoolAdmins($school);

        return [
            ...$this->formatSchool($school, $summary),
            'school_profile' => $this->formatSchoolProfile($school),
            'current_session' => $this->formatSession($currentSession),
            'current_term' => $this->formatTerm($currentTerm),
            'school_sessions' => $schoolSessions,
            'school_terms' => $schoolTerms,
            'school_admins_count' => $schoolAdmins->count(),
            'school_admins' => $schoolAdmins->values(),
            'recent_terms' => $recentTerms,
            'recent_transactions' => $recentTransactions,
        ];
    }

    private function resolveSchoolAdmins(School $school): Collection
    {
        $allowedRoles = ['owner', 'super_admin', 'admin'];

        if (! $school->relationLoaded('users')) {
            $school->load([
                'users' => function ($query) {
                    $query
                        ->select('id', 'school_id', 'name', 'email', 'role', 'status', 'last_login', 'email_verified_at', 'phone')
                        ->with('roles:id,name');
                },
            ]);
        }

        return $school->users
            ->map(function (User $user) use ($allowedRoles) {
                $primaryRole = strtolower((string) ($user->role ?? ''));
                $roleNames = $user->roles
                    ->pluck('name')
                    ->map(fn ($name) => strtolower((string) $name))
                    ->unique()
                    ->values();

                $isAdminRole = in_array($primaryRole, $allowedRoles, true)
                    || $roleNames->intersect($allowedRoles)->isNotEmpty();

                if (! $isAdminRole) {
                    return null;
                }

                $resolvedRole = $primaryRole !== ''
                    ? $primaryRole
                    : ($roleNames->first() ?? 'admin');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'role' => $resolvedRole,
                    'roles' => $roleNames->values(),
                    'last_login' => $user->last_login,
                    'email_verified_at' => $user->email_verified_at,
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $entry) => $this->roleRank((string) ($entry['role'] ?? '')),
                fn (array $entry) => strtolower((string) ($entry['name'] ?? '')),
            ])
            ->values();
    }

    private function roleRank(string $role): int
    {
        return match (strtolower($role)) {
            'owner' => 0,
            'super_admin' => 1,
            'admin' => 2,
            default => 3,
        };
    }

    private function resolveCurrentSession(
        School $school,
        ?Term $currentTerm,
        ?SchoolSession $summarySession = null,
    ): ?SchoolSession
    {
        $session = $school->currentSession;
        if ($session) {
            return $session;
        }

        $configuredSessionId = trim((string) ($school->current_session_id ?? ''));
        if ($configuredSessionId !== '') {
            $configuredSession = SchoolSession::query()
                ->whereKey($configuredSessionId)
                ->first();

            if ($configuredSession) {
                return $configuredSession;
            }
        }

        // Try to get session from the resolved current term
        if ($currentTerm?->session) {
            return $currentTerm->session;
        }

        if ($currentTerm?->session_id) {
            $termSession = SchoolSession::query()
                ->whereKey((string) $currentTerm->session_id)
                ->first();

            if ($termSession) {
                return $termSession;
            }
        }

        if ($summarySession) {
            return $summarySession;
        }

        // Find the most commonly used session by students
        $mostUsedStudentSessionId = (string) (
            Student::query()
                ->where('school_id', $school->id)
                ->whereNotNull('current_session_id')
                ->select('current_session_id')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('current_session_id')
                ->orderByDesc('aggregate')
                ->value('current_session_id') ?? ''
        );

        if ($mostUsedStudentSessionId !== '') {
            $studentSession = SchoolSession::query()
                ->whereKey($mostUsedStudentSessionId)
                ->first();

            if ($studentSession) {
                return $studentSession;
            }
        }

        // Fallback: prefer active sessions, then recent sessions
        return SchoolSession::query()
            ->where('school_id', $school->id)
            ->orderByRaw("CASE WHEN status IN ('active', 'current', 'ongoing') THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->first();
    }

    private function resolveCurrentTerm(School $school, ?Term $summaryTerm = null): ?Term
    {
        $term = $school->currentTerm;
        if ($term) {
            $term->loadMissing('session:id,name');
            return $term;
        }

        $configuredTermId = trim((string) ($school->current_term_id ?? ''));
        if ($configuredTermId !== '') {
            $configuredTerm = $this->schoolTermsQuery($school)
                ->whereKey($configuredTermId)
                ->with('session:id,name')
                ->first();

            if ($configuredTerm) {
                return $configuredTerm;
            }
        }

        $configuredSessionId = trim((string) ($school->current_session_id ?? ''));
        if ($configuredSessionId !== '') {
            $termInConfiguredSession = $this->schoolTermsQuery($school)
                ->where('session_id', $configuredSessionId)
                ->with('session:id,name')
                ->orderByRaw("CASE WHEN status IN ('active', 'current', 'ongoing', 'in_progress') THEN 0 ELSE 1 END")
                ->orderByDesc('term_number')
                ->orderByDesc('start_date')
                ->orderByDesc('created_at')
                ->first();

            if ($termInConfiguredSession) {
                return $termInConfiguredSession;
            }
        }

        $mostUsedStudentTermId = (string) (
            Student::query()
                ->where('school_id', $school->id)
                ->whereNotNull('current_term_id')
                ->select('current_term_id')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('current_term_id')
                ->orderByDesc('aggregate')
                ->value('current_term_id') ?? ''
        );

        if ($mostUsedStudentTermId !== '') {
            $studentTerm = Term::query()
                ->whereKey($mostUsedStudentTermId)
                ->with('session:id,name')
                ->first();

            if ($studentTerm) {
                return $studentTerm;
            }
        }

        if ($summaryTerm) {
            $summaryTerm->loadMissing('session:id,name');
            return $summaryTerm;
        }

        // Enhanced fallback: prefer active terms, then recent terms
        return $this->schoolTermsQuery($school)
            ->with('session:id,name')
            ->orderByRaw("CASE WHEN status IN ('active', 'current', 'ongoing', 'in_progress') THEN 0 ELSE 1 END")
            ->orderByDesc('term_number')
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->first();
    }

    private function schoolTermsQuery(School $school): Builder
    {
        $configuredSessionId = trim((string) ($school->current_session_id ?? ''));
        $configuredTermId = trim((string) ($school->current_term_id ?? ''));

        return Term::query()
            ->where(function (Builder $query) use ($school) {
                $query
                    ->where('terms.school_id', $school->id)
                    ->orWhereHas('session', function (Builder $sessionQuery) use ($school) {
                        $sessionQuery->where('sessions.school_id', $school->id);
                    });
            })
            ->when($configuredSessionId !== '', function (Builder $query) use ($configuredSessionId) {
                $query->orWhere('terms.session_id', $configuredSessionId);
            })
            ->when($configuredTermId !== '', function (Builder $query) use ($configuredTermId) {
                $query->orWhere('terms.id', $configuredTermId);
            });
    }

    /**
     * @return array{0: ?Term, 1: ?SchoolSession}
     */
    private function resolveCurrentContextFromSummaries(School $school): array
    {
        $row = TermSummary::query()
            ->join('students', 'students.id', '=', 'term_summaries.student_id')
            ->where('students.school_id', $school->id)
            ->select('term_summaries.term_id', 'term_summaries.session_id')
            ->selectRaw('MAX(term_summaries.created_at) as latest_summary_at')
            ->groupBy('term_summaries.term_id', 'term_summaries.session_id')
            ->orderByDesc('latest_summary_at')
            ->first();

        if (! $row) {
            return [null, null];
        }

        $termId = trim((string) ($row->term_id ?? ''));
        $sessionId = trim((string) ($row->session_id ?? ''));

        $term = null;
        if ($termId !== '') {
            $term = Term::query()
                ->whereKey($termId)
                ->with('session:id,name')
                ->first();
        }

        $session = null;
        if ($sessionId !== '') {
            $session = SchoolSession::query()
                ->whereKey($sessionId)
                ->first();
        }

        if (! $session && $term?->session) {
            $session = $term->session;
        }

        return [$term, $session];
    }

    private function formatSchoolProfile(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'acronym' => $school->acronym,
            'resolved_acronym' => $school->resolved_acronym,
            'slug' => $school->slug,
            'subdomain' => $school->subdomain,
            'status' => $school->status,
            'owner_name' => $school->owner_name,
            'email' => $school->email,
            'phone' => $school->phone,
            'address' => $school->address,
            'logo_url' => $school->logo_url,
            'signature_url' => $school->signature_url,
            'student_portal_link' => $school->student_portal_link,
            'established_at' => $school->established_at,
            'enable_free_trial' => (bool) ($school->enable_free_trial ?? false),
            'result_show_grade' => (bool) ($school->result_show_grade ?? false),
            'result_show_position' => (bool) ($school->result_show_position ?? false),
            'result_show_class_average' => (bool) ($school->result_show_class_average ?? false),
            'result_show_lowest' => (bool) ($school->result_show_lowest ?? false),
            'result_show_highest' => (bool) ($school->result_show_highest ?? false),
            'result_show_remarks' => (bool) ($school->result_show_remarks ?? false),
            'result_comment_mode' => $school->result_comment_mode,
            'current_session_id' => $school->current_session_id,
            'current_term_id' => $school->current_term_id,
            'created_at' => $school->created_at,
            'updated_at' => $school->updated_at,
        ];
    }

    private function formatSession(?SchoolSession $session): ?array
    {
        if (! $session) {
            return null;
        }

        return [
            'id' => $session->id,
            'name' => $session->name,
            'status' => $session->status,
            'start_date' => $session->start_date,
            'end_date' => $session->end_date,
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
        ];
    }

    private function formatTerm(?Term $term): ?array
    {
        if (! $term) {
            return null;
        }

        return [
            'id' => $term->id,
            'name' => $term->name,
            'term_number' => (int) ($term->term_number ?? 0),
            'status' => $term->status,
            'payment_status' => $term->payment_status,
            'session_id' => $term->session_id,
            'session_name' => $term->session?->name,
            'start_date' => $term->start_date,
            'end_date' => $term->end_date,
            'created_at' => $term->created_at,
            'updated_at' => $term->updated_at,
        ];
    }

    private function formatSchool(School $school, array $paymentSummary): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'subdomain' => $school->subdomain,
            'status' => $school->status,
            'email' => $school->email,
            'phone' => $school->phone,
            'owner_name' => $school->owner_name,
            'address' => $school->address,
            'current_session_id' => $school->current_session_id,
            'current_term_id' => $school->current_term_id,
            'created_at' => $school->created_at,
            'updated_at' => $school->updated_at,
            'students_count' => (int) ($school->students_count ?? 0),
            'staff_count' => (int) ($school->staff_count ?? 0),
            'parents_count' => (int) ($school->parents_count ?? 0),
            'payment_summary' => $paymentSummary,
        ];
    }

    private function paymentSummariesForSchools(Collection $schoolIds): array
    {
        $schoolIds = $schoolIds
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values();

        if ($schoolIds->isEmpty()) {
            return [];
        }

        $termTotals = Term::query()
            ->whereIn('school_id', $schoolIds)
            ->select('school_id')
            ->selectRaw('COALESCE(SUM(COALESCE(amount_due, 0) + COALESCE(midterm_amount_due, 0)), 0) as total_due')
            ->selectRaw('COALESCE(SUM(COALESCE(amount_paid, 0) + COALESCE(midterm_amount_paid, 0)), 0) as total_paid')
            ->groupBy('school_id')
            ->get()
            ->keyBy('school_id');

        $transactionTotals = TermPaymentTransaction::query()
            ->whereIn('school_id', $schoolIds)
            ->select('school_id')
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_transactions")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) as successful_amount_total")
            ->selectRaw("MAX(CASE WHEN status = 'success' THEN paid_at ELSE NULL END) as last_paid_at")
            ->groupBy('school_id')
            ->get()
            ->keyBy('school_id');

        $lastSuccessfulPayments = TermPaymentTransaction::query()
            ->whereIn('school_id', $schoolIds)
            ->where('status', 'success')
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get(['school_id', 'reference'])
            ->unique('school_id')
            ->keyBy('school_id');

        $result = [];

        foreach ($schoolIds as $schoolId) {
            $term = $termTotals->get($schoolId);
            $transaction = $transactionTotals->get($schoolId);
            $lastPayment = $lastSuccessfulPayments->get($schoolId);

            $totalDue = (float) ($term?->total_due ?? 0);
            $totalPaid = (float) ($term?->total_paid ?? 0);

            $result[$schoolId] = [
                'total_due' => $totalDue,
                'total_paid' => $totalPaid,
                'outstanding_balance' => max(0, $totalDue - $totalPaid),
                'successful_payments_total' => (float) ($transaction?->successful_amount_total ?? 0),
                'successful_transactions' => (int) ($transaction?->successful_transactions ?? 0),
                'total_transactions' => (int) ($transaction?->total_transactions ?? 0),
                'last_paid_at' => $transaction?->last_paid_at,
                'last_paid_reference' => $lastPayment?->reference,
            ];
        }

        return $result;
    }

    private function emptyPaymentSummary(): array
    {
        return [
            'total_due' => 0.0,
            'total_paid' => 0.0,
            'outstanding_balance' => 0.0,
            'successful_payments_total' => 0.0,
            'successful_transactions' => 0,
            'total_transactions' => 0,
            'last_paid_at' => null,
            'last_paid_reference' => null,
        ];
    }
}
