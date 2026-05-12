<?php

namespace Tests\Feature\Academic;

use App\Models\User;
use App\Models\School;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Department;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentAcademic;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['school_id' => $this->school->id]);
    $this->admin->assignRole('admin');
});

it('can manage academic years', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::academic-years.index')
        ->set('name', '2025/2026')
        ->set('start_date', '2025-07-01')
        ->set('end_date', '2026-06-30')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('academic_years', [
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'is_active' => true
    ]);
});

it('can manage departments and classes', function () {
    // 1. Create Department
    Livewire::actingAs($this->admin)
        ->test('pages::departments.index')
        ->set('name', 'Madrasah Aliyah')
        ->set('code', 'MA')
        ->call('save')
        ->assertHasNoErrors();

    $dept = Department::where('name', 'Madrasah Aliyah')->first();

    // 2. Create Level (Class)
    Livewire::actingAs($this->admin)
        ->test('pages::levels.index')
        ->set('department_id', $dept->id)
        ->set('name', 'Kelas 10-A')
        ->set('grade_level', 10)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('levels', [
        'department_id' => $dept->id,
        'name' => 'Kelas 10-A'
    ]);
});

it('can promote students to next level', function () {
    $dept = Department::create(['school_id' => $this->school->id, 'name' => 'MA']);
    $level1 = Level::create(['school_id' => $this->school->id, 'department_id' => $dept->id, 'name' => '10', 'grade_level' => 10]);
    $level2 = Level::create(['school_id' => $this->school->id, 'department_id' => $dept->id, 'name' => '11', 'grade_level' => 11]);
    
    $yearSource = AcademicYear::create([
        'school_id' => $this->school->id,
        'name' => '2024/2025',
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
        'is_active' => false
    ]);

    $yearTarget = AcademicYear::create([
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
        'is_active' => true
    ]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'level_id' => $level1->id,
        'full_name' => 'Santri Naik'
    ]);

    StudentAcademic::create([
        'student_id' => $student->id,
        'academic_year_id' => $yearSource->id,
        'level_id' => $level1->id,
        'status' => 'ONGOING'
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::students.promotion')
        ->set('schoolId', $this->school->id)
        ->set('sourceAcademicYearId', $yearSource->id)
        ->set('targetAcademicYearId', $yearTarget->id)
        ->set('departmentId', $dept->id)
        ->set('levelIds', [$level1->id])
        ->call('nextStep')
        ->assertSet('step', 2)
        ->assertHasNoErrors()
        ->call('executePromotion')
        ->assertHasNoErrors();

    $student->refresh();
    expect($student->level_id)->toBe($level2->id);
});
