<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\School;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();
        foreach ($schools as $school) {
            $departments = [
                ['name' => 'Madrasah Ibtidaiyah', 'code' => 'MI'],
                ['name' => 'Madrasah Tsanawiyah', 'code' => 'MTs'],
                ['name' => 'Madrasah Aliyah', 'code' => 'MA'],
            ];
            foreach ($departments as $dept) {
                Department::firstOrCreate(
                    ['school_id' => $school->id, 'code' => $dept['code']],
                    ['name' => $dept['name']]
                );
            }
        }
    }
}
