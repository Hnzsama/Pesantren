<?php

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'pengurus', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Test School']);
});

it('allows guru to access attendance index', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('guru');

    $this->actingAs($user)
        ->get(route('activities.attendance.index'))
        ->assertStatus(200);
});

it('allows pengurus to access attendance scan', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('pengurus');

    $this->actingAs($user)
        ->get(route('activities.attendance.scan'))
        ->assertStatus(200);
});

it('forbids kasir from accessing attendance', function () {
    Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('kasir');

    $this->actingAs($user)
        ->get(route('activities.attendance.index'))
        ->assertStatus(403);
});

