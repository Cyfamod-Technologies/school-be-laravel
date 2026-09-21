<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Which of the two published apps, and which store listing.
            // Staff and Student ship independently, and each platform keeps
            // its own build-number sequence, so all four combinations are
            // tracked separately.
            $table->string('app', 20);
            $table->string('platform', 20);
            // Android versionCode / iOS CFBundleVersion of the newest build
            // published to that listing. Not a MAJOR.MINOR.PATCH name: the
            // mobile force-update check compares build numbers, because the
            // version name is not guaranteed to move between releases.
            $table->unsignedInteger('build');
            $table->timestamps();

            $table->unique(['app', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
