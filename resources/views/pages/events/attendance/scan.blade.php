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
                'student_name' => $student->full_name,
                'student_nis' => $student->nis,
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

@assets
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
@endassets

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
                        'duplicate' => 'blue',
                        'expired' => 'amber',
                        'error' => 'red',
                        default => 'zinc'
                    };
                @endphp

                <div class="rounded-xl border-2 p-8 text-center space-y-6
                    @if($lastScan['status'] === 'success')
                        bg-green-50 border-green-500 dark:bg-green-950 dark:border-green-500
                    @elseif($lastScan['status'] === 'duplicate')
                        bg-blue-50 border-blue-500 dark:bg-blue-950 dark:border-blue-500
                    @elseif($lastScan['status'] === 'expired')
                        bg-amber-50 border-amber-500 dark:bg-amber-950 dark:border-amber-500
                    @else
                        bg-red-50 border-red-500 dark:bg-red-950 dark:border-red-500
                    @endif">
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
                            @elseif($lastScan['status'] === 'duplicate') text-blue-900 dark:text-blue-100
                            @elseif($lastScan['status'] === 'expired') text-amber-900 dark:text-amber-100
                            @else text-red-900 dark:text-red-100
                            @endif">
                            {{ $lastScan['student_name'] }}
                        </h2>
                        <div class="text-2xl
                            @if($lastScan['status'] === 'success') text-green-800 dark:text-green-200
                            @elseif($lastScan['status'] === 'duplicate') text-blue-800 dark:text-blue-200
                            @elseif($lastScan['status'] === 'expired') text-amber-800 dark:text-amber-200
                            @else text-red-800 dark:text-red-200
                            @endif">
                            {{ $lastScan['student_level'] }}
                        </div>
                    </div>

                    @if($lastScan['status'] === 'success')
                        <div class="py-4 border-t border-green-200 dark:border-green-600">
                            <div class="text-green-800 dark:text-green-200 flex items-center justify-center gap-2 font-medium">
                                <flux:icon.check class="w-6 h-6" />
                                <span>Tercatat pada {{ $lastScan['timestamp'] }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-xl border-2 p-8 text-center bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600">
                    <div class="mb-4">
                        <flux:icon.qr-code class="w-24 h-24 mx-auto text-zinc-300 dark:text-zinc-600" />
                    </div>
                    <h3 class="text-3xl font-medium text-zinc-900 dark:text-white">Siap Memindai</h3>
                    <p class="text-zinc-500 dark:text-zinc-400 mt-2">Silakan scan kartu santri untuk agenda ini</p>
                </div>
            @endif
        </div>

        {{-- Camera Scanner --}}
        <div x-data="{ 
            showCamera: false,
            html5QrCode: null,
            initScanner() {
                this.html5QrCode = new Html5Qrcode('reader-event');
            },
            async startScanning() {
                if (!window.isSecureContext && window.location.hostname !== 'localhost') {
                    alert('Maaf, kamera hanya bisa diakses melalui koneksi HTTPS yang aman.');
                    return;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Browser Anda tidak mendukung akses kamera.');
                    return;
                }

                this.showCamera = true;
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                
                try {
                    // Force permission request
                    await Html5Qrcode.getCameras();
                    
                    await this.html5QrCode.start(
                        { facingMode: 'environment' }, 
                        config,
                        (decodedText) => {
                            $wire.set('scanInput', decodedText);
                            $wire.processCardScan();
                            this.stopScanning();
                        }
                    );
                } catch (err) {
                    console.error('Error starting scanner:', err);
                    let message = 'Gagal mengakses kamera.';
                    if (err.name === 'NotAllowedError' || err === 'Permission denied') {
                        message = 'Izin kamera ditolak. Silakan izinkan akses kamera pada pengaturan browser Anda.';
                    } else if (err.name === 'NotFoundError') {
                        message = 'Kamera tidak ditemukan pada perangkat ini.';
                    }
                    alert(message);
                    this.showCamera = false;
                }
            },
            stopScanning() {
                if (this.html5QrCode) {
                    this.html5QrCode.stop().then(() => {
                        this.showCamera = false;
                    }).catch(err => console.error('Error stopping scanner:', err));
                } else {
                    this.showCamera = false;
                }
            }
        }" x-init="initScanner()" class="space-y-4">
            
            <div x-show="showCamera" class="relative rounded-2xl overflow-hidden bg-black aspect-square max-w-sm mx-auto shadow-2xl border-4 border-accent/20">
                <div id="reader-event" class="w-full h-full"></div>
                <button @click="stopScanning()" class="absolute top-4 right-4 bg-red-500 text-white p-2 rounded-full shadow-lg">
                    <flux:icon.x-mark class="w-6 h-6" />
                </button>
                <div class="absolute inset-0 border-2 border-white/30 pointer-events-none flex items-center justify-center">
                    <div class="w-48 h-48 border-2 border-accent rounded-lg"></div>
                </div>
            </div>

            <div class="flex justify-center">
                <flux:button x-show="!showCamera" @click="startScanning()" icon="camera" variant="filled" class="!bg-accent !text-accent-foreground">
                    Scan Pakai Kamera HP
                </flux:button>
            </div>
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