<?php

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->school = School::factory()->create(['name' => 'Admin Test School']);
    $this->admin = User::factory()->create(['school_id' => $this->school->id]);
    $this->admin->assignRole('admin');
});

it('can access admin dashboard', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
});

it('can access berita & pengumuman', function () {
    $this->actingAs($this->admin)->get(route('news.index'))->assertOk();
});

it('can access akademik features', function () {
    $this->actingAs($this->admin)->get(route('academic-years.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('semesters.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('departments.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('levels.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('activities.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('events.index'))->assertOk();
});

it('can access kesiswaan features', function () {
    $this->actingAs($this->admin)->get(route('achievements.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('lectures.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('students.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('guardians.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('student-permissions.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('sowan-schedules.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('feedback.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('student-cards.index'))->assertOk();
});

it('can access presensi & laporan features', function () {
    $this->actingAs($this->admin)->get(route('activities.attendance.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('events.attendance.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('student-reports.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('students.promotion'))->assertOk();
});

it('can access administrasi pengguna', function () {
    $this->actingAs($this->admin)->get(route('users'))->assertOk();
});

it('can access keuangan features', function () {
    $this->actingAs($this->admin)->get(route('admin.financial'))->assertOk();
    $this->actingAs($this->admin)->get(route('dashboard.admin.manual-topup'))->assertOk();
    $this->actingAs($this->admin)->get(route('dashboard.admin.manual-bill-payment'))->assertOk();
    $this->actingAs($this->admin)->get(route('wallet-transactions.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('bills.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('bill-categories.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('event-bills.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('dashboard.admin.withdrawals'))->assertOk();
    $this->actingAs($this->admin)->get(route('dashboard.admin.student-cash-withdrawal'))->assertOk();
});

