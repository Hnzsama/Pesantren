<?php

use App\Models\ActivityAttendance;
use App\Models\Activity;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentCard;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Flux\Flux;
use Carbon\Carbon;

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

    public $sessionDate;
    public $selectedLevels = [];
    public $selectedActivities = [];

    public $scanInput = '';
    public $lastScan = null;

    public function mount()
    {
        $this->sessionDate = session('scanner_date', Carbon::today()->format('Y-m-d'));
        $this->selectedLevels = session('scanner_levels', []);
        $this->selectedActivities = session('scanner_activities', []);

        if (empty($this->selectedLevels) || empty($this->selectedActivities)) {
            return redirect()->route('activities.attendance.index');
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

        $user = auth()->user();
        $schoolId = auth()->user()->school_id;

        $studentCard = StudentCard::query()
            ->with('student')
            ->where('card_number', $cardNumber)
            ->first();

        if (!$studentCard || !$studentCard->student) {
            $this->setLastScan($cardNumber, null, 'error', 'Kartu tidak ditemukan', []);
            $this->scanInput = '';
            return;
        }

        $student = $studentCard->student;

        if ($studentCard->status !== 'printed') {
            $this->setLastScan($cardNumber, $student, 'error', 'Kartu belum diaktivasi', []);
            $this->scanInput = '';
            return;
        }

        if ($studentCard->expires_at && $studentCard->expires_at < now()) {
            $this->setLastScan($cardNumber, $student, 'error', 'Kartu kadaluarsa', []);
            $this->scanInput = '';
            return;
        }

        if (!in_array((string)$student->level_id, array_map('strval', (array)$this->selectedLevels))) {
            $this->setLastScan($cardNumber, $student, 'error', 'Kelas tidak sesuai', []);
            $this->scanInput = '';
            return;
        }

        $recordedActivities = [];
        $alreadyRecorded = [];

        foreach ($this->selectedActivities as $activityId) {
            $exists = ActivityAttendance::where([
                'school_id' => $schoolId,
                'student_id' => $student->id,
                'activity_id' => $activityId,
                'date' => $this->sessionDate,
            ])->exists();

            $activity = Activity::find($activityId);

            if ($exists) {
                if ($activity) {
                    $alreadyRecorded[] = $activity->name;
                }
            } else {
                ActivityAttendance::create([
                    'school_id' => $student->school_id,
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'student_nis' => $student->nis,
                    'activity_id' => $activityId,
                    'date' => $this->sessionDate,
                    'status' => 'present',
                ]);

                if ($activity) {
                    $recordedActivities[] = $activity->name;
                }
            }
        }

        if (empty($recordedActivities) && !empty($alreadyRecorded)) {
            $this->setLastScan($cardNumber, $student, 'duplicate', 'Sudah tercatat', $alreadyRecorded);
        } else {
            $this->setLastScan($cardNumber, $student, 'success', null, $recordedActivities, $alreadyRecorded);
        }

        $this->scanInput = '';
    }

    private function setLastScan($cardNumber, $student, $status, $errorMessage, $activities, $alreadyRecorded = [])
    {
        $this->lastScan = [
            'card_number' => $cardNumber,
            'student_name' => $student ? $student->full_name : 'Tidak Diketahui',
            'student_level' => $student ? $student->level->name ?? '' : '',
            'status' => $status,
            'error_message' => $errorMessage,
            'activities' => $activities,
            'already_recorded' => $alreadyRecorded,
            'timestamp' => now()->format('H:i:s'),
        ];
    }

    public function endSession()
    {
        session()->forget(['scanner_date', 'scanner_levels', 'scanner_activities']);
        Flux::toast(variant: 'success', heading: 'Sesi Selesai', text: 'Sesi absensi telah ditutup.');
        return redirect()->route('activities.attendance.index');
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = auth()->user()->school_id;

        $levels = Level::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->whereIn('id', $this->selectedLevels)
            ->get();

        $activities = Activity::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->whereIn('id', $this->selectedActivities)
            ->get();

        return [
            'levels' => $levels,
            'activities' => $activities,
        ];
    }
}; ?>

