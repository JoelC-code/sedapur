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
        Schema::create('fridge_scans', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('processing');
            $table->string('image_path')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->uuid('user_id')->index();               
            $table->unsignedBigInteger('household_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fridge_scans');
    }
};
