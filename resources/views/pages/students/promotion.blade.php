<?php

use App\Models\School;
use App\Models\Department;
use App\Models\Level;
use App\Models\AcademicYear;
use App\Services\PromotionService;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Lazy] #[Layout('layouts.app')] class extends Component {
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

    public int $step = 1;

    // Step 1: Configuration
    public string $schoolId = '';
    public string $sourceAcademicYearId = '';
    public string $targetAcademicYearId = '';
    public string $departmentId = '';
    public array $levelIds = []; // Changed to array for multi-select

    // Step 2: Data
    public array $promotionData = [];
    public array $stats = [];

    public function mount()
    {
        // Auto-select school for non-super-admin
        if (!auth()->user()->hasRole('super_admin') && auth()->user()->school_id) {
            $this->schoolId = auth()->user()->school_id;
        }
    }

    public function updatedDepartmentId()
    {
        $this->levelIds = [];
    }

    #[Computed]
    public function schools()
    {
        return School::orderBy('name')->get();
    }

    #[Computed]
    public function academicYears()
    {
        if (!$this->schoolId)
            return collect();

        return AcademicYear::where('school_id', $this->schoolId)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    #[Computed]
    public function departments()
    {
        if (!$this->schoolId)
            return collect();

        return Department::where('school_id', $this->schoolId)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function levels()
    {
        if (!$this->departmentId)
            return collect();

        return Level::where('department_id', $this->departmentId)
            ->orderBy('grade_level')
            ->get();
    }

    public function nextStep()
    {
        if ($this->step === 1) {
            $this->validate([
                'schoolId' => 'required',
                'sourceAcademicYearId' => 'required',
                'targetAcademicYearId' => 'required|different:sourceAcademicYearId',
                'departmentId' => 'required',
                'levelIds' => 'required|array|min:1',
                'levelIds.*' => 'exists:levels,id',
            ], [
                'schoolId.required' => 'Pilih sekolah terlebih dahulu',
                'sourceAcademicYearId.required' => 'Pilih tahun ajaran asal',
                'targetAcademicYearId.required' => 'Pilih tahun ajaran tujuan',
                'targetAcademicYearId.different' => 'Tahun ajaran tujuan harus berbeda dengan tahun asal',
                'departmentId.required' => 'Pilih jenjang pendidikan',
                'levelIds.required' => 'Pilih minimal satu kelas',
                'levelIds.min' => 'Pilih minimal satu kelas',
            ]);

            $this->loadPromotionData();
        }

        $this->step++;
    }

    public function previousStep()
    {
        $this->step--;
    }

    public function loadPromotionData()
    {
        $service = new PromotionService();
        $allData = [];

        // Load students from all selected levels
        foreach ($this->levelIds as $levelId) {
            $students = $service->getStudentsForPromotion($levelId);
            $data = $service->preparePromotionData($students);
            $allData = array_merge($allData, $data);
        }

        $this->promotionData = $allData;
        $this->stats = $service->calculateStats($this->promotionData);
    }

    public function updateAction($index, $action)
    {
        if (isset($this->promotionData[$index])) {
            $this->promotionData[$index]['action'] = $action;

            // Update target based on action
            if ($action === 'RETAIN') {
                $this->promotionData[$index]['target_level_id'] = $this->promotionData[$index]['current_level_id'];
                $this->promotionData[$index]['target_level_name'] = $this->promotionData[$index]['current_level_name'];
            } elseif ($action === 'GRADUATE') {
                $this->promotionData[$index]['target_level_id'] = null;
                $this->promotionData[$index]['target_level_name'] = null;
            }

            // Recalculate stats
            $service = new PromotionService();
            $this->stats = $service->calculateStats($this->promotionData);
        }
    }

    public function promoteAll()
    {
        foreach ($this->promotionData as $index => $data) {
            // Only change if has next level
            if ($data['target_level_id']) {
                $this->promotionData[$index]['action'] = 'PROMOTE';
            }
        }

        $service = new PromotionService();
        $this->stats = $service->calculateStats($this->promotionData);
    }

    public function graduateAll()
    {
        foreach ($this->promotionData as $index => $data) {
            $this->promotionData[$index]['action'] = 'GRADUATE';
            $this->promotionData[$index]['target_level_id'] = null;
            $this->promotionData[$index]['target_level_name'] = null;
        }

        $service = new PromotionService();
        $this->stats = $service->calculateStats($this->promotionData);
    }

    public function executePromotion()
    {
        try {
            $service = new PromotionService();
            $result = $service->executePromotion(
                $this->promotionData,
                $this->targetAcademicYearId,
                $this->sourceAcademicYearId,
                auth()->id()
            );

            Flux::toast('Kenaikan kelas berhasil dieksekusi!', variant: 'success');
            $this->redirect(route('students.index'), navigate: true);
        } catch (\Exception $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">Kenaikan Kelas</flux:heading>
                <flux:subheading size="lg">Kelola promosi santri ke level berikutnya</flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button variant="ghost" icon="arrow-left" href="{{ route('students.index') }}" wire:navigate>
                    Kembali
                </flux:button>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Wizard Steps -->
    <div class="mb-12">
        <div class="flex items-center w-full">
            @foreach ([1 => 'Konfigurasi', 2 => 'Preview'] as $s => $label)
                    <div class="relative flex flex-col items-center z-10 shrink-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition-colors duration-200 border-2 
                                                                                            {{ $step >= $s
                ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-500'
                : 'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400' 
                                                                                            }}">
                            {{ $s }}
                        </div>
                        <div class="absolute top-10 left-1/2 -translate-x-1/2 w-max text-center max-w-[100px]">
                            <span
                                class="text-xs font-medium {{ $step >= $s ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-500' }}">
                                {{ $label }}
                            </span>
                        </div>
                    </div>

                    @if ($s < 2)
                        <div
                            class="flex-1 h-0.5 mx-2 rounded {{ $step > $s ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-zinc-200 dark:bg-zinc-700' }}">
                        </div>
                    @endif
            @endforeach
        </div>
    </div>

    <!-- Step 1: Configuration -->
    @if ($step === 1)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Configuration Form -->
            <div class="lg:col-span-1 space-y-6">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Konfigurasi</flux:heading>

                    <div class="space-y-4">
                        @if(auth()->user()->hasRole('super_admin'))
                            <flux:select variant="listbox" wire:model.live="schoolId" label="Sekolah"
                                placeholder="Pilih Sekolah">
                                <flux:select.option value="">Pilih Sekolah</flux:select.option>
                                @foreach ($this->schools as $school)
                                    <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="schoolId" />
                        @endif

                        <flux:select variant="listbox" wire:model="sourceAcademicYearId"
                            label="Tahun Ajaran Asal (Saat Ini)" placeholder="Pilih Tahun Asal" :disabled="!$schoolId">
                            <flux:select.option value="">Pilih Tahun Asal</flux:select.option>
                            @foreach ($this->academicYears as $year)
                                <flux:select.option value="{{ $year->id }}">{{ $year->name }}
                                    {{ $year->is_active ? '(Aktif)' : '' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="sourceAcademicYearId" />

                        <flux:select variant="listbox" wire:model="targetAcademicYearId" label="Tahun Ajaran Tujuan (Baru)"
                            placeholder="Pilih Tahun Tujuan" :disabled="!$schoolId">
                            <flux:select.option value="">Pilih Tahun Tujuan</flux:select.option>
                            @foreach ($this->academicYears as $year)
                                <flux:select.option value="{{ $year->id }}">{{ $year->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="targetAcademicYearId" />

                        <flux:select variant="listbox" wire:model.live="departmentId" label="Jenjang Pendidikan"
                            placeholder="Pilih Jenjang" :disabled="!$schoolId">
                            <flux:select.option value="">Pilih Jenjang</flux:select.option>
                            @foreach ($this->departments as $dept)
                                <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="departmentId" />

                        <flux:select variant="listbox" wire:model="levelIds" label="Kelas" placeholder="Pilih Kelas"
                            :disabled="!$departmentId" multiple>
                            @foreach ($this->levels as $level)
                                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="levelIds" />
                    </div>
                </flux:card>

                <flux:card class="bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800/30">
                    <div class="flex gap-3">
                        <flux:icon name="information-circle" class="text-amber-600 w-5 h-5 flex-shrink-0" />
                        <div class="text-sm text-amber-800 dark:text-amber-200">
                            <p class="font-medium mb-1">Informasi Kenaikan Kelas</p>
                            <ul class="list-disc pl-4 space-y-1 opacity-90">
                                <li>Anda dapat memilih beberapa kelas sekaligus.</li>
                                <li>Semua santri aktif di kelas terpilih akan dimuat.</li>
                                <li>Sistem akan otomatis menentukan level tujuan.</li>
                                <li>Anda dapat mengubah aksi per santri di step berikutnya.</li>
                                <li>Aksi: <strong>Promosi</strong>, <strong>Tinggal Kelas</strong>, atau
                                    <strong>Lulus</strong>.
                                </li>
                            </ul>
                        </div>
                    </div>
                </flux:card>
            </div>

            <!-- Right: Placeholder -->
            <div class="lg:col-span-2">
                <flux:card class="h-full flex flex-col items-center justify-center py-12">
                    <div class="text-center space-y-4">
                        <div
                            class="mx-auto w-16 h-16 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                            <flux:icon name="academic-cap" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <flux:heading size="lg">Siap Memulai</flux:heading>
                            <flux:text>Lengkapi konfigurasi di sebelah kiri, lalu klik <strong>Muat Data</strong>
                            </flux:text>
                        </div>
                    </div>

                    <div class="mt-8">
                        <flux:button wire:click="nextStep" variant="primary" icon-trailing="arrow-right">
                            Muat Data
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    @endif

    <!-- Step 2: Preview & Execute -->
    @if ($step === 2)
        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <flux:card class="text-center">
                    <flux:subheading>Total Santri</flux:subheading>
                    <div class="text-3xl font-bold mt-2 text-zinc-900 dark:text-white">
                        {{ $stats['total'] ?? 0 }}
                    </div>
                </flux:card>
                <flux:card
                    class="text-center border-indigo-200 bg-indigo-50/50 dark:border-indigo-800 dark:bg-indigo-900/10">
                    <flux:subheading class="text-indigo-600 dark:text-indigo-400">Naik Kelas</flux:subheading>
                    <div class="text-3xl font-bold mt-2 text-indigo-700 dark:text-indigo-300">
                        {{ $stats['promoted'] ?? 0 }}
                    </div>
                </flux:card>
                <flux:card class="text-center border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-900/10">
                    <flux:subheading class="text-amber-600 dark:text-amber-400">Tinggal Kelas</flux:subheading>
                    <div class="text-3xl font-bold mt-2 text-amber-700 dark:text-amber-300">
                        {{ $stats['retained'] ?? 0 }}
                    </div>
                </flux:card>
                <flux:card
                    class="text-center border-violet-200 bg-violet-50/50 dark:border-violet-800 dark:bg-violet-900/10">
                    <flux:subheading class="text-violet-600 dark:text-violet-400">Lulus</flux:subheading>
                    <div class="text-3xl font-bold mt-2 text-violet-700 dark:text-violet-300">
                        {{ $stats['graduated'] ?? 0 }}
                    </div>
                </flux:card>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Preview Data Kenaikan Kelas</flux:heading>
                <div class="flex gap-2">
                    <flux:button wire:click="promoteAll" size="sm" variant="ghost" icon="arrow-up-circle">Promosi Semua
                    </flux:button>
                    <flux:button wire:click="graduateAll" size="sm" variant="ghost" icon="academic-cap">Lulus Semua
                    </flux:button>
                </div>
            </div>

            <!-- Table without card -->
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nama Santri</flux:table.column>
                    <flux:table.column>Level Saat Ini</flux:table.column>
                    <flux:table.column>Aksi</flux:table.column>
                    <flux:table.column>Level Tujuan</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($promotionData as $index => $data)
                        <flux:table.row wire:key="promo-{{ $data['student_id'] }}">
                            <flux:table.cell>
                                <div>
                                    <div class="font-medium">{{ $data['student_name'] }}</div>
                                    <div class="text-xs text-zinc-500 font-mono">{{ $data['student_nis'] }}</div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">{{ $data['current_level_name'] }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:select variant="listbox" wire:model="promotionData.{{ $index }}.action"
                                    wire:change="updateAction({{ $index }}, $event.target.value)">
                                    <flux:select.option value="PROMOTE">Naik Kelas</flux:select.option>
                                    <flux:select.option value="RETAIN">Tinggal Kelas</flux:select.option>
                                    <flux:select.option value="GRADUATE">Lulus</flux:select.option>
                                </flux:select>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($data['action'] === 'PROMOTE' && $data['target_level_name'])
                                    <flux:badge size="sm" color="indigo">{{ $data['target_level_name'] }}</flux:badge>
                                @elseif($data['action'] === 'GRADUATE')
                                    <flux:badge size="sm" color="violet">LULUS</flux:badge>
                                @elseif($data['action'] === 'RETAIN')
                                    <flux:badge size="sm" color="amber">{{ $data['current_level_name'] }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                                Tidak ada data santri.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <!-- Footer Actions -->
            <div class="flex justify-between pt-6">
                <flux:button wire:click="previousStep" variant="ghost" icon="arrow-left">Kembali</flux:button>
                <flux:button wire:click="executePromotion" variant="primary" icon-trailing="check">
                    Eksekusi Kenaikan Kelas
                </flux:button>
            </div>
        </div>
    @endif
</div>