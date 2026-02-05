<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentCard;
use Illuminate\Http\Request;

class StudentCardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = StudentCard::with(['student', 'student.level', 'student.school']);

        // If user is guardian, only show their students' cards
        if ($user->hasRole('wali_santri')) {
            $query->whereHas('student', function ($q) use ($user) {
                $q->where('parent_user_id', $user->id);
            });
        }
        
        // If user is student, only show their own card? (If students have login)
        // Usually students don't login based on previous context, but if they did:
        // if ($user->hasRole('student')) ...

        // Filter by card number if provided
        if ($request->has('card_number')) {
            $query->where('card_number', $request->card_number);
        }

        $cards = $query->paginate(10);

        return response()->json($cards);
    }

    public function show($id)
    {
        $card = StudentCard::with(['student', 'student.level', 'student.school'])->find($id);

        if (!$card) {
            return response()->json(['message' => 'Student card not found'], 404);
        }
        
        // Authorization check could be added here

        return response()->json($card);
    }
    
    public function getByCardNumber($cardNumber)
    {
        $card = StudentCard::with(['student', 'student.level', 'student.school'])
            ->where('card_number', $cardNumber)
            ->first();

        if (!$card) {
            return response()->json(['message' => 'Student card not found'], 404);
        }

        return response()->json($card);
    }
}
