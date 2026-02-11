<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\School;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission Seeder
 * 
 * Seeds all frontend permissions into the database for each school.
 * Run this seeder to add all permissions defined in the frontend.
 * 
 * Usage: 
 *   php artisan db:seed --class=FrontendPermissionSeeder
 *   php artisan permissions:seed [school_id] [--guard=sanctum]
 */
class FrontendPermissionSeeder extends Seeder
{
    /**
     * All permissions defined in the frontend application.
     * These match the permission keys in nextjs/lib/permissionKeys.ts
     * 
     * @var array<string, string> Key => Description
     */
    public static array $permissions = [
        // Dashboard
        'dashboard.view' => 'View the main dashboard',
        'dashboard.stats.students' => 'View student statistics on dashboard',
        'dashboard.stats.teachers' => 'View teacher statistics on dashboard',
        'dashboard.stats.parents' => 'View parent statistics on dashboard',
        
        // Profile
        'profile.view' => 'View own profile',
        'admin.profile.update' => 'Update admin profile',
        
        // School Settings
        'settings.school.view' => 'View school settings',
        'settings.school.update' => 'Update school settings',
        'settings.school.session.update' => 'Change active session',
        'settings.school.term.update' => 'Change active term',
        
        // Sessions
        'sessions.view' => 'View academic sessions',
        'sessions.create' => 'Create new sessions',
        'sessions.update' => 'Edit existing sessions',
        'sessions.delete' => 'Delete sessions',
        
        // Terms
        'terms.view' => 'View academic terms',
        'terms.create' => 'Create new terms',
        'terms.update' => 'Edit existing terms',
        'terms.delete' => 'Delete terms',
        
        // Classes
        'classes.view' => 'View classes',
        'classes.create' => 'Create new classes',
        'classes.update' => 'Edit existing classes',
        'classes.delete' => 'Delete classes',
        
        // Class Arms
        'class-arms.view' => 'View class arms',
        'class-arms.create' => 'Create new class arms',
        'class-arms.update' => 'Edit existing class arms',
        'class-arms.delete' => 'Delete class arms',
        
        // Class Sections
        'class-sections.view' => 'View class sections',
        'class-sections.create' => 'Create new class sections',
        'class-sections.update' => 'Edit existing class sections',
        'class-sections.delete' => 'Delete class sections',
        
        // Parents
        'parents.view' => 'View parent records',
        'parents.create' => 'Create new parent records',
        'parents.update' => 'Edit parent records',
        'parents.delete' => 'Delete parent records',
        
        // Students
        'students.view' => 'View student records',
        'students.create' => 'Create new student records',
        'students.update' => 'Edit student records',
        'students.delete' => 'Delete student records',
        'students.export' => 'Export student data',
        
        // Bulk Results
        'results.bulk.view' => 'View bulk results page',
        'results.bulk.generate' => 'Generate bulk results',
        'results.bulk.download' => 'Download bulk results',
        'results.bulk.print' => 'Print bulk results',
        'results.check' => 'Check individual results',
        
        // Skills & Ratings
        'skills.ratings.view' => 'View skill ratings',
        'skills.ratings.enter' => 'Enter skill ratings',
        'skills.ratings.update' => 'Update skill ratings',
        
        // Early Years Results
        'results.early-years.view' => 'View early years results',
        'results.early-years.generate' => 'Generate early years results',
        
        // Staff
        'staff.view' => 'View staff records',
        'staff.create' => 'Create new staff records',
        'staff.update' => 'Edit staff records',
        'staff.delete' => 'Delete staff records',
        'staff.roles.assign' => 'Assign roles to staff',
        'staff.roles.update' => 'Update staff role assignments',
        
        // Subjects
        'subjects.view' => 'View subjects',
        'subjects.create' => 'Create new subjects',
        'subjects.update' => 'Edit existing subjects',
        'subjects.delete' => 'Delete subjects',
        
        // Subject Assignments
        'subject.assignments.view' => 'View subject assignments',
        'subject.assignments.create' => 'Create subject assignments',
        'subject.assignments.update' => 'Update subject assignments',
        'subject.assignments.delete' => 'Delete subject assignments',
        
        // Teacher Assignments
        'teacher.assignments.view' => 'View teacher assignments',
        'teacher.assignments.create' => 'Create teacher assignments',
        'teacher.assignments.update' => 'Update teacher assignments',
        'teacher.assignments.delete' => 'Delete teacher assignments',
        
        // Class Teachers
        'class-teachers.view' => 'View class teacher assignments',
        'class-teachers.create' => 'Assign class teachers',
        'class-teachers.update' => 'Update class teacher assignments',
        'class-teachers.delete' => 'Remove class teacher assignments',
        
        // Assessment Components
        'assessment.components.view' => 'View assessment components',
        'assessment.components.create' => 'Create assessment components',
        'assessment.components.update' => 'Update assessment components',
        'assessment.components.delete' => 'Delete assessment components',
        'assessment.components.cbt-link' => 'Link assessment to CBT',
        
        // Assessment Structures
        'assessment.structures.view' => 'View assessment structures',
        'assessment.structures.create' => 'Create assessment structures',
        'assessment.structures.update' => 'Update assessment structures',
        'assessment.structures.delete' => 'Delete assessment structures',
        
        // CBT Links
        'assessment.cbt-link.view' => 'View CBT assessment links',
        'assessment.cbt-link.create' => 'Create CBT assessment links',
        'assessment.cbt-link.delete' => 'Delete CBT assessment links',
        
        // Grade Scales
        'assessment.grade-scales.view' => 'View grade scales',
        'assessment.grade-scales.create' => 'Create grade scales',
        'assessment.grade-scales.update' => 'Update grade scales',
        'assessment.grade-scales.delete' => 'Delete grade scales',
        'assessment.grade-scales.set-active' => 'Set active grade scale',
        
        // Result Pins
        'result.pin.view' => 'View result pins',
        'result.pin.create' => 'Create result pins',
        'result.pin.bulk-create' => 'Bulk create result pins',
        'result.pin.invalidate' => 'Invalidate result pins',
        'result.pin.export' => 'Export result pins',
        
        // Result Page Settings
        'settings.result-page.view' => 'View result page settings',
        'settings.result-page.update' => 'Update result page settings',
        
        // Results Entry
        'results.entry.view' => 'View results entry page',
        'results.entry.enter' => 'Enter student results',
        'results.entry.save' => 'Save entered results',
        
        // Skill Categories
        'skills.categories.view' => 'View skill categories',
        'skills.categories.create' => 'Create skill categories',
        'skills.categories.update' => 'Update skill categories',
        'skills.categories.delete' => 'Delete skill categories',
        
        // Skill Types
        'skills.types.view' => 'View skill types',
        'skills.types.create' => 'Create skill types',
        'skills.types.update' => 'Update skill types',
        'skills.types.delete' => 'Delete skill types',
        
        // Student Promotion
        'students.promotion.view' => 'View promotion page',
        'students.promotion.execute' => 'Execute student promotion',
        'students.promotion.bulk' => 'Bulk promote students',
        'students.promotion.reports.view' => 'View promotion reports',
        'students.promotion.reports.export' => 'Export promotion reports',
        
        // Academic Rollover
        'academic.rollover.view' => 'View academic rollover',
        'academic.rollover.execute' => 'Execute academic rollover',
        
        // Student Attendance
        'attendance.dashboard.view' => 'View attendance dashboard',
        'attendance.stats.view' => 'View attendance statistics',
        'attendance.student.view' => 'View student attendance',
        'attendance.student.mark' => 'Mark student attendance',
        'attendance.student.update' => 'Update student attendance',
        'attendance.student.delete' => 'Delete student attendance records',
        'attendance.student.export' => 'Export student attendance',
        'attendance.student.history' => 'View student attendance history',
        
        // Staff Attendance
        'attendance.staff.view' => 'View staff attendance',
        'attendance.staff.mark' => 'Mark staff attendance',
        'attendance.staff.update' => 'Update staff attendance',
        'attendance.staff.delete' => 'Delete staff attendance records',
        'attendance.staff.export' => 'Export staff attendance',
        
        // Bulk Student Upload
        'students.bulk-upload.view' => 'View bulk upload page',
        'students.bulk-upload.upload' => 'Upload bulk student file',
        'students.bulk-upload.preview' => 'Preview bulk upload',
        'students.bulk-upload.execute' => 'Execute bulk student upload',
        'students.bulk-upload.template' => 'Download bulk upload template',
        
        // Finance - Bank
        'finance.bank.view' => 'View bank details',
        'finance.bank.update' => 'Update bank details',
        
        // Finance - Fee Items
        'finance.fee-items.view' => 'View fee items',
        'finance.fee-items.create' => 'Create fee items',
        'finance.fee-items.update' => 'Update fee items',
        'finance.fee-items.delete' => 'Delete fee items',
        
        // Finance - Fee Structures
        'finance.fee-structures.view' => 'View fee structures',
        'finance.fee-structures.create' => 'Create fee structures',
        'finance.fee-structures.update' => 'Update fee structures',
        'finance.fee-structures.delete' => 'Delete fee structures',
        'finance.fee-structures.copy' => 'Copy fee structures',
        
        // RBAC - Roles
        'roles.view' => 'View roles',
        'roles.create' => 'Create new roles',
        'roles.update' => 'Update roles',
        'roles.delete' => 'Delete roles',
        'roles.permissions.assign' => 'Assign permissions to roles',
        
        // RBAC - User Roles
        'user-roles.view' => 'View user role assignments',
        'user-roles.assign' => 'Assign roles to users',
        'user-roles.remove' => 'Remove roles from users',
        
        // Staff Portal
        'staff.dashboard.view' => 'View staff dashboard',
        'staff.classes.view' => 'View assigned classes',
        'staff.subjects.view' => 'View assigned subjects',
        'staff.profile.view' => 'View staff profile',
        'staff.profile.update' => 'Update staff profile',
        
        // Student Portal
        'student.dashboard.view' => 'View student dashboard',
        'student.bio.view' => 'View student bio',
        'student.result.view' => 'View own results',
        'student.result.download' => 'Download own results',
        
        // CBT - Student
        'cbt.quizzes.view' => 'View available quizzes',
        'cbt.quizzes.take' => 'Take quizzes',
        'cbt.quizzes.submit' => 'Submit quiz answers',
        'cbt.history.view' => 'View quiz history',
        'cbt.results.view' => 'View quiz results',
        'cbt.results.review' => 'Review quiz answers',
        
        // CBT - Admin
        'cbt.admin.view' => 'View CBT admin panel',
        'cbt.admin.create' => 'Create quizzes',
        'cbt.admin.update' => 'Update quizzes',
        'cbt.admin.delete' => 'Delete quizzes',
        'cbt.admin.publish' => 'Publish quizzes',
        'cbt.admin.unpublish' => 'Unpublish quizzes',
        'cbt.admin.results.view' => 'View all quiz results',
        'cbt.admin.results.export' => 'Export quiz results',
        'cbt.admin.results.grade' => 'Grade quiz submissions',
        
        // CBT - Questions
        'cbt.questions.view' => 'View quiz questions',
        'cbt.questions.create' => 'Create quiz questions',
        'cbt.questions.update' => 'Update quiz questions',
        'cbt.questions.delete' => 'Delete quiz questions',
        'cbt.questions.reorder' => 'Reorder quiz questions',
        
        // CBT - Links
        'cbt.links.view' => 'View CBT links',
        'cbt.links.create' => 'Create CBT links',
        'cbt.links.delete' => 'Delete CBT links',
    ];