@assets
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
@endassets

<div class="min-h-screen flex flex-col items-center justify-center p-6 text-zinc-800 dark:text-zinc-100">
    <div class="w-full max-w-4xl space-y-8">
        {{-- Header --}}
        <div class="text-center space-y-2">
            <flux:heading size="xl" level="1">Scanner Presensi</flux:heading>
            <flux:subheading>
                {{ \Carbon\Carbon::parse($sessionDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}
            </flux:subheading>

            <div class="flex flex-wrap justify-center gap-2 mt-4">
                @foreach($levels as $level)
                    <flux:badge size="sm" color="zinc">{{ $level->name }}</flux:badge>
                @endforeach
            </div>
        </div>

        {{-- Result Card --}}
        <div class="relative">
            @if($lastScan)
                @php
                    $statusColor = match ($lastScan['status']) {
                        'success' => 'green',
                        'duplicate' => 'blue',
                        'error' => 'red',
                        default => 'zinc'
                    };
                @endphp

                <div class="rounded-xl border-2 p-8 text-center space-y-6
                    @if($lastScan['status'] === 'success')
                        bg-green-50 border-green-500 dark:bg-green-950 dark:border-green-500
                    @elseif($lastScan['status'] === 'duplicate')
                        bg-blue-50 border-blue-500 dark:bg-blue-950 dark:border-blue-500
                    @else
                        bg-red-50 border-red-500 dark:bg-red-950 dark:border-red-500
                    @endif">
                    <div>
                        <flux:badge size="lg" color="{{ $statusColor }}" class="mb-4">
                            @if($lastScan['status'] === 'success')
                                BERHASIL
                            @elseif($lastScan['status'] === 'duplicate')
                                SUDAH TERCATAT
                            @else
                                {{ strtoupper($lastScan['error_message']) }}
                            @endif
                        </flux:badge>

                        <h2 class="text-5xl font-bold mb-2
                            @if($lastScan['status'] === 'success') text-green-900 dark:text-green-100
                            @elseif($lastScan['status'] === 'duplicate') text-blue-900 dark:text-blue-100
                            @else text-red-900 dark:text-red-100
                            @endif">
                            {{ $lastScan['student_name'] }}
                        </h2>
                        <div class="text-2xl
                            @if($lastScan['status'] === 'success') text-green-800 dark:text-green-200
                            @elseif($lastScan['status'] === 'duplicate') text-blue-800 dark:text-blue-200
                            @else text-red-800 dark:text-red-200
                            @endif">
                            {{ $lastScan['student_level'] }}
                        </div>
                    </div>

                    @if(!empty($lastScan['activities']))
                        <div class="py-4 border-t border-green-200 dark:border-green-600">
                            <div class="text-sm text-green-800 dark:text-green-200 mb-2 font-medium">Kegiatan Tercatat</div>
                            <div class="flex flex-wrap justify-center gap-2">
                                @foreach($lastScan['activities'] as $activity)
                                    <flux:badge color="green" icon="check">{{ $activity }}</flux:badge>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!empty($lastScan['already_recorded']))
                        <div class="py-4 border-t border-zinc-200 dark:border-zinc-600">
                            <div class="text-sm text-zinc-700 dark:text-zinc-300 mb-2 font-medium">Sebelumnya Tercatat</div>
                            <div class="flex flex-wrap justify-center gap-2">
                                @foreach($lastScan['already_recorded'] as $activity)
                                    <flux:badge color="blue" icon="information-circle">{{ $activity }}</flux:badge>
                                @endforeach
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
                    <p class="text-zinc-500 dark:text-zinc-400 mt-2">Silakan scan kartu santri pada alat scanner</p>
                </div>
            @endif
        </div>

        {{-- Camera Scanner --}}
        <div x-data="{ 
            showCamera: false,
            html5QrCode: null,
            initScanner() {
                this.html5QrCode = new Html5Qrcode('reader');
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
                <div id="reader" class="w-full h-full"></div>
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
                Kembali ke Dashboard
            </flux:button>
        </div>
    </div>
</div>