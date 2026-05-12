<?php

namespace Tests\Feature\Koperasi;

use App\Models\User;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\Product;
use App\Models\WalletTransaction;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->kasir = User::factory()->create(['school_id' => $this->school->id]);
    $this->kasir->assignRole('kasir');

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'wallet_balance' => 1000000,
        'wallet_pin' => bcrypt('123456')
    ]);

    $this->card = StudentCard::factory()->create([
        'student_id' => $this->student->id,
        'card_number' => 'CARD999',
        'status' => 'printed',
        'issued_by' => $this->kasir->id,
        'issued_at' => now()->toDateString(),
    ]);

    $this->product = Product::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Buku Tulis',
        'selling_price' => 5000,
        'stock' => 10,
        'type' => 'PRODUCT'
    ]);
});

it('can identify student by scanning card', function () {
    Livewire::actingAs($this->kasir)
        ->test('pages::dashboard.cooperative.pos')
        ->set('cardNumber', 'CARD999')
        ->assertSet('student.id', $this->student->id);
});

it('can process purchase via wallet with correct PIN', function () {
    Livewire::actingAs($this->kasir)
        ->test('pages::dashboard.cooperative.pos')
        ->set('cardNumber', 'CARD999')
        ->call('addToCart', $this->product->id)
        ->set('pin', '123456')
        ->call('processPurchase')
        ->assertHasNoErrors()
        ->assertSet('lastTransaction.amount', 5000.0);

    // Verify stock reduced
    $this->product->refresh();
    expect($this->product->stock)->toEqual(9);

    // Verify balance reduced
    $this->student->refresh();
    expect($this->student->wallet_balance)->toEqual(995000.0);

    // Verify transaction log
    $transaction = WalletTransaction::where('student_id', $this->student->id)
        ->where('category', 'PURCHASE')
        ->first();
    expect($transaction->amount)->toEqual(5000.0);
});

it('fails purchase with incorrect PIN', function () {
    Livewire::actingAs($this->kasir)
        ->test('pages::dashboard.cooperative.pos')
        ->set('cardNumber', 'CARD999')
        ->call('addToCart', $this->product->id)
        ->set('pin', 'wrong-pin')
        ->call('processPurchase');

    // Verify nothing changed
    $this->product->refresh();
    expect($this->product->stock)->toEqual(10);
    $this->student->refresh();
    expect($this->student->wallet_balance)->toEqual(1000000.0);
});
