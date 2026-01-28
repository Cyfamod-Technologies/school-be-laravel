<?php

namespace App\Console\Commands;

use Database\Seeders\FrontendPermissionSeeder;
use Illuminate\Console\Command;
use App\Models\School;

class SeedSchoolPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:seed 
                            {school? : The school ID to seed permissions for (optional, seeds all schools if not provided)}
                            {--guard= : The guard name to use (defaults to sanctum)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed frontend permissions for a specific school or all schools';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $schoolId = $this->argument('school');
        $guardName = $this->option('guard') ?? config('permission.default_guard', 'sanctum');

        if ($schoolId) {
            // Seed for a specific school
            $school = School::find($schoolId);
            
            if (!$school) {
                $this->error("School with ID '{$schoolId}' not found.");
                return self::FAILURE;
            }

            $this->info("Seeding permissions for school: {$school->name}");
            $result = FrontendPermissionSeeder::seedForSchool($schoolId, $guardName);
            
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Created', $result['created']],
                    ['Already Existed', $result['existing']],
                    ['Total Catalog Size', $result['total']],
                ]
            );

            $this->info('✓ Permissions seeded successfully!');
        } else {
            // Seed for all schools
            $schools = School::all();

            if ($schools->isEmpty()) {
                $this->warn('No schools found in the database.');
                return self::SUCCESS;
            }

            $this->info("Seeding permissions for {$schools->count()} school(s)...");
            $this->newLine();

            $totalCreated = 0;
            $totalExisting = 0;

            $progressBar = $this->output->createProgressBar($schools->count());
            $progressBar->start();

            foreach ($schools as $school) {
                $result = FrontendPermissionSeeder::seedForSchool($school->id, $guardName);
                $totalCreated += $result['created'];
                $totalExisting += $result['existing'];
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Schools Processed', $schools->count()],
                    ['Total Created', $totalCreated],
                    ['Already Existed', $totalExisting],
                    ['Permissions per School', count(FrontendPermissionSeeder::$permissions)],
                ]
            );

            $this->info('✓ All permissions seeded successfully!');
        }

        return self::SUCCESS;
    }
}
