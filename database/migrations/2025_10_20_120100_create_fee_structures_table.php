<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('class_id');
            $table->uuid('session_id');
            $table->uuid('term_id');
            $table->uuid('fee_item_id');
            $table->decimal('amount', 12, 2); // Amount for this specific fee item
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
            $table->foreign('fee_item_id')->references('id')->on('fee_items')->onDelete('cascade');
            
            // Prevent duplicate structures for the same class/session/term/fee_item combination
            $table->unique(['class_id', 'session_id', 'term_id', 'fee_item_id'], 'unique_fee_structure');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fee_structures');
    }
};
