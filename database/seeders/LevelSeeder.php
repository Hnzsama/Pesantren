<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Level;
use App\Models\School;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();
        foreach ($schools as $school) {
            // Ambil Department yang ada di sekolah ini
            $deptMI = Department::where('school_id', $school->id)->where('code', 'MI')->first();
            $deptMTs = Department::where('school_id', $school->id)->where('code', 'MTs')->first();
            $deptMA = Department::where('school_id', $school->id)->where('code', 'MA')->first();
            
            // Seed MI (Kelas 1-6)
            if ($deptMI) {
                for ($i = 1; $i <= 6; $i++) {
                    Level::firstOrCreate(
                        ['school_id' => $school->id, 'department_id' => $deptMI->id, 'name' => "Kelas $i"],
                        ['grade_level' => $i, 'alias' => "MI-$i"]
                    );
                }
            }
            // Seed MTs (Kelas 7-9)
            if ($deptMTs) {
                for ($i = 1; $i <= 3; $i++) {
                    Level::firstOrCreate(
                        ['school_id' => $school->id, 'department_id' => $deptMTs->id, 'name' => "Kelas " . ($i+6)],
                        ['grade_level' => $i, 'alias' => "MTs-$i"]
                    );
                }
            }
             // Seed MA (Kelas 10-12)
            if ($deptMA) {
                for ($i = 1; $i <= 3; $i++) {
                    Level::firstOrCreate(
                        ['school_id' => $school->id, 'department_id' => $deptMA->id, 'name' => "Kelas " . ($i+9)],
                        ['grade_level' => $i, 'alias' => "MA-$i"]
                    );
                }
            }
        }
    }
}
