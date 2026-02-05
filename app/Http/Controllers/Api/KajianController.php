<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoLecture;
use Illuminate\Http\Request;

class KajianController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = VideoLecture::query();

        if ($request->has('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        $kajian = $query->latest('published_at')->paginate(10);

        return response()->json($kajian);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $kajian = VideoLecture::findOrFail($id);

        return response()->json($kajian);
    }
}
