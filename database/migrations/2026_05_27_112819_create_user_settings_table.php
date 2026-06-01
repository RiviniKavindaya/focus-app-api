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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->boolean('theme')->default('true'); // true for dark, false for light
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('notification_enabled')->default(true);

            $table->string('focus_intensity')->default('deep');// 'light', 'deep', 'balanced'

            $table->boolean('auto_start_breaks')->default(false);
            $table->boolean('long_break_reminder')->default(true);

            $table->string('music_provider')->nullable();
            $table->integer('music_volume')->default(50);

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
