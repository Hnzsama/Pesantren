<?php

use App\Models\School;
use App\Models\Student;
use App\Services\StudentImportService;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Rule;

use App\Models\Department;
use App\Models\Level;
use Livewire\Attributes\Computed;

new #[Lazy] class extends Component {
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

    // use WithFileUploads; 

    public int $step = 1;
    public string $uploadedFilePath = ''; // Temp path storage

    // New Context Properties
    public string $department_id = '';
    public ?string $level_id = null;

    public $simulationStats = [];
    public $simulationDetails = [];
    public $schoolId;

    public function mount()
    {
        if (auth()->check() && auth()->user()->school_id) {
            $this->schoolId = auth()->user()->school_id;
        } else {
            $this->schoolId = School::first()?->id;
        }
    }

    public function updatedDepartmentId()
    {
        $this->level_id = null; // Reset level when department changes
    }

    #[Computed]
    public function departments()
    {
        return Department::where('school_id', $this->schoolId)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function levels()
    {
        if (empty($this->department_id)) {
            return collect();
        }

        return Level::where('department_id', $this->department_id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();
    }

    public function handleFileUpload(string $base64, string $fileName)
    {
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64)[1];
        }
        $content = base64_decode($base64);

        $path = 'temp-imports/' . Str::random(40) . '_' . $fileName;
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $content);

        $this->uploadedFilePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
    }

    public function nextStep()
    {
        if ($this->step === 1) {
            $this->validate([
                'uploadedFilePath' => 'required',
                'department_id' => 'required|exists:departments,id',
                'level_id' => 'required|exists:levels,id',
            ], [
                'uploadedFilePath.required' => 'Silakan upload file terlebih dahulu.',
                'department_id.required' => 'Pilih jenjang pendidikan.',
                'level_id.required' => 'Pilih kelas.',
            ]);
            $this->processUpload();
        }
        $this->step++;
    }

    public function previousStep()
    {
        $this->step--;
    }

    public function processUpload()
    {
        $path = $this->uploadedFilePath;
        $data = array_map('str_getcsv', file($path));
        $header = array_shift($data);

        $headerMap = [];
        foreach ($header as $idx => $col) {
            $col = strtolower(trim($col));

            // Cek Gender dulu karena 'jenis' mengandung 'nis'
            if (str_contains($col, 'jenis') || str_contains($col, 'gender') || str_contains($col, 'kelamin'))
                $headerMap['gender'] = $idx;
            elseif (str_contains($col, 'nama') && !str_contains($col, 'orang') && !str_contains($col, 'wali'))
                $headerMap['name'] = $idx;
            elseif ($col === 'nis' || $col === 'no_induk' || (str_contains($col, 'nis') && !str_contains($col, 'jenis')))
                $headerMap['nis'] = $idx;
            elseif (str_contains($col, 'hp') || str_contains($col, 'telp') || str_contains($col, 'phone'))
                $headerMap['parent_phone'] = $idx;
            elseif (str_contains($col, 'orang') || str_contains($col, 'wali') || str_contains($col, 'parent'))
                $headerMap['parent_name'] = $idx;
        }

        $formattedRows = [];
        foreach ($data as $row) {
            // Ensure we have at least a name column content
            if (isset($headerMap['name']) && isset($row[$headerMap['name']])) {
                $formattedRows[] = [
                    'name' => $row[$headerMap['name']] ?? '',
                    'nis' => isset($headerMap['nis']) ? ($row[$headerMap['nis']] ?? '') : '',
                    'parent_phone' => isset($headerMap['parent_phone']) ? ($row[$headerMap['parent_phone']] ?? '') : '',
                    'parent_name' => isset($headerMap['parent_name']) ? ($row[$headerMap['parent_name']] ?? '') : '',
                    'gender' => isset($headerMap['gender']) ? ($row[$headerMap['gender']] ?? 'L') : 'L',
                ];
            }
        }

        $service = new StudentImportService();
        $results = $service->simulateImport($formattedRows, $this->schoolId);

        $this->simulationStats = array_diff_key($results, ['details' => '']);
        $this->simulationDetails = $results['details'];
    }

    public function executeImport()
    {
        $service = new StudentImportService();

        DB::transaction(function () use ($service) {
            foreach ($this->simulationDetails as $detail) {
                // Determine NIS
                $nis = $detail['student']['nis'];
                if (empty($nis)) {
                    // Auto Generate
                    $nis = $service->generateNis($this->schoolId);
                }

                $parentUserId = $detail['selected_parent_id'];
                $needsVerification = $parentUserId ? false : true;

                // Normalize Gender
                $rawGender = strtoupper(substr($detail['student']['gender'] ?? 'L', 0, 1));
                $gender = in_array($rawGender, ['L', 'P']) ? $rawGender : 'L';

                $studentData = [
                    'school_id' => $this->schoolId,
                    'full_name' => $detail['student']['name'],
                    'nis' => $nis,
                    'status' => 'ACTIVE',
                    'gender' => $gender,
                    'allow_transaction' => true,
                    'parent_user_id' => $parentUserId,
                    'needs_parent_verification' => $needsVerification,
                    'level_id' => $this->level_id, // Add Level ID
                ];

                Student::updateOrCreate(
                    ['nis' => $nis, 'school_id' => $this->schoolId],
                    $studentData
                );
            }
        });

        Flux::toast('Import berhasil dieksekusi.', variant: 'success');
        $this->redirect(route('students.index'), navigate: true);
    }

}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Import Santri') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Import data santri dari file Excel/CSV') }}
                </flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button variant="ghost" icon="arrow-left" href="{{ route('students.index') }}" wire:navigate>
                    {{ __('Kembali') }}
                </flux:button>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Wizard Steps -->

    <!-- Wizard Steps -->
    <div class="mb-12">
        <div class="flex items-center w-full">
            @foreach ([1 => 'Upload File', 2 => 'Preview & Link', 3 => 'Eksekusi'] as $s => $label)
                    <div class="relative flex flex-col items-center z-10 shrink-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition-colors duration-200 border-2 
                                {{ $step >= $s
                ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-500'
                : 'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400' 
                                }}">
                            {{ $s }}
                        </div>
                        <div class="absolute top-10 left-1/2 -translate-x-1/2 w-max text-center">
                            <span
                                class="text-xs font-medium {{ $step >= $s ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-500' }}">
                                {{ $label }}
                            </span>
                        </div>
                    </div>

                    @if ($s < 3)
                        <div
                            class="flex-1 h-0.5 mx-2 rounded {{ $step > $s ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-zinc-200 dark:bg-zinc-700' }}">
                        </div>
                    @endif
            @endforeach
        </div>
    </div>

    <!-- Step 1: Upload -->
    @if ($step === 1)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Configurations -->
            <div class="lg:col-span-1 space-y-6">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Konfigurasi</flux:heading>

                    <div class="space-y-4">
                        <flux:select variant="listbox" wire:model.live="department_id" label="Jenjang Pendidikan"
                            placeholder="Pilih Jenjang">
                            <flux:select.option value="">Pilih Jenjang</flux:select.option>
                            @foreach ($this->departments as $dept)
                                <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select variant="listbox" wire:model="level_id" label="Kelas" placeholder="Pilih Kelas"
                            :disabled="empty($department_id)">
                            <flux:select.option value="">Pilih Kelas</flux:select.option>
                            @foreach ($this->levels as $level)
                                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </flux:card>

                <flux:card class="bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800/30">
                    <div class="flex gap-3">
                        <flux:icon name="information-circle" class="text-amber-600 w-5 h-5 flex-shrink-0" />
                        <div class="text-sm text-amber-800 dark:text-amber-200">
                            <p class="font-medium mb-1">Ketentuan Format File</p>
                            <ul class="list-disc pl-4 space-y-1 opacity-90">
                                <li>Format file harus <strong>.csv</strong> atau <strong>.xlsx</strong>.</li>
                                <li>Pastikan header kolom berikut ada:
                                    <code class="px-1 py-0.5 bg-white/50 rounded font-mono text-xs">nama</code>,
                                    <code class="px-1 py-0.5 bg-white/50 rounded font-mono text-xs">nis</code> (opsional),
                                    <code class="px-1 py-0.5 bg-white/50 rounded font-mono text-xs">gender</code>,
                                    <code class="px-1 py-0.5 bg-white/50 rounded font-mono text-xs">no_hp_ortu</code>.
                                </li>
                                <li>Link orang tua otomatis berdasarkan No HP.</li>
                            </ul>
                        </div>
                    </div>
                </flux:card>
            </div>

            <!-- Right: Upload Area -->
            <div class="lg:col-span-2">
                <flux:card class="h-full flex flex-col">
                    <flux:heading size="lg" class="mb-4">Upload Data</flux:heading>

                    <div class="flex-1 flex flex-col justify-center space-y-6" x-data="{
                                            isUploading: false,
                                            fileName: null,
                                            handleFile(e) {
                                                const file = e.target.files[0];
                                                if (!file) return;

                                                this.isUploading = true;
                                                this.fileName = file.name;

                                                const reader = new FileReader();
                                                reader.onload = (e) => {
                                                    $wire.handleFileUpload(e.target.result, file.name)
                                                            .then(() => { this.isUploading = false; });
                                                };
                                                reader.readAsDataURL(file);
                                            }
                                        }">
                        <div
                            class="relative border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl p-8 text-center hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <input type="file" accept=".csv, .xlsx, .xls" @change="handleFile"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />

                            <div class="space-y-4">
                                <div
                                    class="mx-auto w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                                    <flux:icon name="arrow-up-tray" class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        <span class="text-indigo-600 dark:text-indigo-400">Klik untuk upload</span> atau
                                        drag and drop
                                    </p>
                                    <p class="text-xs text-zinc-500 mt-1">CSV, Excel (Max 5MB)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Progress / Status -->
                        <div x-show="isUploading"
                            class="flex items-center gap-3 p-3 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300">
                            <flux:icon name="arrow-path" class="w-5 h-5 animate-spin" />
                            <span class="text-sm font-medium">Sedang memproses file...</span>
                        </div>

                        @if ($uploadedFilePath)
                            <div
                                class="flex items-center justify-between p-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/30 text-emerald-700 dark:text-emerald-300">
                                <div class="flex items-center gap-3">
                                    <flux:icon name="document-text" class="w-5 h-5" />
                                    <div>
                                        <p class="text-sm font-medium">{{ basename($uploadedFilePath) }}</p>
                                        <p class="text-xs opacity-80">File siap diproses</p>
                                    </div>
                                </div>
                                <flux:icon name="check-circle" class="w-6 h-6" />
                            </div>
                            <flux:error name="uploadedFilePath" />
                        @endif
                    </div>

                    <div class="mt-6 flex justify-end">
                        <flux:button wire:click="nextStep" variant="primary" icon-trailing="arrow-right"
                            :disabled="!$uploadedFilePath">
                            Lanjut Preview
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    @endif

    <!-- Step 2: Simulation -->
    @if ($step === 2)
        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <flux:card class="text-center">
                    <flux:subheading>Total Data</flux:subheading>
                    <div class="text-3xl font-bold mt-2 text-zinc-900 dark:text-white">
                        {{ $simulationStats['total_rows'] ?? 0 }}
                    </div>
                </flux:card>
                <flux:card
                    class="text-center border-emerald-200 bg-emerald-50/50 dark:border-emerald-800 dark:bg-emerald-900/10">
                    <flux:subheading class="text-emerald-600 dark:text-emerald-400">Terhubung ke Wali</flux:subheading>
                    <div class="text-3xl font-bold mt-2 text-emerald-700 dark:text-emerald-300">
                        {{ $simulationStats['matched'] ?? 0 }}
                    </div>
                </flux:card>
                <flux:card class="text-center border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-900/10">
                    <flux:subheading class="text-amber-600 dark:text-amber-400">Tanpa Wali (Unlinked)</flux:subheading>
                    <div class="text-3xl font-bold mt-2 text-amber-700 dark:text-amber-300">
                        {{ $simulationStats['orphaned'] ?? 0 }}
                    </div>
                </flux:card>
            </div>

            <flux:card>
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg">Preview Data</flux:heading>
                    <flux:badge size="sm" color="zinc">Menampilkan semua baris</flux:badge>
                </div>

                <div class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>NIS</flux:table.column>
                            <flux:table.column>Nama Santri</flux:table.column>
                            <flux:table.column>Gender</flux:table.column>
                            <flux:table.column>No HP (CSV)</flux:table.column>
                            <flux:table.column>Status Link</flux:table.column>
                            <flux:table.column>Orang Tua (System)</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($simulationDetails as $detail)
                                <flux:table.row>
                                    <flux:table.cell>
                                        @if(empty($detail['student']['nis']))
                                            <flux:badge size="xs" color="indigo" variant="pill" icon="sparkles">AUTO</flux:badge>
                                        @else
                                            <span class="font-mono text-sm">{{ $detail['student']['nis'] }}</span>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="font-medium">{{ $detail['student']['name'] }}</div>
                                        @if($detail['action'] == 'UPDATE')
                                            <div class="text-xs text-blue-600">Update Data</div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="xs" :color="$detail['student']['gender'] == 'L' ? 'blue' : 'pink'">
                                            {{ $detail['student']['gender'] }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="font-mono text-zinc-500">
                                        {{ $detail['parent_input']['phone'] ?: '-' }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($detail['match_status'] === 'MATCHED')
                                            <flux:badge size="sm" color="emerald" icon="link">Linked</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" icon="link-slash">Unlinked</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($detail['found_parent'])
                                            <div class="flex items-center gap-2">
                                                <flux:icon name="user" size="xs" class="text-emerald-500" />
                                                <span
                                                    class="font-medium text-emerald-700 dark:text-emerald-400">{{ $detail['found_parent']['name'] }}</span>
                                            </div>
                                        @else
                                            <span class="text-xs text-zinc-400 italic">Tidak ditemukan</span>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                <div class="flex justify-between pt-6 mt-6 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="previousStep" variant="ghost" icon="arrow-left">Kembali</flux:button>
                    <flux:button wire:click="executeImport" variant="primary" icon-trailing="check">Eksekusi Import
                    </flux:button>
                </div>
            </flux:card>
        </div>
    @endif
</div>