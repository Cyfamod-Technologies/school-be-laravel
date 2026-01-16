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
		Schema::table('quizzes', function (Blueprint $table) {
			$table->boolean('show_score')->default(true)->after('show_answers');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('quizzes', function (Blueprint $table) {
			$table->dropColumn('show_score');
		});
	}
};
