<?php

namespace App\Http\Controllers;

use App\Models\StudentCard;
use App\Models\School;
use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;
use ZipArchive;
use Illuminate\Support\Facades\File;

class StudentCardPrintController extends Controller
{
    public function preview($id)
    {
        $card = StudentCard::with(['student.school', 'student.level'])->findOrFail($id);
        
        return view('pages.student-cards.print', compact('card'));
    }

    public function download(Request $request) {
        // This method will handle the bulk download request
        // Validate input
        $request->validate([
            'school_id' => 'required|exists:schools,id',
        ]);

        $schoolId = $request->input('school_id');
        $status = $request->input('status', 'request_print'); // Default to request_print
        
        $cards = StudentCard::query()
            ->whereHas('student', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->where('status', $status)
            ->with(['student.level.department', 'student.school'])
            ->limit(50) // Limit to prevent timeout, maybe increase if async job
            ->get();

        if ($cards->isEmpty()) {
            return back()->with('error', 'Tidak ada data kartu untuk dicetak.');
        }

        // Create Temp Directory
        $batchId = uniqid();
        $tempDir = storage_path("app/temp/cards/{$batchId}");
        File::ensureDirectoryExists($tempDir);

        $zipPath = storage_path("app/temp/cards/{$batchId}.zip");
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            return back()->with('error', 'Gagal membuat file ZIP.');
        }

        // Set expiration date (3 years from now)
        $printDate = now();
        $expiresAt = $printDate->copy()->addYears(3);

        foreach ($cards as $card) {
            $student = $card->student;
            $levelName = $student->level->name ?? 'Tanpa Jenjang';
            // Sanitize names for folder paths
            $levelName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $levelName);
            $className = $student->level->department->name ?? 'Umum';
            $className = preg_replace('/[^A-Za-z0-9_\-]/', '_', $className);
            
            // Temporarily set expires_at for the view (will be saved later)
            $card->expires_at = $expiresAt;
            
            // Generate HTML
            $html = view('pages.student-cards.print', compact('card'))->render();
            
            // Save Image
            $imageName = "{$student->nis}_{$student->full_name}.png";
            // Sanitize filename
            $imageName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $imageName);
            
            $imagePath = "{$tempDir}/{$imageName}";
            
            try {
                // Browsershot configuration for high quality PNG
                // If we are in testing, just touch the file
                if (app()->environment('testing')) {
                    touch($imagePath);
                } else {
                    Browsershot::html($html)
                        ->windowSize(856, 540)
                        ->deviceScaleFactor(2)
                        ->save($imagePath);
                }
                
                // Add to Zip with structure: Jenjang / Kelas / Namafile
                if (file_exists($imagePath)) {
                    $zip->addFile($imagePath, "{$className}/{$levelName}/{$imageName}");
                }
                
            } catch (\Exception $e) {
                \Log::error("Gagal generate kartu {$student->id}: " . $e->getMessage());
                // Continue to next card? or fail?
            }
        }

        $zip->close();

        // Update Status and Expiration
        StudentCard::whereIn('id', $cards->pluck('id'))->update([
            'status' => 'printed',
            'expires_at' => $expiresAt,
        ]);

        // Cleanup Temp Files (Images)
        File::deleteDirectory($tempDir);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
    public function downloadFile($filename)
    {
        // Simple security check to prevent directory traversal
        if (preg_match('/[^a-zA-Z0-9_\-\.]/', $filename)) {
            abort(400, "Invalid filename");
        }

        $zipPath = storage_path("app/temp/cards/{$filename}");

        if (!file_exists($zipPath)) {
            abort(404, "File not found");
        }
        
        $downloadName = request()->query('name', $filename);
        
        // Ensure name ends with .zip
        if (!\Illuminate\Support\Str::endsWith($downloadName, '.zip')) {
             $downloadName .= '.zip';
        }

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
