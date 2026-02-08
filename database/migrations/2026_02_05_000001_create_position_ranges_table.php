<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('position_ranges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grading_scale_id');
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->foreign('grading_scale_id')->references('id')->on('grading_scales')->onDelete('cascade');
            $table->unique(['grading_scale_id', 'position']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('position_ranges');
    }
};
