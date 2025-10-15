<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class FixStudentForeignKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:fix-foreign-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix invalid UUID foreign keys in students table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing invalid student foreign keys...');
        
        try {
            Student::fixLegacyForeignKeys();
            $this->info('✓ Successfully fixed student foreign keys.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to fix student foreign keys: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
