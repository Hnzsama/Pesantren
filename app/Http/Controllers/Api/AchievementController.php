<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Achievement::query()->with('student:id,full_name');

        if ($request->has('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        $achievements = $query->latest('date_achieved')->paginate(10);

        return response()->json($achievements);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $achievement = Achievement::with('student:id,full_name')->findOrFail($id);
        return response()->json($achievement);
    }
}
