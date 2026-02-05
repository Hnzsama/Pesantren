<?php

use App\Models\ActivityAttendance;
use App\Models\Activity;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentCard;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Flux\Flux;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    // List view filters
    public $dateFilter;
    public $activityFilter;
    public $levelFilter;

    // Scanner modal state
    public $showScannerModal = false;
    public $sessionActive = false;

    // Session setup
    public $sessionDate;
    public $selectedLevels = [];
    public $selectedActivities = [];

    // Scanning
    public $scanInput = '';
    public $scannedRecords = [];

    // Session Detail Flyout
    public $selectedSession = null;
    public $sessionDetails = [];

    public function mount()
    {
        $this->dateFilter = Carbon::today()->format('Y-m-d');
        $this->sessionDate = Carbon::today()->format('Y-m-d');
    }

    public function updatedLevelFilter()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        // Sessions for list view
        $sessions = ActivityAttendance::query()
            ->select('date', 'activity_id', 'school_id')
            ->distinct()
            ->with(['activity.level'])
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->dateFilter, fn($q) => $q->where('date', $this->dateFilter))
            ->when($this->activityFilter, fn($q) => $q->where('activity_id', $this->activityFilter))
            ->when($this->levelFilter, function ($q) {
                $q->whereHas('student', fn($sq) => $sq->where('level_id', $this->levelFilter));
            })
            ->orderBy('date', 'desc')
            ->orderBy('activity_id')
            ->paginate(10);

        // Levels for filters and scanner
        $levels = Level::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();

        // Activities for filters
        $activities = Activity::query()
            ->with('level')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();

        // Activities for scanner (filtered by selected levels)
        $availableActivities = [];
        if (!empty($this->selectedLevels)) {
            $availableActivities = Activity::query()
                ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->whereIn('level_id', $this->selectedLevels)
                ->orderBy('name')
                ->get();
        }

        return [
            'sessions' => $sessions,
            'levels' => $levels,
            'activities' => $activities,
            'availableActivities' => $availableActivities,
        ];
    }

    public function getAttendanceCount($date, $activityId)
    {
        return ActivityAttendance::where('date', $date)
            ->where('activity_id', $activityId)
            ->when($this->levelFilter, function ($q) {
                $q->whereHas('student', fn($sq) => $sq->where('level_id', $this->levelFilter));
            })
            ->count();
    }

    public function viewSession($date, $activityId)
    {
        $this->selectedSession = [
            'date' => $date,
            'activity' => Activity::find($activityId),
        ];

        $this->sessionDetails = ActivityAttendance::with(['student'])
            ->where('date', $date)
            ->where('activity_id', $activityId)
            ->get();

        $this->modal('session-detail-modal')->show();
    }

    public function openScannerModal()
    {
        $this->reset(['sessionActive', 'selectedLevels', 'selectedActivities', 'scanInput', 'scannedRecords']);
        $this->sessionDate = Carbon::today()->format('Y-m-d');
        $this->showScannerModal = true;
        $this->modal('scanner-modal')->show();
    }

    public function startSession()
    {
        $this->validate([
            'sessionDate' => 'required|date',
            'selectedLevels' => 'required|array|min:1',
            'selectedActivities' => 'required|array|min:1',
        ], [
            'selectedLevels.required' => 'Pilih minimal 1 kelas',
            'selectedActivities.required' => 'Pilih minimal 1 kegiatan',
        ]);

        // Store session data
        session([
            'scanner_date' => $this->sessionDate,
            'scanner_levels' => $this->selectedLevels,
            'scanner_activities' => $this->selectedActivities,
        ]);

        // Session saved, JavaScript will handle opening
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
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        // Find student card by card_number
        $studentCard = StudentCard::query()
            ->with('student')
            ->where('card_number', $cardNumber)
            ->first();

        if (!$studentCard || !$studentCard->student) {
            $this->addScanRecord($cardNumber, null, 'error', 'Kartu tidak ditemukan', []);
            $this->scanInput = '';
            return;
        }

        $student = $studentCard->student;

        // Check if card is active (printed and not expired)
        if ($studentCard->status !== 'printed') {
            $this->addScanRecord($cardNumber, $student, 'error', 'Kartu belum dicetak', []);
            $this->scanInput = '';
            return;
        }

        if ($studentCard->expires_at && $studentCard->expires_at < now()) {
            $this->addScanRecord($cardNumber, $student, 'error', 'Kartu sudah expired', []);
            $this->scanInput = '';
            return;
        }

        // Check if student is in selected levels
        if (!in_array($student->level_id, $this->selectedLevels)) {
            $this->addScanRecord($cardNumber, $student, 'error', 'Santri bukan dari kelas yang dipilih', []);
            $this->scanInput = '';
            return;
        }

        // Create attendance records for all selected activities
        $recordedActivities = [];
        foreach ($this->selectedActivities as $activityId) {
            ActivityAttendance::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'activity_id' => $activityId,
                    'date' => $this->sessionDate,
                ],
                [
                    'status' => 'present',
                ]
            );

            $activity = Activity::find($activityId);
            if ($activity) {
                $recordedActivities[] = $activity->name;
            }
        }

        $this->addScanRecord($cardNumber, $student, 'success', null, $recordedActivities);
        $this->scanInput = '';
    }

    private function addScanRecord($cardNumber, $student, $status, $errorMessage, $activities)
    {
        array_unshift($this->scannedRecords, [
            'card_number' => $cardNumber,
            'student_name' => $student ? $student->full_name : 'Unknown',
            'status' => $status,
            'error_message' => $errorMessage,
            'activities' => $activities,
            'timestamp' => now()->format('H:i:s'),
        ]);

        // Keep only last 50 records
        if (count($this->scannedRecords) > 50) {
            array_pop($this->scannedRecords);
        }
    }

    public function endSession()
    {
        $this->reset(['sessionActive', 'selectedLevels', 'selectedActivities', 'scanInput', 'scannedRecords']);
        $this->showScannerModal = false;
        $this->modal('scanner-modal')->close();

        Flux::toast(variant: 'success', heading: 'Sesi Selesai', text: 'Sesi absensi telah diakhiri.');
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Riwayat Presensi Kegiatan</flux:heading>
                <flux:subheading size="lg" class="mb-6">Daftar pencatatan presensi kegiatan harian.</flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('activities.attendance.detail') }}" icon="table-cells" wire:navigate>
                    Detail Harian
                </flux:button>
                <flux:button variant="primary" icon="qr-code" wire:click="openScannerModal">
                    Catat Presensi Baru
                </flux:button>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    {{-- Filters --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input type="date" wire:model.live="dateFilter" label="Tanggal" />

        <flux:select wire:model.live="levelFilter" label="Kelas" variant="listbox" placeholder="Pilih Kelas...">
            <flux:select.option value="">Semua Kelas</flux:select.option>
            @foreach($levels as $level)
                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="activityFilter" label="Kegiatan" variant="listbox" placeholder="Semua Kegiatan">
            <flux:select.option value="">Semua Kegiatan</flux:select.option>
            @foreach($activities->unique(fn($a) => $a->name . ($a->level_id ?? '')) as $activity)
                <flux:select.option value="{{ $activity->id }}">
                    {{ $activity->name }} - {{ $activity->level->name ?? 'Semua Kelas' }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <div class="space-y-4">
        <flux:table :paginate="$sessions">
            <flux:table.columns>
                <flux:table.column>Tanggal</flux:table.column>
                <flux:table.column>Kegiatan</flux:table.column>
                <flux:table.column>Jumlah Tercatat</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($sessions as $session)
                    <flux:table.row :key="$session->date . $session->activity_id">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar-days class="w-4 h-4 text-zinc-400" />
                                {{ \Carbon\Carbon::parse($session->date)->format('d F Y') }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $session->activity->name ?? '-' }}
                            </div>
                            <div class="text-xs text-zinc-500">
                                {{ $session->activity->level->name ?? 'Semua Kelas' }} •
                                {{ $session->activity->type ?? '' }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">
                                {{ $this->getAttendanceCount($session->date, $session->activity_id) }} Santri
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:button wire:click="viewSession('{{ $session->date }}', '{{ $session->activity_id }}')"
                                icon="eye" size="sm" variant="ghost">
                                Detail
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8">
                            <div class="text-zinc-500">Belum ada riwayat presensi yang sesuai filter.</div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Scanner Modal --}}
    <flux:modal name="scanner-modal" class="w-full max-w-4xl">
        <div class="space-y-6">
            @if(!$sessionActive)
                {{-- Session Setup --}}
                <div>
                    <flux:heading size="lg">Mulai Sesi Absensi</flux:heading>
                    <flux:subheading>Pilih kelas dan kegiatan untuk sesi absensi</flux:subheading>
                </div>

                <flux:separator />

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Tanggal</flux:label>
                        <flux:input type="date" wire:model="sessionDate" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Kelas <span class="text-red-500">*</span></flux:label>
                        <flux:select variant="listbox" multiple wire:model.live="selectedLevels"
                            placeholder="Pilih Kelas (bisa lebih dari 1)">
                            @foreach($levels as $level)
                                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="selectedLevels" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Kegiatan <span class="text-red-500">*</span></flux:label>
                        <flux:select variant="listbox" multiple wire:model="selectedActivities"
                            placeholder="Pilih Kegiatan (bisa lebih dari 1)" :disabled="empty($selectedLevels)">
                            @foreach($availableActivities as $activity)
                                <flux:select.option value="{{ $activity->id }}">
                                    {{ $activity->name }} - {{ $activity->level->name ?? 'Semua Kelas' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="selectedActivities" />
                    </flux:field>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" icon="play" wire:click="startSession"
                        x-on:click="setTimeout(() => window.open('{{ route('activities.attendance.scan') }}', '_blank'), 500)">
                        Mulai Sesi Absen
                    </flux:button>
                </div>
            @else
                {{-- Scanning Active --}}
                <div>
                    <flux:heading size="lg">Sesi Absensi Aktif</flux:heading>
                    <flux:subheading>Scan kartu NIS santri</flux:subheading>
                </div>

                <flux:separator />

                {{-- Session Info --}}
                <div class="grid grid-cols-3 gap-4 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                    <div>
                        <div class="text-xs text-zinc-500 mb-1">Tanggal</div>
                        <div class="font-medium">{{ \Carbon\Carbon::parse($sessionDate)->format('d M Y') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 mb-1">Kelas</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($selectedLevels as $levelId)
                                @php $level = $levels->firstWhere('id', $levelId); @endphp
                                @if($level)
                                    <flux:badge size="sm">{{ $level->name }}</flux:badge>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 mb-1">Kegiatan</div>
                        <div class="text-sm">{{ count($selectedActivities) }} kegiatan dipilih</div>
                    </div>
                </div>

                {{-- Scanner Input --}}
                <div x-data="{ focused: true }" x-init="$nextTick(() => $refs.scanInput.focus())">
                    <flux:field>
                        <flux:label>Scan Kartu NIS</flux:label>
                        <flux:input wire:model.live="scanInput" placeholder="Scan kartu di sini..." x-ref="scanInput"
                            @click="$refs.scanInput.focus()" @blur="setTimeout(() => $refs.scanInput.focus(), 100)"
                            class="font-mono text-lg" />
                    </flux:field>
                </div>

                {{-- Scanned Records --}}
                @if(count($scannedRecords) > 0)
                    <div class="max-h-96 overflow-y-auto space-y-2">
                        <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                            Riwayat Scan ({{ count($scannedRecords) }})
                        </div>
                        @foreach($scannedRecords as $record)
                            <div
                                class="p-3 rounded-lg {{ $record['status'] === 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }}">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            @if($record['status'] === 'success')
                                                <flux:icon.check-circle class="w-5 h-5 text-green-600" />
                                            @else
                                                <flux:icon.x-circle class="w-5 h-5 text-red-600" />
                                            @endif
                                            <span class="font-medium">{{ $record['student_name'] }}</span>
                                            <span class="text-xs text-zinc-500">NIS: {{ $record['nis'] }}</span>
                                        </div>
                                        @if($record['status'] === 'success')
                                            <div class="text-sm text-green-700 dark:text-green-300 mt-1">
                                                Hadir untuk {{ count($record['activities']) }} kegiatan
                                            </div>
                                        @else
                                            <div class="text-sm text-red-700 dark:text-red-300 mt-1">
                                                {{ $record['error_message'] }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $record['timestamp'] }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button variant="danger" icon="stop" wire:click="endSession">Akhiri Sesi</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Detail Flyout --}}
    <flux:modal name="session-detail-modal" flyout class="space-y-6">
        @if($selectedSession)
            <div>
                <flux:heading size="lg">Detail Presensi</flux:heading>
                <flux:subheading>
                    {{ $selectedSession['activity']->name ?? '-' }} -
                    {{ \Carbon\Carbon::parse($selectedSession['date'])->format('d M Y') }}
                </flux:subheading>
            </div>

            <flux:separator />

            <div class="space-y-4">
                @forelse($sessionDetails as $detail)
                    <div
                        class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$detail->student->full_name" />
                            <div>
                                <div class="font-medium text-sm">{{ $detail->student->full_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $detail->student->nis }}</div>
                            </div>
                        </div>
                        <flux:badge size="sm" color="green">Hadir</flux:badge>
                    </div>
                @empty
                    <div class="text-center text-zinc-500 py-4">
                        Tidak ada data presensi.
                    </div>
                @endforelse
            </div>
        @endif
    </flux:modal>
</div>