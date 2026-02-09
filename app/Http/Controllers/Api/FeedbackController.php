<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback; // Pastikan Model Feedback sudah dibuat
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * Display a history of feedback sent by the authenticated user.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Ambil feedback hanya milik user yang sedang login
        $feedbacks = Feedback::where('user_id', $user->id)
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $feedbacks
        ]);
    }

    /**
     * Store a newly created feedback in storage.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Ambil School ID dari Header atau User
        $school_id = $request->header('X-School-Id') ?? $user?->school_id;

        if (!$school_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'School ID not found'
            ], 400);
        }

        // Validasi Input
        $validator = Validator::make($request->all(), [
            'category' => 'required|in:fasilitas,akademik,layanan',
            'message' => 'required|string|min:10',
            // Field di bawah opsional di request jika user login, 
            // tapi kita bisa auto-fill dari data user jika kosong
            'sender_name' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $feedback = Feedback::create([
                'school_id' => $school_id,
                'user_id' => $user->id,
                // Gunakan input user, jika kosong gunakan data dari akun user
                'sender_name' => $request->sender_name ?? $user->name,
                'email' => $request->email ?? $user->email,
                'phone' => $request->phone ?? $user->phone_number ?? '-', // Pastikan field phone ada di user atau handle default
                'category' => $request->category,
                'message' => $request->message,
                'is_read' => false,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback submitted successfully',
                'data' => $feedback
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit feedback: ' . $e->getMessage()
            ], 500);
        }
    }
}