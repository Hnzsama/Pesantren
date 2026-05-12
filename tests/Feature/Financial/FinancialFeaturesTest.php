<?php

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'bendahara', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Financial Test School']);
    $this->bendahara = User::factory()->create(['school_id' => $this->school->id]);
    $this->bendahara->assignRole('bendahara');
});

it('allows bendahara to access financial features', function () {
    $this->actingAs($this->bendahara)->get(route('admin.financial'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('dashboard.admin.manual-topup'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('dashboard.admin.manual-bill-payment'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('wallet-transactions.index'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('bills.index'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('bill-categories.index'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('event-bills.index'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('dashboard.admin.withdrawals'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('dashboard.admin.student-cash-withdrawal'))->assertOk();
});

it('forbids bendahara from accessing academic master data', function () {
    $this->actingAs($this->bendahara)->get(route('academic-years.index'))->assertStatus(403);
    $this->actingAs($this->bendahara)->get(route('departments.index'))->assertStatus(403);
});

