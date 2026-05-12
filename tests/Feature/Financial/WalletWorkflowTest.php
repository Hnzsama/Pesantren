<?php

namespace Tests\Feature\Financial;

use App\Models\User;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\WalletTransaction;
use App\Models\Bill;
use App\Models\BillCategory;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bendahara', 'guard_name' => 'web']);
    
    // Setup school and user
    $this->school = School::factory()->create(['name' => 'Test Pesantren']);
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'admin@pesantren.com'
    ]);
    $this->admin->assignRole('admin');
    
    // Setup student
    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'full_name' => 'Santri Testing',
        'nis' => '123456',
        'wallet_balance' => 0,
        'status' => 'ACTIVE'
    ]);
});

it('can process manual top up and update balance', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::dashboard.admin.manual-topup')
        ->set('student_id', $this->student->id)
        ->set('amount', 50000)
        ->set('admin_fee', 2000)
        ->call('submit')
        ->assertHasNoErrors();

    // Verify balance
    $this->student->refresh();
    expect($this->student->wallet_balance)->toEqual(50000.0);

    // Verify transaction log
    $transaction = WalletTransaction::where('student_id', $this->student->id)->first();
    expect($transaction->amount)->toEqual(50000.0)
        ->and($transaction->type)->toBe('CREDIT')
        ->and($transaction->category)->toBe('TOPUP');
});

it('can process manual bill payment and update balance', function () {
    // 1. Give student some balance first
    $this->student->update(['wallet_balance' => 100000]);
    
    // 2. Create a bill
    $category = BillCategory::create([
        'school_id' => $this->school->id,
        'name' => 'SPP Bulanan',
        'amount' => 50000
    ]);
    
    $bill = Bill::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'bill_category_id' => $category->id,
        'amount' => 50000,
        'status' => 'UNPAID',
        'student_name' => $this->student->full_name,
        'title' => 'SPP Bulanan'
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::dashboard.admin.manual-bill-payment')
        ->set('student_id', $this->student->id)
        ->set('selected_bill_id', $bill->id)
        ->call('processPayment')
        ->assertHasNoErrors();

    // Verify bill status
    $bill->refresh();
    expect($bill->status)->toBe('PAID');

    // Verify balance remains same (Paid in Cash)
    $this->student->refresh();
    expect($this->student->wallet_balance)->toEqual(100000.0);
    
    // Verify transaction
    $transaction = WalletTransaction::where('student_id', $this->student->id)
        ->where('category', 'PURCHASE')
        ->first();
    expect($transaction->amount)->toEqual(50000.0)
        ->and($transaction->type)->toBe('DEBIT');
});

it('can process student cash withdrawal (Pocket Money)', function () {
    // 1. Give student some balance
    $this->student->update([
        'wallet_balance' => 50000,
        'wallet_pin' => bcrypt('123456')
    ]);

    // 2. Create card for student
    StudentCard::factory()->create([
        'student_id' => $this->student->id,
        'card_number' => 'WD-CARD-123',
        'status' => 'printed',
        'issued_by' => $this->admin->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::dashboard.admin.student-cash-withdrawal')
        ->set('cardNumber', 'WD-CARD-123')
        ->call('searchCard')
        ->assertSet('step', 2)
        ->call('processWithdrawal') // This will fail because step 3 is needed for input
        ->assertHasErrors(['amount', 'pin'])
        ->set('step', 3)
        ->set('amount', 20000)
        ->set('pin', '123456')
        ->call('processWithdrawal')
        ->assertHasNoErrors();

    // Verify balance
    $this->student->refresh();
    expect($this->student->wallet_balance)->toEqual(30000.0);

    // Verify transaction
    $transaction = WalletTransaction::where('student_id', $this->student->id)
        ->where('category', 'WITHDRAWAL')
        ->first();
    expect($transaction->amount)->toEqual(20000.0)
        ->and($transaction->type)->toBe('DEBIT');
});
