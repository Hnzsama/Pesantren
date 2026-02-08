<?php

namespace App\Http\Controllers\Api;

use App\Models\SowanSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SowanScheduleController extends Controller
{
    /**
     * Display a listing of sowan schedules.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = SowanSchedule::query();

        // Filter by school if provided
        if ($request->has('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'request_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $schedules = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    /**
     * Store a newly created sowan schedule.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:schools,id',
            'guest_name' => 'required|string|max:255',
            'request_date' => 'required|date',
            'topic' => 'required|string',
            'status' => 'nullable|string|in:pending,confirmed,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $schedule = SowanSchedule::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Sowan schedule created successfully',
            'data' => $schedule,
        ], 201);
    }

    /**
     * Display the specified sowan schedule.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $schedule = SowanSchedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Sowan schedule not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    /**
     * Update the specified sowan schedule.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $schedule = SowanSchedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Sowan schedule not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:schools,id',
            'guest_name' => 'sometimes|required|string|max:255',
            'request_date' => 'sometimes|required|date',
            'topic' => 'sometimes|required|string',
            'status' => 'sometimes|required|string|in:pending,confirmed,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $schedule->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Sowan schedule updated successfully',
            'data' => $schedule->fresh(),
        ]);
    }

    /**
     * Remove the specified sowan schedule from storage.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $schedule = SowanSchedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Sowan schedule not found',
            ], 404);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sowan schedule deleted successfully',
        ]);
    }
}
