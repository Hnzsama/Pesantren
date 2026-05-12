<?php

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin_koperasi', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Test School']);
});

it('allows kasir to access POS', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('kasir');

    $this->actingAs($user)
        ->get(route('koperasi.pos'))
        ->assertStatus(200);
});

it('allows admin_koperasi to access koperasi dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('admin_koperasi');

    $this->actingAs($user)
        ->get(route('koperasi.dashboard'))
        ->assertStatus(200);
});

it('forbids guru from accessing POS', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('guru');

    $this->actingAs($user)
        ->get(route('koperasi.pos'))
        ->assertStatus(403);
});