    /**
     * Get all permission names (keys only).
     *
     * @return array<int, string>
     */
    public static function getPermissionNames(): array
    {
        return array_keys(self::$permissions);
    }

    /**
     * Get permission description by name.
     */
    public static function getDescription(string $name): ?string
    {
        return self::$permissions[$name] ?? null;
    }

    /**
     * Seed permissions for a specific school.
     *
     * @param string $schoolId The school ID to seed permissions for
     * @param string|null $guardName Optional guard name (defaults to sanctum)
     * @return array{created: int, existing: int, total: int}
     */
    public static function seedForSchool(string $schoolId, ?string $guardName = null): array
    {
        $guardName = $guardName ?? config('permission.default_guard', 'sanctum');
        $created = 0;
        $existing = 0;

        foreach (self::$permissions as $name => $description) {
            $permission = Permission::query()->updateOrCreate(
                [
                    'name' => $name,
                    'guard_name' => $guardName,
                    'school_id' => $schoolId,
                ],
                [
                    'description' => $description,
                ],
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'created' => $created,
            'existing' => $existing,
            'total' => count(self::$permissions),
        ];
    }

    /**
     * Run the database seeds.
     * 
     * This will seed permissions for all schools.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guardName = config('permission.default_guard', 'sanctum');

        // Seed for all schools
        $schools = School::all();

        if ($schools->isEmpty()) {
            $this->command->warn("No schools found in the database. Skipping permission seeding.");
            return;
        }

        $totalCreated = 0;
        $totalExisting = 0;

        foreach ($schools as $school) {
            $result = self::seedForSchool($school->id, $guardName);
            $totalCreated += $result['created'];
            $totalExisting += $result['existing'];
            $this->command->info("  - {$school->name}: {$result['created']} created, {$result['existing']} existing");
        }

        $this->command->info("");
        $this->command->info("Frontend permissions seeded for {$schools->count()} school(s):");
        $this->command->info("  Total created: {$totalCreated}");
        $this->command->info("  Already existed: {$totalExisting}");
        $this->command->info("  Permissions per school: " . count(self::$permissions));
    }
}
