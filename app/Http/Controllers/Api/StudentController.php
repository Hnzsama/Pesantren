<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    /**
     * Display the specified student with relationships.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        // Get student and verify ownership
        $student = Student::with(['parent:id,name,email,phone_number', 'school:id,name,address', 'level:id,name'])
            ->where('id', $id)
            ->where('parent_user_id', $user->id) // Security: Only show own student
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or unauthorized.'], 404);
        }

        // Format response to match mobile app expectations
        return response()->json([
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'full_name' => $student->full_name,
                'name' => $student->full_name, // Alias for compatibility
                'photo_url' => $student->photo_url,
                'status' => $student->status,
                'gender' => $student->gender,
                'birth_date' => $student->birth_date,
                'wallet_balance' => $student->wallet_balance,
                'daily_cash_limit' => $student->daily_cash_limit,
                'allow_transaction' => $student->allow_transaction,
                'has_pin' => $student->hasPin(),
                'level' => $student->level ? [
                    'id' => $student->level->id,
                    'name' => $student->level->name,
                ] : null,
            ],
            'parent' => $student->parent ? [
                'id' => $student->parent->id,
                'name' => $student->parent->name,
                'email' => $student->parent->email,
                'phone_number' => $student->parent->phone_number,
            ] : null,
            'school' => $student->school ? [
                'id' => $student->school->id,
                'name' => $student->school->name,
                'address' => $student->school->address,
            ] : null,
        ]);
    }
    /**
     * Get student wallet balance only.
     */
    public function wallet(Request $request, $id)
    {
        $user = $request->user();
        
        // Verify ownership
        $student = Student::where('id', $id)
            ->where('parent_user_id', $user->id)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or unauthorized.'], 404);
        }

        return response()->json([
            'wallet_balance' => $student->wallet_balance,
            'has_pin' => $student->hasPin(),
        ]);
    }

    /**
     * Update student wallet PIN.
     */
    public function updatePin(Request $request, $id)
    {
        $request->validate([
            'pin' => 'required|digits:6|confirmed',
        ], [
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits' => 'PIN harus berupa 6 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak cocok.',
        ]);

        $user = $request->user();
        $student = Student::where('id', $id)
            ->where('parent_user_id', $user->id)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or unauthorized.'], 404);
        }

        $student->update([
            'wallet_pin' => Hash::make($request->pin),
        ]);

        return response()->json(['message' => 'Wallet PIN berhasil diperbarui.']);
    }

    /**
     * Update student daily cash limit.
     */
    public function updateDailyLimit(Request $request, $id)
    {
        $request->validate([
            'daily_cash_limit' => 'required|numeric|min:0',
        ], [
            'daily_cash_limit.required' => 'Limit harian wajib diisi.',
            'daily_cash_limit.numeric' => 'Limit harian harus berupa angka.',
            'daily_cash_limit.min' => 'Limit harian tidak boleh negatif.',
        ]);

        $user = $request->user();
        $student = Student::where('id', $id)
            ->where('parent_user_id', $user->id)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or unauthorized.'], 404);
        }

        $student->update([
            'daily_cash_limit' => $request->daily_cash_limit,
        ]);

        return response()->json(['message' => 'Limit tarik tunai harian berhasil diperbarui.']);
    }
}

/*
|--------------------------------------------------------------------------
| API Documentation (Frontend)
|--------------------------------------------------------------------------
|
| Endpoint: GET /api/students/{studentId}
| Middleware: auth:sanctum
|
| Mendapatkan detail lengkap profil santri termasuk data orang tua dan sekolah.
| User yang login (wali_santri) hanya bisa melihat data santri yang terkait dengannya.
|
| Example Response (200 OK):
| {
|     "student": {
|         "id": "01hk...",
|         "nis": "12345",
|         "full_name": "Ahmad Fauzi",
|         "name": "Ahmad Fauzi",
|         "photo_url": "http://...",
|         "status": "ACTIVE",
|         "gender": "L",
|         "birth_date": "2010-05-15",
|         "wallet_balance": "500000.00",
|         "daily_cash_limit": "50000.00",
|         "allow_transaction": true,
|         "level": {
|             "id": 1,
|             "name": "Kelas 3 MTs"
|         }
|     },
|     "parent": {
|         "id": "01hk...",
|         "name": "Bapak Ahmad",
|         "email": "ahmad@example.com",
|         "phone_number": "08123456789"
|     },
|     "school": {
|         "id": "01hk...",
|         "name": "Pondok Pesantren Al-Falah",
|         "address": "Jl. Raya No. 123"
|     }
| }
|
| Error Response (404):
| {
|     "message": "Student not found or unauthorized."
| }
|
*/
