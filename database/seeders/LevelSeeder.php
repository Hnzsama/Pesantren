<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Level;
use App\Models\School;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = School::all();

        foreach ($schools as $school) {
            // Retrieve Departments for this school
            // Note: Ensure departments exist. For seeding, we might need to assume they are created or create them here if missing.
            // Ideally DepartmentSeeder runs before this.
            
            // Map Code to Logic
            $deptConfig = [
                'MI'  => ['start' => 1, 'end' => 6, 'alias_prefix' => 'MI'],
                'SD'  => ['start' => 1, 'end' => 6, 'alias_prefix' => 'SD'],
                'MTs' => ['start' => 7, 'end' => 9, 'alias_prefix' => 'MTs', 'alias_reset' => true], // Alias resets to 1
                'SMP' => ['start' => 7, 'end' => 9, 'alias_prefix' => 'SMP', 'alias_reset' => true],
                'MA'  => ['start' => 10, 'end' => 12, 'alias_prefix' => 'MA', 'alias_reset' => true],
                'SMA' => ['start' => 10, 'end' => 12, 'alias_prefix' => 'SMA', 'alias_reset' => true],
                'SMK' => ['start' => 10, 'end' => 12, 'alias_prefix' => 'SMK', 'alias_reset' => true],
            ];

            foreach ($deptConfig as $code => $config) {
                $dept = Department::where('school_id', $school->id)
                    ->where('code', $code)
                    ->first();

                if ($dept) {
                    for ($grade = $config['start']; $grade <= $config['end']; $grade++) {
                        $aliasNum = isset($config['alias_reset']) && $config['alias_reset'] 
                            ? ($grade - $config['start'] + 1) 
                            : $grade;
                            
                        $alias = "{$config['alias_prefix']}-{$aliasNum}";
                        
                        Level::firstOrCreate(
                            [
                                'school_id' => $school->id,
                                'department_id' => $dept->id,
                                'grade_level' => $grade,
                            ],
                            [
                                'name' => "Kelas {$grade}",
                                'alias' => $alias,
                            ]
                        );
                    }
                }
            }
        }
    }
}
