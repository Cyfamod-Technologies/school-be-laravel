<?php

namespace Database\Seeders;

use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ClassTeacher;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\SkillCategory;
use App\Models\SkillType;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\SubjectTeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Rbac\RbacService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class ComprehensiveSchoolSeeder extends Seeder
{
    private ?School $school = null;
    private array $sessions = [];
    private array $terms = [];
    private array $classes = [];
    private array $subjects = [];
    private array $teachers = [];
    private array $parents = [];
    private array $components = [];

    /**
     * Nigerian first names
     */
    private array $nigerianFirstNames = [
        // Male names
        'Chukwuemeka', 'Oluwaseun', 'Abdullahi', 'Tunde', 'Emeka', 'Chisom', 'Ifeanyi', 'Olumide',
        'Adebayo', 'Yusuf', 'Ibrahim', 'Olusegun', 'Chinedu', 'Aminu', 'Obinna', 'Kunle',
        'Olamide', 'Tochukwu', 'Hassan', 'Chiamaka', 'Damilola', 'Femi', 'Chidi', 'Usman',
        // Female names
        'Chioma', 'Blessing', 'Faith', 'Amara', 'Aisha', 'Ngozi', 'Nneka', 'Oluchi',
        'Fatima', 'Joy', 'Peace', 'Ada', 'Khadija', 'Funmi', 'Bukola', 'Adaeze',
        'Hauwa', 'Ebere', 'Folake', 'Zainab', 'Ifeoma', 'Bisola', 'Chiamaka', 'Halima',
        // Unisex
        'Chika', 'Ayo', 'Bisi', 'Tobi', 'Tayo', 'Wale', 'Kemi'
    ];

    /**
     * Nigerian last names
     */
    private array $nigerianLastNames = [
        'Okafor', 'Adeleke', 'Mohammed', 'Williams', 'Nwosu', 'Adeyemi', 'Ibrahim', 'Okonkwo',
        'Oluwole', 'Bello', 'Eze', 'Adebayo', 'Musa', 'Okeke', 'Afolabi', 'Usman',
        'Chukwu', 'Ogunleye', 'Yakubu', 'Nnamdi', 'Adedayo', 'Suleiman', 'Onyeka', 'Obi',
        'Adewale', 'Garba', 'Nnaji', 'Bakare', 'Aliyu', 'Okafor', 'Ojo', 'Abdullahi',
        'Chukwuemeka', 'Akinwale', 'Haruna', 'Ike', 'Ladipo', 'Balarabe', 'Nwankwo', 'Adeola'
    ];

    /**
     * Run the database seeds.
     *
     * This seeder is idempotent - it checks for existing data and skips creation if already present.
     * Safe to run multiple times without duplicating data.
     *
     * MULTI-TENANCY NOTICE:
     * - All data is scoped to a single school (subdomain: 'demo')
     * - School-specific data: sessions, terms, classes, subjects, teachers, students
     * - All queries check school_id to ensure proper isolation
     * - Safe to use in multi-school platform
     */
    public function run(): void
    {
        $this->command->info('=================================================');
        $this->command->info('  Demo International School Comprehensive Seeder');
        $this->command->info('=================================================');
        $this->command->info('');

        DB::beginTransaction();

        try {
            $this->createSchool();
            $this->createSessionsAndTerms();
            $this->createClasses();
            $this->createSubjects();
            $this->createAssessmentComponents();
            $this->createSkillTypes();
            $this->assignSubjectsToClasses();
            $this->createTeachers();
            $this->createParents();
            $this->createStudents();
            $this->assignTeachersToSubjects();

            // Update school with current session and term
            if (!empty($this->sessions) && !empty($this->terms)) {
                $this->school->update([
                    'current_session_id' => $this->sessions[0]->id,
                    'current_term_id' => $this->terms[0]->id,
                ]);
            }

            DB::commit();

            $this->command->info('');
            $this->command->info('=================================================');
            $this->command->info('✓ Seeding completed successfully!');
            $this->command->info('=================================================');
            $this->command->info("School: {$this->school->name}");
            $this->command->info("Login Email: demo@gmail.com");
            $this->command->info("Password: 12345678");
            $this->command->info('=================================================');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create school and admin user
     */
    private function createSchool(): void
    {
        $this->command->info('Creating school...');

        // Check if demo school already exists (by unique subdomain)
        $this->school = School::where('subdomain', 'demo')->first();

        if ($this->school) {
            $this->command->warn('Demo school (subdomain: demo) already exists. Using existing school...');
        } else {
            // Get the next available code_sequence
            $maxCodeSequence = School::max('code_sequence') ?? 0;
            $nextCodeSequence = $maxCodeSequence + 1;

            $this->school = School::create([
                'id' => (string) Str::uuid(),
                'name' => 'Demo International School',
                'slug' => 'demo-international-school',
                'subdomain' => 'demo',
                'acronym' => 'DIS',
                'address' => '123 Education Avenue, Ikeja, Lagos State, Nigeria',
                'email' => 'info@demointernational.edu.ng',
                'phone' => '+234-800-123-4567',
                'status' => 'active',
                'code_sequence' => $nextCodeSequence,
                'established_at' => Carbon::create(2010, 1, 1),
            ]);
        }

        // Setup permissions
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId($this->school->id);

        // Create or update admin user (scoped to this school)
        $admin = User::where('email', 'demo@gmail.com')
            ->where('school_id', $this->school->id)
            ->first();

        if (!$admin) {
            $admin = User::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'name' => 'School Administrator',
                'email' => 'demo@gmail.com',
                'password' => bcrypt('12345678'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            /** @var RbacService $rbac */
            $rbac = app(RbacService::class);
            $rbac->bootstrapForSchool($this->school, $admin);

            $this->command->info('✓ Admin user created');
        } else {
            $this->command->warn('Admin user already exists for this school');
        }

        $adminRole = \App\Models\Role::query()
            ->where('name', 'admin')
            ->where('school_id', $this->school->id)
            ->first();

        if ($adminRole && !$admin->hasRole($adminRole)) {
            $admin->assignRole($adminRole);
        }

        $registrar->setPermissionsTeamId(null);

        $this->command->info('✓ School setup completed');
    }

    /**
     * Create academic sessions and terms
     */
    private function createSessionsAndTerms(): void
    {
        $this->command->info('Creating sessions and terms...');

        $sessionYears = [
            ['name' => '2024/2025', 'start' => '2024-09-01', 'end' => '2025-08-31'],
            ['name' => '2025/2026', 'start' => '2025-09-01', 'end' => '2026-08-31'],
        ];

        foreach ($sessionYears as $sessionData) {
            // Check if session already exists
            $session = Session::where('school_id', $this->school->id)
                ->where('name', $sessionData['name'])
                ->first();

            if ($session) {
                $this->command->warn("Session {$sessionData['name']} already exists, skipping...");
                $this->sessions[] = $session;

                // Get existing terms for this session
                $existingTerms = Term::where('session_id', $session->id)->get();
                foreach ($existingTerms as $term) {
                    $this->terms[] = $term;
                }
                continue;
            }

            $session = Session::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'name' => $sessionData['name'],
                'slug' => Str::slug($sessionData['name']),
                'start_date' => Carbon::parse($sessionData['start']),
                'end_date' => Carbon::parse($sessionData['end']),
                'status' => 'active',
            ]);

            $this->sessions[] = $session;

            // Create 3 terms for this session
            $termDefinitions = [
                ['name' => '1st', 'months' => 4],
                ['name' => '2nd', 'months' => 4],
                ['name' => '3rd', 'months' => 4],
            ];

            $startDate = Carbon::parse($sessionData['start']);
            foreach ($termDefinitions as $termData) {
                $endDate = $startDate->copy()->addMonths($termData['months'])->subDay();

                $term = Term::create([
                    'id' => (string) Str::uuid(),
                    'school_id' => $this->school->id,
                    'session_id' => $session->id,
                    'name' => $termData['name'],
                    'slug' => Str::slug($termData['name'] . '-' . $session->name),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active',
                ]);

                $this->terms[] = $term;
                $startDate = $endDate->copy()->addDay();
            }
        }

        $this->command->info('✓ Created ' . count($this->sessions) . ' sessions and ' . count($this->terms) . ' terms');
    }

    /**
     * Create classes with arms and departments
     */
    private function createClasses(): void
    {
        $this->command->info('Creating classes...');

        // Define the proper order for classes
        $classStructure = [
            // Nursery - No arms
            ['name' => 'Nursery 1', 'arms' => []],
            ['name' => 'Nursery 2', 'arms' => []],
            ['name' => 'Nursery 3', 'arms' => []],

            // Primary - No arms
            ['name' => 'Primary 1', 'arms' => []],
            ['name' => 'Primary 2', 'arms' => []],
            ['name' => 'Primary 3', 'arms' => []],
            ['name' => 'Primary 4', 'arms' => []],
            ['name' => 'Primary 5', 'arms' => []],

            // JSS - With arms
            ['name' => 'JSS 1', 'arms' => ['A', 'B', 'C']],
            ['name' => 'JSS 2', 'arms' => ['A', 'B', 'C']],
            ['name' => 'JSS 3', 'arms' => ['A', 'B', 'C']],

            // SS - With arms and departments
            ['name' => 'SS 1', 'arms' => ['Science', 'Arts', 'Commercial']],
            ['name' => 'SS 2', 'arms' => ['Science', 'Arts', 'Commercial']],
            ['name' => 'SS 3', 'arms' => ['Science', 'Arts', 'Commercial']],
        ];

        // Check if classes already exist
        $existingClassCount = SchoolClass::where('school_id', $this->school->id)->count();
        if ($existingClassCount > 0) {
            $this->command->warn("Found {$existingClassCount} existing classes. Loading them in proper order...");
            $existingClasses = SchoolClass::where('school_id', $this->school->id)
                ->with('class_arms')
                ->orderBy('order', 'asc')
                ->get();

            // Load classes directly from database order
            foreach ($existingClasses as $class) {
                $this->classes[$class->name] = [
                    'class' => $class,
                    'arms' => $class->class_arms->all(),
                ];
            }

            $this->command->info("✓ Loaded {$existingClassCount} existing classes in proper order");
            return;
        }

        foreach ($classStructure as $index => $classData) {
            $class = SchoolClass::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'name' => $classData['name'],
                'slug' => Str::slug($classData['name']),
                'description' => $classData['name'],
                'order' => $index + 1, // Set order based on position in array (1-indexed)
            ]);

            $this->classes[$classData['name']] = [
                'class' => $class,
                'arms' => [],
            ];

            // Create arms if defined, or create a default arm for classes without arms
            if (!empty($classData['arms'])) {
                foreach ($classData['arms'] as $armName) {
                    $arm = ClassArm::create([
                        'id' => (string) Str::uuid(),
                        'school_class_id' => $class->id,
                        'name' => $armName,
                        'slug' => Str::slug($armName),
                        'description' => "{$class->name} {$armName}",
                    ]);

                    $this->classes[$classData['name']]['arms'][] = $arm;
                }
            } else {
                // Create a default arm for classes without traditional arms (Nursery, Primary)
                // This is required because class_arm_id is NOT NULL in the database
                $arm = ClassArm::create([
                    'id' => (string) Str::uuid(),
                    'school_class_id' => $class->id,
                    'name' => 'Main',
                    'slug' => 'main',
                    'description' => "{$class->name} Main",
                ]);

                $this->classes[$classData['name']]['arms'][] = $arm;
            }
        }

        $this->command->info('✓ Created ' . count($this->classes) . ' classes');
    }

    /**
     * Create subjects
     */
    private function createSubjects(): void
    {
        $this->command->info('Creating subjects...');

        // Check if subjects already exist
        $existingSubjectCount = Subject::where('school_id', $this->school->id)->count();
        if ($existingSubjectCount > 0) {
            $this->command->warn("Found {$existingSubjectCount} existing subjects. Loading them...");
            $existingSubjects = Subject::where('school_id', $this->school->id)->get();

            foreach ($existingSubjects as $subject) {
                // Try to match with our predefined levels (if needed for logic)
                $this->subjects[$subject->name] = [
                    'subject' => $subject,
                    'levels' => ['all'], // Default to all if loading existing
                ];
            }

            $this->command->info("✓ Loaded {$existingSubjectCount} existing subjects");
            return;
        }

        $subjectList = [
            // Core subjects (All levels)
            ['name' => 'Mathematics', 'code' => 'MATH', 'levels' => ['all']],
            ['name' => 'English Language', 'code' => 'ENG', 'levels' => ['all']],
            ['name' => 'Basic Science', 'code' => 'BSC', 'levels' => ['nursery', 'primary', 'jss']],
            ['name' => 'Social Studies', 'code' => 'SST', 'levels' => ['primary', 'jss']],
            ['name' => 'Civic Education', 'code' => 'CIV', 'levels' => ['all']],

            // Science subjects
            ['name' => 'Biology', 'code' => 'BIO', 'levels' => ['jss', 'ss-science', 'ss-arts']],
            ['name' => 'Physics', 'code' => 'PHY', 'levels' => ['ss-science']],
            ['name' => 'Chemistry', 'code' => 'CHEM', 'levels' => ['ss-science']],
            ['name' => 'Agricultural Science', 'code' => 'AGRIC', 'levels' => ['jss', 'ss-science']],

            // Arts subjects
            ['name' => 'Literature in English', 'code' => 'LIT', 'levels' => ['jss', 'ss-arts']],
            ['name' => 'Government', 'code' => 'GOVT', 'levels' => ['ss-arts', 'ss-commercial']],
            ['name' => 'Christian Religious Studies', 'code' => 'CRS', 'levels' => ['all']],
            ['name' => 'Islamic Religious Studies', 'code' => 'IRS', 'levels' => ['all']],
            ['name' => 'History', 'code' => 'HIST', 'levels' => ['jss', 'ss-arts']],
            ['name' => 'Geography', 'code' => 'GEO', 'levels' => ['jss', 'ss-arts', 'ss-science']],

            // Commercial subjects
            ['name' => 'Economics', 'code' => 'ECONS', 'levels' => ['ss-commercial', 'ss-arts']],
            ['name' => 'Commerce', 'code' => 'COMM', 'levels' => ['ss-commercial']],
            ['name' => 'Accounting', 'code' => 'ACCT', 'levels' => ['ss-commercial']],
            ['name' => 'Business Studies', 'code' => 'BUS', 'levels' => ['jss', 'ss-commercial']],

            // Other subjects
            ['name' => 'Computer Studies', 'code' => 'COMP', 'levels' => ['all']],
            ['name' => 'Physical Education', 'code' => 'PHE', 'levels' => ['all']],
            ['name' => 'Home Economics', 'code' => 'HOME', 'levels' => ['jss', 'ss-arts']],
            ['name' => 'Fine Arts', 'code' => 'ART', 'levels' => ['primary', 'jss', 'ss-arts']],
            ['name' => 'Music', 'code' => 'MUS', 'levels' => ['primary', 'jss', 'ss-arts']],
            ['name' => 'French', 'code' => 'FRE', 'levels' => ['jss', 'ss-arts']],
            ['name' => 'Yoruba', 'code' => 'YOR', 'levels' => ['primary', 'jss']],
            ['name' => 'Igbo', 'code' => 'IGB', 'levels' => ['primary', 'jss']],
            ['name' => 'Hausa', 'code' => 'HAU', 'levels' => ['primary', 'jss']],
        ];

        foreach ($subjectList as $subjectData) {
            $subject = Subject::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'name' => $subjectData['name'],
                'code' => $subjectData['code'],
                'description' => $subjectData['name'],
            ]);

            $this->subjects[$subjectData['name']] = [
                'subject' => $subject,
                'levels' => $subjectData['levels'],
            ];
        }

        $this->command->info('✓ Created ' . count($this->subjects) . ' subjects');
    }

    /**
     * Create assessment components
     */
    private function createAssessmentComponents(): void
    {
        $this->command->info('Creating assessment components...');

        // Check if assessment components already exist
        $existingComponentCount = AssessmentComponent::where('school_id', $this->school->id)->count();
        if ($existingComponentCount > 0) {
            $this->command->warn("Found {$existingComponentCount} existing assessment components. Skipping...");
            return;
        }

        $componentData = [
            ['name' => 'CA1', 'label' => 'Continuous Assessment 1', 'weight' => 10, 'order' => 1],
            ['name' => 'CA2', 'label' => 'Continuous Assessment 2', 'weight' => 10, 'order' => 2],
            ['name' => 'CA3', 'label' => 'Continuous Assessment 3', 'weight' => 10, 'order' => 3],
            ['name' => 'EXAM', 'label' => 'Examination', 'weight' => 70, 'order' => 4],
        ];

        // Create assessment components (not tied to specific subjects, sessions, or terms)
        foreach ($componentData as $data) {
            $component = AssessmentComponent::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'name' => $data['name'],
                'label' => $data['label'],
                'weight' => $data['weight'],
                'order' => $data['order'],
            ]);

            $this->components[] = $component;
        }

        $this->command->info('✓ Created ' . count($this->components) . ' assessment components');
    }

    /**
     * Create skill types
     */
    private function createSkillTypes(): void
    {
        $this->command->info('Creating skill types...');

        // Check for existing skill types
        $existingSkillCount = SkillType::where('school_id', $this->school->id)->count();
        if ($existingSkillCount > 0) {
            $this->command->warn("Found {$existingSkillCount} existing skill types. Skipping...");
            return;
        }

        // Create or find the "Skills and Behaviour" category
        $category = SkillCategory::where('school_id', $this->school->id)
            ->where('name', 'Skills and Behaviour')
            ->first();

        if (!$category) {
            $category = SkillCategory::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'name' => 'Skills and Behaviour',
                'description' => 'Behavioural and social skills assessment',
            ]);
            $this->command->info('✓ Created skill category: Skills and Behaviour');
        } else {
            $this->command->warn('Skill category "Skills and Behaviour" already exists. Using existing category...');
        }

        $skills = [
            'Attentiveness',
            'Communication Skills',
            'Handwriting',
            'Honesty',
            'Neatness',
            'Perseverance',
            'Politeness',
            'Promptness in completing work',
            'Punctuality',
            'Self Control',
        ];

        foreach ($skills as $skillName) {
            SkillType::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'skill_category_id' => $category->id,
                'name' => $skillName,
                'description' => $skillName,
                'weight' => 10,
            ]);
        }

        $this->command->info('✓ Created ' . count($skills) . ' skill types');
    }

    /**
     * Assign subjects to classes
     */
    private function assignSubjectsToClasses(): void
    {
        $this->command->info('Assigning subjects to classes...');

        // Check for existing subject assignments for this school
        $existingAssignmentCount = SubjectAssignment::whereIn(
            'school_class_id',
            array_map(fn($classData) => $classData['class']->id, $this->classes)
        )->count();

        if ($existingAssignmentCount > 0) {
            $this->command->warn("Found {$existingAssignmentCount} existing subject assignments. Skipping...");
            return;
        }

        $assignmentCount = 0;

        foreach ($this->classes as $className => $classData) {
            $class = $classData['class'];
            $arms = $classData['arms'];

            // Determine class level
            $level = $this->getClassLevel($className);

            foreach ($this->subjects as $subjectInfo) {
                $subject = $subjectInfo['subject'];
                $levels = $subjectInfo['levels'];

                // Check if subject applies to this class level
                if (!$this->subjectAppliesToLevel($levels, $level, $className)) {
                    continue;
                }

                // If class has no arms, assign to class only
                if (empty($arms)) {
                    SubjectAssignment::create([
                        'id' => (string) Str::uuid(),
                        'subject_id' => $subject->id,
                        'school_class_id' => $class->id,
                        'class_arm_id' => null,
                        'class_section_id' => null,
                    ]);
                    $assignmentCount++;
                } else {
                    // Assign to each arm
                    foreach ($arms as $arm) {
                        // For SS classes, check department-specific subjects
                        if (str_starts_with($className, 'SS')) {
                            if ($this->subjectAppliesToDepartment($subject->name, $arm->name)) {
                                SubjectAssignment::create([
                                    'id' => (string) Str::uuid(),
                                    'subject_id' => $subject->id,
                                    'school_class_id' => $class->id,
                                    'class_arm_id' => $arm->id,
                                    'class_section_id' => null,
                                ]);
                                $assignmentCount++;
                            }
                        } else {
                            // For JSS, assign to all arms
                            SubjectAssignment::create([
                                'id' => (string) Str::uuid(),
                                'subject_id' => $subject->id,
                                'school_class_id' => $class->id,
                                'class_arm_id' => $arm->id,
                                'class_section_id' => null,
                            ]);
                            $assignmentCount++;
                        }
                    }
                }
            }
        }

        $this->command->info("✓ Created {$assignmentCount} subject assignments");
    }

    /**
     * Create teachers
     */
    private function createTeachers(): void
    {
        $this->command->info('Creating teachers...');

        // Check for existing teachers
        $existingTeacherCount = Staff::where('school_id', $this->school->id)
            ->where('role', 'Teacher')
            ->count();

        if ($existingTeacherCount >= 10) {
            $this->command->warn("Found {$existingTeacherCount} existing teachers. Loading them...");

            $existingStaff = Staff::where('school_id', $this->school->id)
                ->where('role', 'Teacher')
                ->with('user')
                ->take(10)
                ->get();

            foreach ($existingStaff as $staff) {
                $this->teachers[] = ['user' => $staff->user, 'staff' => $staff];
            }

            $this->command->info("✓ Loaded {$existingTeacherCount} existing teachers");
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->school->id);

        $teacherRole = \App\Models\Role::query()
            ->where('name', 'teacher')
            ->where('school_id', $this->school->id)
            ->first();

        for ($i = 1; $i <= 10; $i++) {
            $gender = $i % 2 === 0 ? 'Male' : 'Female';
            $firstName = $this->nigerianFirstNames[array_rand($this->nigerianFirstNames)];
            $lastName = $this->nigerianLastNames[array_rand($this->nigerianLastNames)];
            $fullName = "{$firstName} {$lastName}";
            $email = strtolower(Str::slug($firstName . '-' . $lastName)) . '@demointernational.edu.ng';

            // Check if user already exists
            $user = User::where('email', $email)->first();
            if ($user) {
                continue;
            }

            $user = User::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'name' => $fullName,
                'email' => $email,
                'password' => bcrypt('password'),
                'role' => 'teacher',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            if ($teacherRole) {
                $user->assignRole($teacherRole);
            }

            $staff = Staff::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => '+234-' . rand(700, 999) . '-' . rand(100, 999) . '-' . rand(1000, 9999),
                'role' => 'Teacher',
                'gender' => $gender,
                'employment_start_date' => Carbon::now()->subYears(rand(1, 5)),
                'qualifications' => 'B.Ed., M.Ed.',
            ]);

            $this->teachers[] = ['user' => $user, 'staff' => $staff];
        }

        $registrar->setPermissionsTeamId(null);

        $this->command->info('✓ Created ' . count($this->teachers) . ' teachers');
    }

    /**
     * Create parents
     */
    private function createParents(): void
    {
        $this->command->info('Creating 20 parents...');

        // Check for existing parents
        $existingParentCount = SchoolParent::where('school_id', $this->school->id)->count();
        if ($existingParentCount >= 20) {
            $this->command->warn("Found {$existingParentCount} existing parents. Loading them...");

            $existingParents = SchoolParent::where('school_id', $this->school->id)
                ->with('user')
                ->take(20)
                ->get();

            foreach ($existingParents as $parent) {
                $this->parents[] = $parent;
            }

            $this->command->info("✓ Loaded {$existingParentCount} existing parents");
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->school->id);

        $parentRole = \App\Models\Role::query()
            ->where('name', 'parent')
            ->where('school_id', $this->school->id)
            ->first();

        $nigerianOccupations = [
            'Engineer', 'Doctor', 'Lawyer', 'Teacher', 'Accountant', 'Businessman',
            'Civil Servant', 'Nurse', 'Pharmacist', 'Architect', 'Banker', 'Trader',
            'Contractor', 'Lecturer', 'IT Professional', 'Sales Manager'
        ];

        $nigerianStates = [
            'Lagos', 'Oyo', 'Kano', 'Rivers', 'Enugu', 'Kaduna', 'Ogun',
            'Delta', 'Anambra', 'Abuja', 'Imo', 'Kwara', 'Edo', 'Osun'
        ];

        for ($i = 1; $i <= 20; $i++) {
            $gender = $i % 2 === 0 ? 'Male' : 'Female';
            $firstName = $this->nigerianFirstNames[array_rand($this->nigerianFirstNames)];
            $lastName = $this->nigerianLastNames[array_rand($this->nigerianLastNames)];
            $fullName = "{$firstName} {$lastName}";
            $email = strtolower(Str::slug($firstName . '-' . $lastName)) . '-parent@demointernational.edu.ng';

            // Check if user already exists
            $user = User::where('email', $email)->first();
            if ($user) {
                continue;
            }

            $user = User::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'name' => $fullName,
                'email' => $email,
                'password' => bcrypt('password'),
                'role' => 'parent',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            if ($parentRole) {
                $user->assignRole($parentRole);
            }

            $parent = SchoolParent::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => '+234-' . rand(700, 999) . '-' . rand(100, 999) . '-' . rand(1000, 9999),
                'address' => rand(1, 100) . ' ' . ['Allen Avenue', 'Victoria Island', 'Lekki', 'Ikeja GRA', 'Surulere'][array_rand(['Allen Avenue', 'Victoria Island', 'Lekki', 'Ikeja GRA', 'Surulere'])] . ', Lagos State',
                'occupation' => $nigerianOccupations[array_rand($nigerianOccupations)],
                'nationality' => 'Nigerian',
                'state_of_origin' => $nigerianStates[array_rand($nigerianStates)],
            ]);

            $this->parents[] = $parent;
        }

        $registrar->setPermissionsTeamId(null);

        $this->command->info('✓ Created ' . count($this->parents) . ' parents');
    }

    /**
     * Create students
     */
    private function createStudents(): void
    {
        $this->command->info('Creating 200 students...');

        // Check for existing students
        $existingStudentCount = Student::where('school_id', $this->school->id)->count();
        if ($existingStudentCount >= 200) {
            $this->command->warn("Found {$existingStudentCount} existing students. Skipping student creation...");
            return;
        }

        $studentCount = $existingStudentCount;
        $targetTotal = 200;
        $classKeys = array_keys($this->classes);

        while ($studentCount < $targetTotal) {
            foreach ($classKeys as $className) {
                if ($studentCount >= $targetTotal) {
                    break;
                }

                $classData = $this->classes[$className];
                $class = $classData['class'];
                $arms = $classData['arms'];

                $firstName = $this->nigerianFirstNames[array_rand($this->nigerianFirstNames)];
                $lastName = $this->nigerianLastNames[array_rand($this->nigerianLastNames)];
                $gender = rand(0, 1) === 0 ? 'Male' : 'Female';

                // Select arm if available
                $armId = null;
                if (!empty($arms)) {
                    $arm = $arms[array_rand($arms)];
                    $armId = $arm->id;
                }

                // Generate admission number
                $admissionNo = Student::generateAdmissionNumber($this->school, $this->sessions[0]);

                // Randomly assign a parent (60% chance of having a parent)
                // Each parent can have multiple children (siblings)
                $parentId = null;
                if (!empty($this->parents) && rand(1, 100) <= 60) {
                    $parent = $this->parents[array_rand($this->parents)];
                    $parentId = $parent->id;
                }

                Student::create([
                    'id' => (string) Str::uuid(),
                    'school_id' => $this->school->id,
                    'admission_no' => $admissionNo,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => $gender,
                    'date_of_birth' => Carbon::now()->subYears(rand(5, 18))->subMonths(rand(0, 11)),
                    'admission_date' => Carbon::now()->subMonths(rand(1, 24)),
                    'current_session_id' => $this->sessions[0]->id,
                    'current_term_id' => $this->terms[0]->id,
                    'school_class_id' => $class->id,
                    'class_arm_id' => $armId,
                    'parent_id' => $parentId,
                    'status' => 'active',
                    'nationality' => 'Nigerian',
                    'state_of_origin' => ['Lagos', 'Oyo', 'Kano', 'Rivers', 'Enugu'][array_rand(['Lagos', 'Oyo', 'Kano', 'Rivers', 'Enugu'])],
                ]);

                $studentCount++;
            }
        }

        $this->command->info("✓ Created {$studentCount} students");
    }

    /**
     * Assign teachers to subjects and classes
     */
    private function assignTeachersToSubjects(): void
    {
        $this->command->info('Assigning teachers to subjects and classes...');

        // Check for existing teacher assignments
        $existingTeacherAssignments = SubjectTeacherAssignment::whereHas('staff', function ($query) {
            $query->where('school_id', $this->school->id);
        })->count();

        $existingClassTeachers = ClassTeacher::whereHas('staff', function ($query) {
            $query->where('school_id', $this->school->id);
        })->count();

        if ($existingTeacherAssignments > 0 || $existingClassTeachers > 0) {
            $this->command->warn("Found {$existingTeacherAssignments} existing subject-teacher assignments and {$existingClassTeachers} class teachers. Skipping...");
            return;
        }

        $assignmentCount = 0;
        $classTeacherCount = 0;

        // Get all subject assignments grouped by class
        $subjectAssignments = SubjectAssignment::where('school_class_id', '!=', null)
            ->get()
            ->groupBy('school_class_id');

        $teacherIndex = 0;
        $totalTeachers = count($this->teachers);

        foreach ($subjectAssignments as $classId => $assignments) {
            // Assign some subjects to teachers
            $shuffled = $assignments->shuffle()->take(rand(3, 6));

            foreach ($shuffled as $assignment) {
                $teacher = $this->teachers[$teacherIndex % $totalTeachers];

                SubjectTeacherAssignment::create([
                    'id' => (string) Str::uuid(),
                    'staff_id' => $teacher['staff']->id,
                    'session_id' => $this->sessions[0]->id,
                    'term_id' => $this->terms[0]->id,
                    'subject_id' => $assignment->subject_id,
                    'school_class_id' => $assignment->school_class_id,
                    'class_arm_id' => $assignment->class_arm_id,
                    'class_section_id' => $assignment->class_section_id,
                ]);

                $assignmentCount++;
                $teacherIndex++;
            }

            // Assign one teacher as class teacher
            $classTeacher = $this->teachers[$teacherIndex % $totalTeachers];

            ClassTeacher::create([
                'id' => (string) Str::uuid(),
                'staff_id' => $classTeacher['staff']->id,
                'session_id' => $this->sessions[0]->id,
                'term_id' => $this->terms[0]->id,
                'school_class_id' => $classId,
                'class_arm_id' => $assignments->first()->class_arm_id,
                'class_section_id' => null,
            ]);

            $classTeacherCount++;
            $teacherIndex++;
        }

        $this->command->info("✓ Created {$assignmentCount} subject-teacher assignments");
        $this->command->info("✓ Assigned {$classTeacherCount} class teachers");
    }

    /**
     * Determine class level from class name
     */
    private function getClassLevel(string $className): string
    {
        if (str_contains($className, 'Nursery')) {
            return 'nursery';
        }
        if (str_contains($className, 'Primary')) {
            return 'primary';
        }
        if (str_contains($className, 'JSS')) {
            return 'jss';
        }
        if (str_contains($className, 'SS')) {
            return 'ss';
        }
        return 'unknown';
    }

    /**
     * Check if subject applies to level
     */
    private function subjectAppliesToLevel(array $levels, string $classLevel, string $className): bool
    {
        if (in_array('all', $levels)) {
            return true;
        }

        if (in_array($classLevel, $levels)) {
            return true;
        }

        // Check for department-specific levels
        if (str_starts_with($className, 'SS')) {
            foreach ($this->classes[$className]['arms'] as $arm) {
                $departmentLevel = 'ss-' . strtolower($arm->name);
                if (in_array($departmentLevel, $levels)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if subject applies to department
     */
    private function subjectAppliesToDepartment(string $subjectName, string $department): bool
    {
        $departmentSubjects = [
            'Science' => [
                'Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology',
                'Further Mathematics', 'Computer Studies', 'Agricultural Science', 'Geography',
                'Civic Education', 'Physical Education', 'Christian Religious Studies', 'Islamic Religious Studies'
            ],
            'Arts' => [
                'Mathematics', 'English Language', 'Literature in English', 'Government',
                'History', 'Geography', 'Economics', 'Biology', 'Civic Education',
                'Fine Arts', 'Music', 'French', 'Christian Religious Studies', 'Islamic Religious Studies',
                'Physical Education', 'Computer Studies'
            ],
            'Commercial' => [
                'Mathematics', 'English Language', 'Economics', 'Commerce', 'Accounting',
                'Business Studies', 'Government', 'Civic Education', 'Computer Studies',
                'Christian Religious Studies', 'Islamic Religious Studies', 'Physical Education'
            ],
        ];

        return in_array($subjectName, $departmentSubjects[$department] ?? []);
    }
}
