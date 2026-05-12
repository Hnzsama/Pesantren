<?php

use App\Models\User;
use App\Models\Student;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Test School']);
});

it('allows admin to view student list', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('students.index'))
        ->assertStatus(200);
});

it('allows admin to access student import page', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('students.import'))
        ->assertStatus(200);
});

it('forbids guru from accessing student management', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('guru');

    $this->actingAs($user)
        ->get(route('students.index'))
        ->assertStatus(403);
});

