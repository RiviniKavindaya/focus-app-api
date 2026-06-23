<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * GET /api/settings
     * Get user settings
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $settings = UserSetting::where('user_id', $userId)->first();

        if (!$settings) {
            $settings = UserSetting::create(['user_id' => $userId]);
        }

        return response()->json([
            'preferred_sprint_duration' => $settings->preferred_sprint_duration,
            'short_break_duration' => $settings->short_break_duration,
            'long_break_duration' => $settings->long_break_duration,
            'sprints_before_long_break' => $settings->sprints_before_long_break,
            'daily_target_minutes' => $settings->daily_target_minutes,
            'weekly_target_minutes' => $settings->weekly_target_minutes,
            'default_ambient_sound' => $settings->default_ambient_sound,
            'default_ambient_volume' => $settings->default_ambient_volume,
            'sound_effects_enabled' => $settings->sound_effects_enabled,
            'theme' => $settings->theme,
        ]);
    }

    /**
     * POST /api/settings
     * Update user settings
     */
    public function store(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $data = $request->validate([
            'preferred_sprint_duration' => 'sometimes|integer|min:5|max:60',
            'short_break_duration' => 'sometimes|integer|min:1|max:30',
            'long_break_duration' => 'sometimes|integer|min:5|max:60',
            'sprints_before_long_break' => 'sometimes|integer|min:2|max:10',
            'daily_target_minutes' => 'sometimes|integer|min:30|max:480',
            'weekly_target_minutes' => 'sometimes|integer|min:100|max:2400',
            'default_ambient_sound' => 'sometimes|string|in:none,rain,birds,noise,lofi',
            'default_ambient_volume' => 'sometimes|integer|min:0|max:100',
            'sound_effects_enabled' => 'sometimes|boolean',
            'theme' => 'sometimes|string|in:dark,light',
        ]);

        $settings = UserSetting::firstOrCreate(
            ['user_id' => $userId]
        );

        $settings->update($data);

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => $settings,
        ]);
    }

    /**
     * POST /api/settings/reset
     * Reset all settings to defaults
     */
    public function reset(): JsonResponse
    {
        $userId = Auth::id();

        $settings = UserSetting::firstOrCreate(['user_id' => $userId]);

        $settings->update([
            'preferred_sprint_duration' => 25,
            'short_break_duration' => 5,
            'long_break_duration' => 15,
            'sprints_before_long_break' => 4,
            'daily_target_minutes' => 120,
            'weekly_target_minutes' => 600,
            'default_ambient_sound' => 'none',
            'default_ambient_volume' => 50,
            'sound_effects_enabled' => true,
            'theme' => 'dark',
        ]);

        return response()->json([
            'message' => 'Settings reset to defaults',
            'data' => $settings,
        ]);
    }
}
