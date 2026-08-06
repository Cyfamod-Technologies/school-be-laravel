<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SetSchoolDomain extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schools:set-domain
                            {school : The school ID}
                            {domain : The custom domain to activate this school\'s public website on (e.g. wickedacademy.ng)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Set a school's custom domain, so its public website can go live on it. There's no self-service UI for this yet -- schools request Go Live, and this is how Cyfamod staff record the domain that was set up for them on Cloudflare.";

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $schoolId = $this->argument('school');
        $domain = strtolower(trim($this->argument('domain')));

        $school = School::find($schoolId);

        if (! $school) {
            $this->error("School with ID '{$schoolId}' not found.");

            return self::FAILURE;
        }

        try {
            $this->validateDomain($domain, $school->id);
        } catch (ValidationException $e) {
            $this->error(implode(' ', $e->validator->errors()->all()));

            return self::FAILURE;
        }

        $previousDomain = $school->custom_domain;
        $school->custom_domain = $domain;
        $school->save();

        if ($previousDomain) {
            $this->info("Updated {$school->name}'s domain: {$previousDomain} -> {$domain}");
        } else {
            $this->info("Set {$school->name}'s domain to {$domain}.");
        }

        return self::SUCCESS;
    }

    private function validateDomain(string $domain, string $schoolId): void
    {
        Validator::validate(
            ['domain' => $domain],
            [
                'domain' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                    Rule::unique('schools', 'custom_domain')->ignore($schoolId),
                ],
            ]
        );
    }
}
