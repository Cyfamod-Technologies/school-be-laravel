<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $afterColumn = $this->resolveAnchorColumn();

        $addColumn = function (string $columnName, callable $definition) use (&$afterColumn): void {
            if (Schema::hasColumn('terms', $columnName)) {
                $afterColumn = $columnName;
                return;
            }

            Schema::table('terms', function (Blueprint $table) use ($definition, $afterColumn) {
                $column = $definition($table);

                if ($afterColumn !== null) {
                    $column->after($afterColumn);
                }
            });

            $afterColumn = $columnName;
        };

        // Payment tracking for original term invoice
        $addColumn('payment_status', fn (Blueprint $table) => $table->enum('payment_status', ['pending', 'invoiced', 'paid', 'partial'])->default('pending'));
        $addColumn('invoice_id', fn (Blueprint $table) => $table->uuid('invoice_id')->nullable());
        $addColumn('student_count_snapshot', fn (Blueprint $table) => $table->integer('student_count_snapshot')->nullable()->comment('Student count at term creation'));
        $addColumn('amount_due', fn (Blueprint $table) => $table->decimal('amount_due', 10, 2)->default(0)->comment('Original invoice amount'));
        $addColumn('amount_paid', fn (Blueprint $table) => $table->decimal('amount_paid', 10, 2)->default(0)->comment('Paid for original invoice'));
        $addColumn('payment_due_date', fn (Blueprint $table) => $table->date('payment_due_date')->nullable());

        // Mid-term additions tracking
        $addColumn('has_midterm_additions', fn (Blueprint $table) => $table->boolean('has_midterm_additions')->default(false));
        $addColumn('midterm_amount_due', fn (Blueprint $table) => $table->decimal('midterm_amount_due', 10, 2)->default(0)->comment('Fees from mid-term student admissions'));
        $addColumn('midterm_amount_paid', fn (Blueprint $table) => $table->decimal('midterm_amount_paid', 10, 2)->default(0));

        // Outstanding balance (calculated field)
        $addColumn('outstanding_balance', fn (Blueprint $table) => $table->decimal('outstanding_balance', 10, 2)->storedAs('(amount_due + midterm_amount_due - amount_paid - midterm_amount_paid)'));

        if (
            Schema::hasTable('invoices')
            && Schema::hasColumn('terms', 'invoice_id')
            && ! $this->hasForeignKey('terms', 'terms_invoice_id_foreign')
        ) {
            Schema::table('terms', function (Blueprint $table) {
                $table->foreign('invoice_id')
                    ->references('id')
                    ->on('invoices')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('terms', 'invoice_id') && $this->hasForeignKey('terms', 'terms_invoice_id_foreign')) {
            Schema::table('terms', function (Blueprint $table) {
                $table->dropForeign('terms_invoice_id_foreign');
            });
        }

        $columns = [];

        foreach ([
            'payment_status',
            'invoice_id',
            'student_count_snapshot',
            'amount_due',
            'amount_paid',
            'payment_due_date',
            'has_midterm_additions',
            'midterm_amount_due',
            'midterm_amount_paid',
            'outstanding_balance',
        ] as $column) {
            if (Schema::hasColumn('terms', $column)) {
                $columns[] = $column;
            }
        }

        if (! empty($columns)) {
            Schema::table('terms', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function resolveAnchorColumn(): ?string
    {
        foreach ([
            'description',
            'term_number',
            'updated_at',
            'created_at',
            'status',
            'end_date',
            'start_date',
            'slug',
            'name',
            'session_id',
            'school_id',
            'id',
        ] as $column) {
            if (Schema::hasColumn('terms', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function hasForeignKey(string $table, string $keyName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        $schema = Schema::getConnection()->getDatabaseName();

        $result = Schema::getConnection()->selectOne('
            SELECT 1
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
            LIMIT 1
        ', [$schema, $table, $keyName]);

        return $result !== null;
    }
};
