<?php

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bendahara', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Test School']);
});

it('allows admin to access financial dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.financial'))
        ->assertStatus(200);
});

it('allows bendahara to access manual topup', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('bendahara');

    $this->actingAs($user)
        ->get(route('dashboard.admin.manual-topup'))
        ->assertStatus(200);
});

it('allows bendahara to access manual bill payment', function () {
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('bendahara');

    $this->actingAs($user)
        ->get(route('dashboard.admin.manual-bill-payment'))
        ->assertStatus(200);
});

it('forbids guru from accessing manual topup', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
    /** @var User $user */
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('guru');

    $this->actingAs($user)
        ->get(route('dashboard.admin.manual-topup'))
        ->assertStatus(403);
});

