<?php

namespace App\Http\Controllers\Api;

use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class NewsController extends Controller
{
    /**
     * Display a listing of news.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = News::query()->with(['author:id,name', 'school:id,name']);

        // Filter by school if provided
        if ($request->has('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        // Filter by target role if provided
        if ($request->has('target_role')) {
            $query->where('target_role', $request->target_role);
        }

        // Only published news by default
        if (!$request->has('include_unpublished') || !$request->include_unpublished) {
            $query->where('is_published', true);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $news = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $news,
        ]);
    }

    /**
     * Display the specified news.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $news = News::with(['author:id,name', 'school:id,name'])->find($id);

        if (!$news) {
            return response()->json([
                'success' => false,
                'message' => 'News not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $news,
        ]);
    }

    /**
     * Store a newly created news.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:schools,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'images' => 'nullable|array',
            'images.*' => 'string', // URLs or base64
            'target_role' => 'required|string|in:admin,guardian,student,all',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['author_id'] = auth()->id();

        $news = News::create($data);

        return response()->json([
            'success' => true,
            'message' => 'News created successfully',
            'data' => $news->load(['author:id,name', 'school:id,name']),
        ], 201);
    }

    /**
     * Update the specified news.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json([
                'success' => false,
                'message' => 'News not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:schools,id',
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'target_role' => 'sometimes|required|string|in:admin,guardian,student,all',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $news->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'News updated successfully',
            'data' => $news->fresh(['author:id,name', 'school:id,name']),
        ]);
    }

    /**
     * Remove the specified news.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json([
                'success' => false,
                'message' => 'News not found',
            ], 404);
        }

        $news->delete();

        return response()->json([
            'success' => true,
            'message' => 'News deleted successfully',
        ]);
    }
}
