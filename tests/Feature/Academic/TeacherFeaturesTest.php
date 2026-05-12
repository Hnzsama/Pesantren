<?php

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'pengurus', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Academic Test School']);
    
    $this->guru = User::factory()->create(['school_id' => $this->school->id]);
    $this->guru->assignRole('guru');
    
    $this->pengurus = User::factory()->create(['school_id' => $this->school->id]);
    $this->pengurus->assignRole('pengurus');
});

it('allows guru to access teacher features', function () {
    $this->actingAs($this->guru)->get(route('guru.dashboard'))->assertOk();
    $this->actingAs($this->guru)->get(route('news.index'))->assertOk();
    $this->actingAs($this->guru)->get(route('activities.attendance.index'))->assertOk();
    $this->actingAs($this->guru)->get(route('events.attendance.index'))->assertOk();
    $this->actingAs($this->guru)->get(route('student-reports.index'))->assertOk();
    $this->actingAs($this->guru)->get(route('students.promotion'))->assertOk();
});

it('allows pengurus to access attendance features', function () {
    $this->actingAs($this->pengurus)->get(route('activities.attendance.index'))->assertOk();
    $this->actingAs($this->pengurus)->get(route('events.attendance.index'))->assertOk();
});

it('forbids pengurus from accessing reports and news', function () {
    $this->actingAs($this->pengurus)->get(route('news.index'))->assertStatus(403);
    $this->actingAs($this->pengurus)->get(route('student-reports.index'))->assertStatus(403);
});

