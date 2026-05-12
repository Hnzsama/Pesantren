<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure roles exist
    $roles = ['super_admin', 'admin', 'bendahara', 'kasir', 'admin_koperasi', 'guru', 'pengurus', 'wali_santri'];
    foreach ($roles as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

it('redirects super_admin to super admin dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('super-admin.dashboard'));
});

it('redirects admin to admin dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('admin.dashboard'));
});

it('redirects bendahara to admin dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('bendahara');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('admin.dashboard'));
});

it('redirects admin_koperasi to koperasi dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin_koperasi');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('koperasi.dashboard'));
});

it('redirects kasir to koperasi dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('kasir');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('koperasi.dashboard'));
});

it('redirects guru to guru dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('guru');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('guru.dashboard'));
});

it('redirects pengurus to activities attendance index', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('pengurus');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('activities.attendance.index'));
});

it('redirects wali_santri to generic dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('wali_santri');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertViewIs('dashboard');
});

it('forbids guru from accessing pos koperasi', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('guru');

    $this->actingAs($user)
        ->get('/koperasi/pos')
        ->assertStatus(403); 
});

