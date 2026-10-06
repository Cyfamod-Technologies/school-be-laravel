<?php

namespace App\Services\Fees;

use App\Models\FinanceAuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Writes the who/what/when/before/after trail behind every money-moving
 * action in the fees module.
 *
 * Deliberately not the existing `audit_logs` table: that one hard-keys its
 * actor to `users`, and a student submitting their own payment is an actor
 * here too.
 */
class FinanceAuditLogger
{
    public const ACTION_ASSIGNMENT_CREATED = 'fee_assignment.created';

    public const ACTION_ASSIGNMENT_UPDATED = 'fee_assignment.updated';

    public const ACTION_ASSIGNMENT_DELETED = 'fee_assignment.deleted';

    public const ACTION_BILL_GENERATED = 'bill.generated';

    public function log(
        string $schoolId,
        string $action,
        string $subjectType,
        ?string $subjectId,
        ?array $before = null,
        ?array $after = null,
        ?Model $actor = null,
    ): FinanceAuditLog {
        [$actorType, $actorId, $actorName] = $this->describeActor($actor);

        return FinanceAuditLog::create([
            'school_id' => $schoolId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $this->clientIp(),
        ]);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function describeActor(?Model $actor): array
    {
        if ($actor instanceof User) {
            return [
                FinanceAuditLog::ACTOR_USER,
                (string) $actor->getKey(),
                trim((string) ($actor->name ?? '')) ?: null,
            ];
        }

        if ($actor instanceof Student) {
            $name = trim(implode(' ', array_filter([$actor->first_name, $actor->last_name])));

            return [
                FinanceAuditLog::ACTOR_STUDENT,
                (string) $actor->getKey(),
                $name ?: null,
            ];
        }

        // A queued job or an artisan command has no human behind it.
        return [FinanceAuditLog::ACTOR_SYSTEM, null, null];
    }

    private function clientIp(): ?string
    {
        // Console runs have no request to read an IP from.
        if (app()->runningInConsole()) {
            return null;
        }

        return Request::ip();
    }
}
