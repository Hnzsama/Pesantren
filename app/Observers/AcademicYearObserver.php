<?php

namespace App\Observers;

use App\Models\AcademicYear;
use App\Models\Semester;
use Carbon\Carbon;

class AcademicYearObserver
{
    /**
     * Handle the AcademicYear "created" event.
     */
    public function created(AcademicYear $academicYear): void
    {
        if (!$academicYear->start_date || !$academicYear->end_date) {
            return;
        }

        $startDate = Carbon::parse($academicYear->start_date);
        
        // Define Ganjil End Date (End of that year)
        // e.g. Start Mid 2024 -> End Dec 31, 2024
        $ganjilEndDate = $startDate->copy()->endOfYear();

        // Create Semester GANJIL
        Semester::create([
            'academic_year_id' => $academicYear->id,
            'school_id' => $academicYear->school_id,
            'type' => 'GANJIL',
            'start_date' => $startDate,
            'end_date' => $ganjilEndDate,
        ]);

        // Create Semester GENAP
        // Start: Jan 1st of next year (Day after Ganjil End)
        // End: Academic Year End Date
        $genapStartDate = $ganjilEndDate->copy()->addDay();
        
        Semester::create([
            'academic_year_id' => $academicYear->id,
            'school_id' => $academicYear->school_id,
            'type' => 'GENAP',
            'start_date' => $genapStartDate,
            'end_date' => $academicYear->end_date,
        ]);
    }
}
