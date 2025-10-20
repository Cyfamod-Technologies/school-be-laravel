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
        Schema::create('fee_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('name'); // e.g., Tuition, Uniform, PTA dues
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // e.g., Academic, Administrative, Extra-curricular
            $table->decimal('default_amount', 12, 2)->default(0); // Default amount for this item
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            
            // Ensure unique fee item names per school
            $table->unique(['school_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fee_items');
    }
};
