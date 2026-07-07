<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_pins', function (Blueprint $table) {
            if (! Schema::hasColumn('result_pins', 'session_id')) {
                $table->uuid('session_id')->nullable()->after('student_id');
            }

            if (! Schema::hasColumn('result_pins', 'term_id')) {
                $table->uuid('term_id')->nullable()->after('session_id');
            }

            if (! Schema::hasColumn('result_pins', 'pin_code')) {
                $table->string('pin_code', 64)->after('term_id');
            } else {
                $table->string('pin_code', 64)->change();
            }

            if (! Schema::hasColumn('result_pins', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('pin_code');
            }

            if (! Schema::hasColumn('result_pins', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('result_pins', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('expires_at');
            }
        });

        if (Schema::hasColumn('result_pins', 'expiry_date')) {
            Schema::table('result_pins', function (Blueprint $table) {
                $table->dropColumn('expiry_date');
            });
        }

        DB::statement("ALTER TABLE result_pins MODIFY status VARCHAR(32) NOT NULL DEFAULT 'active'");
        DB::statement("UPDATE result_pins SET status = 'active' WHERE status NOT IN ('active', 'revoked')");

        Schema::table('result_pins', function (Blueprint $table) {
            if (! Schema::hasColumn('result_pins', 'session_id')) {
                return;
            }

            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            if (! Schema::hasColumn('result_pins', 'term_id')) {
                return;
            }

            if (! Schema::hasColumn('result_pins', 'pin_code')) {
                return;
            }

            $table->unique(['student_id', 'session_id', 'term_id'], 'result_pins_unique_student_term');
            $table->unique('pin_code');
        });
    }

    public function down(): void
    {
        Schema::table('result_pins', function (Blueprint $table) {
            if (Schema::hasColumn('result_pins', 'pin_code')) {
                $table->dropUnique('result_pins_pin_code_unique');
            }
            if (Schema::hasColumn('result_pins', 'session_id') && Schema::hasColumn('result_pins', 'term_id')) {
                $table->dropUnique('result_pins_unique_student_term');
            }
            if (Schema::hasColumn('result_pins', 'session_id')) {
                try {
                    $table->dropForeign(['session_id']);
                } catch (\Exception $e) {
                    // ignore if constraint doesn't exist
                }
            }
            if (Schema::hasColumn('result_pins', 'term_id')) {
                $table->dropForeign(['term_id']);
            }
            if (Schema::hasColumn('result_pins', 'created_by')) {
                $table->dropForeign(['created_by']);
            }
        });

        Schema::table('result_pins', function (Blueprint $table) {
            if (Schema::hasColumn('result_pins', 'revoked_at')) {
                $table->dropColumn('revoked_at');
            }
            if (Schema::hasColumn('result_pins', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('result_pins', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('result_pins', 'term_id')) {
                $table->dropColumn('term_id');
            }
            if (Schema::hasColumn('result_pins', 'session_id')) {
                $table->dropColumn('session_id');
            }
        });

        DB::statement("ALTER TABLE result_pins MODIFY status ENUM('unused','used','expired') NOT NULL DEFAULT 'unused'");

        Schema::table('result_pins', function (Blueprint $table) {
            if (! Schema::hasColumn('result_pins', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('status');
            }
        });
    }
};
