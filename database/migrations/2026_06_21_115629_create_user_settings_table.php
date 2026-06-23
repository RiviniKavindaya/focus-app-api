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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('preferred_sprint_duration')->default(25);
            $table->unsignedSmallInteger('short_break_duration')->default(5);
            $table->unsignedSmallInteger('long_break_duration')->default(15);
            $table->unsignedSmallInteger('sprints_before_long_break')->default(4);
            $table->unsignedSmallInteger('daily_target_minutes')->default(120);
            $table->unsignedSmallInteger('weekly_target_minutes')->default(600);
            $table->string('default_ambient_sound')->default('none');
            $table->unsignedTinyInteger('default_ambient_volume')->default(50);
            $table->boolean('sound_effects_enabled')->default(true);
            $table->string('theme')->default('dark');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
