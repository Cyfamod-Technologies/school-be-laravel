<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // The full, arbitrary domain a school brings themselves
            // (e.g. "hill-top.com.ng") -- separate from `subdomain`, which
            // is a slug-like identifier, not necessarily a real domain.
            $table->string('custom_domain')->nullable()->unique()->after('subdomain');

            // Whether Cyfamod has approved this school's public website to
            // go live on their own domain. Separate from SchoolWebsite's
            // `status` (draft/published) -- a school can be fully
            // Published while still not Activated, until this flips.
            $table->boolean('activated')->default(false)->after('custom_domain');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['custom_domain', 'activated']);
        });
    }
};
