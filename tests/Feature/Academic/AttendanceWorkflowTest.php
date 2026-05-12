<?php

namespace Tests\Feature\Academic;

use App\Models\User;
use App\Models\School;
use App\Models\Activity;
use App\Models\ActivityAttendance;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentCard;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Carbon\Carbon;

/**
 * @property \App\Models\School $school
 * @property \App\Models\User $pengurus
 * @property \App\Models\Level $level
 * @property \App\Models\Student $student
 * @property \App\Models\StudentCard $card
 * @property \App\Models\Activity $activity
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'pengurus', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->pengurus = User::factory()->create(['school_id' => $this->school->id]);
    $this->pengurus->assignRole('pengurus');

    $this->level = Level::factory()->create(['school_id' => $this->school->id]);
    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'level_id' => $this->level->id,
        'status' => 'ACTIVE'
    ]);

    $this->card = StudentCard::factory()->create([
        'student_id' => $this->student->id,
        'card_number' => 'ABSEN123',
        'status' => 'printed',
        'issued_by' => $this->pengurus->id,
    ]);

    $this->activity = Activity::create([
        'school_id' => $this->school->id,
        'name' => 'Shalat Jamaah',
        'type' => 'daily'
    ]);
});

it('can record attendance via QR scan', function () {
    // Setup session for scanner
    session([
        'scanner_date' => Carbon::today()->format('Y-m-d'),
        'scanner_levels' => [$this->level->id],
        'scanner_activities' => [$this->activity->id]
    ]);

    $component = Livewire::actingAs($this->pengurus)
        ->test('pages::activities.attendance.scan')
        ->set('sessionDate', Carbon::today()->format('Y-m-d'))
        ->set('selectedLevels', [$this->level->id])
        ->set('selectedActivities', [$this->activity->id])
        ->set('scanInput', 'ABSEN123')
        ->call('processCardScan');
    
    $lastScan = $component->get('lastScan');

    expect($lastScan['status'])->toBe('success', "Scan failed with error: " . ($lastScan['error_message'] ?? 'Unknown error'));

    $this->assertDatabaseHas('activity_attendances', [
        'student_id' => $this->student->id,
        'activity_id' => $this->activity->id,
        'status' => 'present'
    ]);
});

it('fails scan if student level is not in scope', function () {
    $otherLevel = Level::factory()->create(['school_id' => $this->school->id]);
    
    session([
        'scanner_date' => Carbon::today()->format('Y-m-d'),
        'scanner_levels' => [$otherLevel->id], // Only otherLevel is allowed
        'scanner_activities' => [$this->activity->id]
    ]);

    Livewire::actingAs($this->pengurus)
        ->test('pages::activities.attendance.scan')
        ->set('scanInput', 'ABSEN123')
        ->call('processCardScan')
        ->assertSet('lastScan.status', 'error')
        ->assertSet('lastScan.error_message', 'Kelas tidak sesuai');
});
