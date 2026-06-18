<?php

namespace App\Services\Teachers;

use App\Models\ClassTeacher;
use App\Models\Staff;
use App\Models\SubjectTeacherAssignment;
use App\Models\User;

class TeacherAccessService
{
    public function forUser(?User $user): TeacherAssignmentScope
    {
        if (! $user) {
            return TeacherAssignmentScope::forNonTeacher();
        }

        $staff = $user->staff ?? Staff::query()->where('user_id', $user->id)->first();

        if (! $staff) {
            return $this->isTeacherUser($user)
                ? new TeacherAssignmentScope(true, null, collect(), collect())
                : TeacherAssignmentScope::forNonTeacher();
        }

        $subjectAssignments = SubjectTeacherAssignment::query()
            ->where('staff_id', $staff->id)
            ->with([
                'subject:id,name,code',
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
                'session:id,name',
                'term:id,name',
            ])
            ->orderByDesc('created_at')
            ->get();

        $classAssignments = ClassTeacher::query()
            ->where('staff_id', $staff->id)
            ->with([
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
                'session:id,name',
                'term:id,name',
            ])
            ->orderByDesc('created_at')
            ->get();

        if (
            ! $this->isTeacherUser($user)
            && ! $this->isTeacherStaff($staff)
            && $subjectAssignments->isEmpty()
            && $classAssignments->isEmpty()
        ) {
            return TeacherAssignmentScope::forNonTeacher();
        }

        return new TeacherAssignmentScope(true, $staff, $subjectAssignments, $classAssignments);
    }

    private function isTeacherUser(User $user): bool
    {
        $roleColumn = strtolower((string) ($user->role ?? ''));

        if ($roleColumn !== '' && str_contains($roleColumn, 'teacher')) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('teacher')) {
            return true;
        }

        return false;
    }

    private function isTeacherStaff(Staff $staff): bool
    {
        return str_contains(strtolower((string) $staff->role), 'teach');
    }
}
