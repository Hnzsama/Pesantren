<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Student;
use App\Models\StudentCard;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] #[Layout('layouts.standalone')] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full animate-pulse">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div class="space-y-2">
                    <div class="h-8 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
                    <div class="h-4 w-48 bg-zinc-100 dark:bg-zinc-900 rounded-lg"></div>
                </div>
                <div class="h-10 w-32 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            </div>
            <div class="space-y-4">
                <div class="h-12 w-full bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-64 w-full bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800"></div>
            </div>
        </div>
        HTML;
    }

    public $event;

    public $scanInput = '';

    public $lastScan = null;

    public function mount($eventId)
    {
        $this->event = Event::find($eventId);
        if (!$this->event) {
            return redirect()->route('events.attendance.index');
        }
    }

    public function updatedScanInput()
    {
        if (empty($this->scanInput)) {
            return;
        }

        $this->processCardScan();
    }

    public function processCardScan()
    {
        $cardNumber = trim($this->scanInput);

        if (empty($cardNumber)) {
            return;
        }

        // Find student card
        $studentCard = StudentCard::query()
            ->with('student.level')
            ->where('card_number', $cardNumber)
            ->first();

        if (!$studentCard || !$studentCard->student) {
            $this->setLastScan($cardNumber, null, 'error', 'Kartu tidak ditemukan');
            $this->scanInput = '';

            return;
        }

        $student = $studentCard->student;

        // Check card status
        if ($studentCard->status !== 'printed') {
            $this->setLastScan($cardNumber, $student, 'error', 'Kartu belum diaktivasi');
            $this->scanInput = '';

            return;
        }

        if ($studentCard->expires_at && $studentCard->expires_at < now()) {
            $this->setLastScan($cardNumber, $student, 'expired', 'Kartu kadaluarsa');
            $this->scanInput = '';

            return;
        }

        // Check if already attended
        $exists = EventAttendance::where([
            'event_id' => $this->event->id,
            'student_id' => $student->id,
        ])->exists();

        if ($exists) {
            $this->setLastScan($cardNumber, $student, 'duplicate', 'Sudah tercatat hadir');
        } else {
            EventAttendance::create([
                'event_id' => $this->event->id,
                'student_id' => $student->id,
                'check_in_time' => now(),
                'status' => 'present',
            ]);

            $this->setLastScan($cardNumber, $student, 'success', null);
        }

        $this->scanInput = '';
    }

    private function setLastScan($cardNumber, $student, $status, $errorMessage)
    {
        $this->lastScan = [
            'card_number' => $cardNumber,
            'student_name' => $student ? $student->full_name : 'Tidak Diketahui',
            'student_level' => $student ? ($student->level->name ?? '-') : '',
            'status' => $status,
            'error_message' => $errorMessage,
            'timestamp' => now()->format('H:i:s'),
        ];
    }

    public function endSession()
    {
        session()->forget('scanner_event_id');
        Flux::toast(variant: 'success', heading: 'Sesi Selesai', text: 'Sesi absensi agenda telah ditutup.');

        return redirect()->route('events.attendance.index');
    }
}; ?>

<div class="min-h-screen flex flex-col items-center justify-center p-6 text-zinc-800 dark:text-zinc-100">
    <div class="w-full max-w-4xl space-y-8">
        {{-- Header --}}
        <div class="text-center space-y-2">
            <flux:heading size="xl" level="1">Scanner Presensi Agenda</flux:heading>
            <flux:subheading class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                {{ $event->title }}
            </flux:subheading>
            <div class="text-lg text-zinc-500">
                {{ \Carbon\Carbon::parse($event->event_date)->locale('id')->isoFormat('dddd, D MMMM Y') }}
                @if($event->start_time)
                    • {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}
                @endif
            </div>
        </div>

        {{-- Result Card --}}
        <div class="relative">
            @if($lastScan)
                @php
                    $statusColor = match ($lastScan['status']) {
                        'success' => 'green',
                        'duplicate' => 'yellow',
                        'expired' => 'orange',
                        'error' => 'red',
                        default => 'zinc'
                    };
                @endphp

                <flux:card
                    class="text-center space-y-6 !border-{{ $statusColor }}-500 dark:!border-{{ $statusColor }}-500 !bg-{{ $statusColor }}-50 dark:!bg-{{ $statusColor }}-950">
                    <div>
                        <flux:badge size="lg" color="{{ $statusColor }}" class="mb-4">
                            @if($lastScan['status'] === 'success')
                                BERHASIL HADIR
                            @elseif($lastScan['status'] === 'duplicate')
                                SUDAH TERCATAT
                            @else
                                {{ strtoupper($lastScan['error_message']) }}
                            @endif
                        </flux:badge>

                        <h2 class="text-5xl font-bold mb-2 
                                        @if($lastScan['status'] === 'success') text-green-900 dark:text-green-100
                                        @elseif($lastScan['status'] === 'duplicate') text-yellow-900 dark:text-yellow-100
                                        @elseif($lastScan['status'] === 'expired') text-orange-900 dark:text-orange-100
                                        @else text-red-900 dark:text-red-100
                                        @endif">
                            {{ $lastScan['student_name'] }}
                        </h2>
                        <div class="text-2xl 
                                        @if($lastScan['status'] === 'success') text-green-700 dark:text-green-300
                                        @elseif($lastScan['status'] === 'duplicate') text-yellow-700 dark:text-yellow-300
                                        @elseif($lastScan['status'] === 'expired') text-orange-700 dark:text-orange-300
                                        @else text-red-700 dark:text-red-300
                                        @endif">
                            {{ $lastScan['student_level'] }}
                        </div>
                    </div>

                    @if($lastScan['status'] === 'success')
                        <div class="py-4 border-t border-green-200 dark:border-green-700">
                            <div class="text-green-700 dark:text-green-300 flex items-center justify-center gap-2">
                                <flux:icon.check class="w-6 h-6" />
                                <span>Tercatat pada {{ $lastScan['timestamp'] }}</span>
                            </div>
                        </div>
                    @endif
                </flux:card>
            @else
                <flux:card class="text-center py-12 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="mb-4">
                        <flux:icon.qr-code class="w-24 h-24 mx-auto text-zinc-300 dark:text-zinc-600" />
                    </div>
                    <h3 class="text-3xl font-medium text-zinc-900 dark:text-white">Siap Memindai</h3>
                    <p class="text-zinc-500 dark:text-zinc-400 mt-2">Silakan scan kartu santri untuk agenda ini</p>
                </flux:card>
            @endif
        </div>

        {{-- Input Area --}}
        <div x-data="{ focused: true }" x-init="$nextTick(() => $refs.scanInput.focus())" class="relative">
            <flux:input wire:model.live="scanInput" x-ref="scanInput" @click="$refs.scanInput.focus()"
                @blur="setTimeout(() => $refs.scanInput.focus(), 100)" placeholder="Input Scan..."
                class="text-center text-2xl font-mono opacity-50 focus:opacity-100 transition-opacity p-4" />
            <div class="text-center mt-2">
                <span class="text-xs text-zinc-400">
                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block mr-1"></span>
                    Auto-focus aktif
                </span>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex justify-center pt-8">
            <flux:button variant="subtle" wire:click="endSession" icon="arrow-left">
                Kembali ke Daftar Agenda
            </flux:button>
        </div>
    </div>
</div>