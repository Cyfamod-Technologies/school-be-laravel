<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission Seeder
 * 
 * Seeds all frontend permissions into the database.
 * Run this seeder to add all permissions defined in the frontend.
 * 
 * Usage: php artisan db:seed --class=FrontendPermissionSeeder
 */
class FrontendPermissionSeeder extends Seeder
{
    /**
     * All permissions defined in the frontend application.
     * These match the permission keys in nextjs/lib/permissionKeys.ts
     */
    protected array $permissions = [
        'dashboard.view',
        'dashboard.stats.students',
        'dashboard.stats.teachers',
        'dashboard.stats.parents',
        'profile.view',
        'admin.profile.update',
        'settings.school.view',
        'settings.school.update',
        'settings.school.session.update',
        'settings.school.term.update',
        'sessions.view',
        'sessions.create',
        'sessions.update',
        'sessions.delete',
        'terms.view',
        'terms.create',
        'terms.update',
        'terms.delete',
        'classes.view',
        'classes.create',
        'classes.update',
        'classes.delete',
        'class-arms.view',
        'class-arms.create',
        'class-arms.update',
        'class-arms.delete',
        'class-sections.view',
        'class-sections.create',
        'class-sections.update',
        'class-sections.delete',
        'parents.view',
        'parents.create',
        'parents.update',
        'parents.delete',
        'students.view',
        'students.create',
        'students.update',
        'students.delete',
        'students.export',
        'results.bulk.view',
        'results.bulk.generate',
        'results.bulk.download',
        'results.bulk.print',
        'results.check',
        'skills.ratings.view',
        'skills.ratings.enter',
        'skills.ratings.update',
        'results.early-years.view',
        'results.early-years.generate',
        'staff.view',
        'staff.create',
        'staff.update',
        'staff.delete',
        'staff.roles.assign',
        'staff.roles.update',
        'subjects.view',
        'subjects.create',
        'subjects.update',
        'subjects.delete',
        'subject.assignments.view',
        'subject.assignments.create',
        'subject.assignments.update',
        'subject.assignments.delete',
        'teacher.assignments.view',
        'teacher.assignments.create',
        'teacher.assignments.update',
        'teacher.assignments.delete',
        'class-teachers.view',
        'class-teachers.create',
        'class-teachers.update',
        'class-teachers.delete',
        'assessment.components.view',
        'assessment.components.create',
        'assessment.components.update',
        'assessment.components.delete',
        'assessment.structures.view',
        'assessment.components.cbt-link',
        'assessment.structures.create',
        'assessment.structures.update',
        'assessment.structures.delete',
        'assessment.cbt-link.view',
        'assessment.cbt-link.create',
        'assessment.cbt-link.delete',
        'assessment.grade-scales.view',
        'assessment.grade-scales.create',
        'assessment.grade-scales.update',
        'assessment.grade-scales.delete',
        'assessment.grade-scales.set-active',
        'result.pin.view',
        'result.pin.create',
        'result.pin.bulk-create',
        'result.pin.invalidate',
        'result.pin.export',
        'settings.result-page.view',
        'settings.result-page.update',
        'results.entry.view',
        'results.entry.enter',
        'results.entry.save',
        'skills.categories.view',
        'skills.categories.create',
        'skills.categories.update',
        'skills.categories.delete',
        'skills.types.view',
        'skills.types.create',
        'skills.types.update',
        'skills.types.delete',
        'students.promotion.view',
        'students.promotion.execute',
        'students.promotion.bulk',
        'students.promotion.reports.view',
        'students.promotion.reports.export',
        'academic.rollover.view',
        'academic.rollover.execute',
        'attendance.dashboard.view',
        'attendance.stats.view',
        'attendance.student.view',
        'attendance.student.mark',
        'attendance.student.update',
        'attendance.student.delete',
        'attendance.student.export',
        'attendance.student.history',
        'attendance.staff.view',
        'attendance.staff.mark',
        'attendance.staff.update',
        'attendance.staff.delete',
        'attendance.staff.export',
        'students.bulk-upload.view',
        'students.bulk-upload.upload',
        'students.bulk-upload.preview',
        'students.bulk-upload.execute',
        'students.bulk-upload.template',
        'finance.bank.view',
        'finance.bank.update',
        'finance.fee-items.view',
        'finance.fee-items.create',
        'finance.fee-items.update',
        'finance.fee-items.delete',
        'finance.fee-structures.view',
        'finance.fee-structures.create',
        'finance.fee-structures.update',
        'finance.fee-structures.delete',
        'finance.fee-structures.copy',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.permissions.assign',
        'user-roles.view',
        'user-roles.assign',
        'user-roles.remove',
        'staff.dashboard.view',
        'staff.classes.view',
        'staff.subjects.view',
        'staff.profile.view',
        'staff.profile.update',
        'student.dashboard.view',
        'student.bio.view',
        'student.result.view',
        'student.result.download',
        'cbt.quizzes.view',
        'cbt.quizzes.take',
        'cbt.quizzes.submit',
        'cbt.history.view',
        'cbt.results.view',
        'cbt.results.review',
        'cbt.admin.view',
        'cbt.admin.create',
        'cbt.admin.update',
        'cbt.admin.delete',
        'cbt.admin.publish',
        'cbt.admin.unpublish',
        'cbt.admin.results.view',
        'cbt.questions.view',
        'cbt.questions.create',
        'cbt.questions.update',
        'cbt.questions.delete',
        'cbt.questions.reorder',
        'cbt.admin.results.export',
        'cbt.admin.results.grade',
        'cbt.links.view',
        'cbt.links.create',
        'cbt.links.delete',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'web';
        $created = 0;
        $existing = 0;

        foreach ($this->permissions as $permission) {
            $exists = Permission::where('name', $permission)
                ->where('guard_name', $guardName)
                ->exists();

            if (!$exists) {
                Permission::create([
                    'name' => $permission,
                    'guard_name' => $guardName,
                ]);
                $created++;
            } else {
                $existing++;
            }
        }

        $this->command->info("Frontend permissions seeded: {$created} created, {$existing} already existed.");
        $this->command->info("Total permissions: " . count($this->permissions));
    }
}
