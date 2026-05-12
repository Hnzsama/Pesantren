<?php

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin_koperasi', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Coop Test School']);
    
    $this->adminKoperasi = User::factory()->create(['school_id' => $this->school->id]);
    $this->adminKoperasi->assignRole('admin_koperasi');
    
    $this->kasir = User::factory()->create(['school_id' => $this->school->id]);
    $this->kasir->assignRole('kasir');
});

it('allows admin_koperasi to access cooperative features', function () {
    $this->actingAs($this->adminKoperasi)->get(route('koperasi.dashboard'))->assertOk();
    $this->actingAs($this->adminKoperasi)->get(route('koperasi.history'))->assertOk();
    $this->actingAs($this->adminKoperasi)->get(route('koperasi.products'))->assertOk();
    $this->actingAs($this->adminKoperasi)->get(route('koperasi.stock-opname'))->assertOk();
    $this->actingAs($this->adminKoperasi)->get(route('koperasi.stock-history'))->assertOk();
    $this->actingAs($this->adminKoperasi)->get(route('koperasi.cashiers'))->assertOk();
});

it('allows kasir to access POS and history', function () {
    $this->actingAs($this->kasir)->get(route('koperasi.pos'))->assertOk();
    $this->actingAs($this->kasir)->get(route('koperasi.history'))->assertOk();
});

it('forbids kasir from accessing management features', function () {
    $this->actingAs($this->kasir)->get(route('koperasi.products'))->assertStatus(403);
    $this->actingAs($this->kasir)->get(route('koperasi.cashiers'))->assertStatus(403);
});

