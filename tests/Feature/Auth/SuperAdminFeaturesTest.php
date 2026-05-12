<?php

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Super Admin Test School']);
    $this->superAdmin = User::factory()->create(['school_id' => $this->school->id]);
    $this->superAdmin->assignRole('super_admin');
});

it('allows super_admin to access global features', function () {
    $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('news.index'))->assertOk();
    
    // Administrasi
    $this->actingAs($this->superAdmin)->get(route('student-card-requests.index'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('student-cards.design'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('schools.index'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('users.global'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('roles.index'))->assertOk();
    
    // Keuangan
    $this->actingAs($this->superAdmin)->get(route('super-admin.financial'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('payment-methods.index'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('dashboard.super-admin.withdrawals'))->assertOk();
});

it('allows super_admin to access admin-level financial dashboard', function () {
    // Super admin should also be able to see unit-level finance if redirected or accessed directly
    $this->actingAs($this->superAdmin)->get(route('admin.financial'))->assertOk();
});

