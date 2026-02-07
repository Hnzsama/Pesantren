<?php

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

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

    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $academicYearFilter = '';

    public $semesterId = null;
    public $academic_year_id = '';
    public $type = 'GANJIL';
    public $start_date = '';
    public $end_date = '';

    public $semesterIdToDelete = null;

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = Semester::query()
            ->with(['academicYear'])
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->academicYearFilter, fn($q) => $q->where('academic_year_id', $this->academicYearFilter))
            ->when($this->search, fn($q) => $q->where('type', 'like', '%' . $this->search . '%'))
            ->orderBy('start_date', 'desc');

        $academicYears = AcademicYear::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name', 'desc')
            ->get();

        return [
            'semesters' => $query->paginate(10),
            'academicYears' => $academicYears,
        ];
    }

    public function create()
    {
        $this->reset(['semesterId', 'academic_year_id', 'type', 'start_date', 'end_date']);
        $this->academic_year_id = $this->academicYearFilter; // Pre-select if filter active
        $this->resetValidation();
        $this->modal('semester-modal')->show();
    }

    public function edit($id)
    {
        $semester = Semester::find($id);
        $this->semesterId = $semester->id;
        $this->academic_year_id = $semester->academic_year_id;
        $this->type = $semester->type;
        $this->start_date = $semester->start_date?->format('Y-m-d');
        $this->end_date = $semester->end_date?->format('Y-m-d');

        $this->modal('semester-modal')->show();
    }

    public function save()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $this->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'type' => [
                'required',
                'in:GANJIL,GENAP',
                Rule::unique('semesters')->where(function ($query) use ($schoolId) {
                    return $query->where('academic_year_id', $this->academic_year_id);
                })->ignore($this->semesterId)
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $data = [
            'academic_year_id' => $this->academic_year_id,
            'type' => $this->type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ];

        if (!$this->semesterId && $schoolId) {
            $data['school_id'] = $schoolId;
        }

        if ($this->semesterId) {
            Semester::find($this->semesterId)->update($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Semester berhasil diperbarui.');
        } else {
            Semester::create($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Semester berhasil ditambahkan.');
        }

        $this->modal('semester-modal')->close();
        $this->reset(['semesterId', 'academic_year_id', 'type', 'start_date', 'end_date']);
    }

    public function confirmDelete($id)
    {
        $this->semesterIdToDelete = $id;
        $this->modal('delete-semester-modal')->show();
    }

    public function destroy()
    {
        $semester = Semester::find($this->semesterIdToDelete);
        if ($semester) {
            $semester->delete();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Semester berhasil dihapus.');
        }
        $this->semesterIdToDelete = null;
        $this->modal('delete-semester-modal')->close();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Semester') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola semester per tahun ajaran') }}</flux:subheading>
            </div>
            <flux:modal.trigger name="semester-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Semester') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <flux:select wire:model.live="academicYearFilter" variant="listbox" placeholder="Filter Tahun Ajaran" clearable>
            <flux:select.option value="">Semua Tahun</flux:select.option>
            @foreach ($academicYears as $year)
                <flux:select.option value="{{ $year->id }}">
                    {{ $year->name }} {{ $year->is_active ? '(Aktif)' : '' }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$semesters">
        <flux:table.columns>
            <flux:table.column>Tahun Ajaran</flux:table.column>
            <flux:table.column>Semester</flux:table.column>
            <flux:table.column>Periode</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($semesters as $semester)
                <flux:table.row :key="$semester->id">
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc" variant="outline">{{ $semester->academicYear->name }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$semester->type == 'GANJIL' ? 'orange' : 'blue'">{{ $semester->type }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500">
                        {{ $semester->start_date->format('d M Y') }} - {{ $semester->end_date->format('d M Y') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                <flux:modal.trigger name="semester-modal">
                                    <flux:menu.item wire:click="edit('{{ $semester->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>
                                <flux:modal.trigger name="delete-semester-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $semester->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.calendar class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada semester</flux:heading>
                                <flux:subheading>Belum ada data semester. Klik "Tambah Semester" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Modal Create/Edit -->
    <flux:modal name="semester-modal" flyout class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $semesterId ? 'Edit Semester' : 'Tambah Semester' }}</flux:heading>
                <flux:subheading>{{ $semesterId ? 'Perbarui data semester.' : 'Tambahkan semester baru.' }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label badge="Required">Tahun Ajaran</flux:label>
                <flux:select wire:model="academic_year_id" variant="listbox" placeholder="Pilih Tahun">
                    @foreach ($academicYears as $year)
                        <flux:select.option value="{{ $year->id }}">{{ $year->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="academic_year_id" />
            </flux:field>

            <flux:field>
                <flux:label badge="Required">Tipe Semester</flux:label>
                <flux:select wire:model="type" variant="listbox" placeholder="Pilih">
                    <flux:select.option value="GANJIL">GANJIL</flux:select.option>
                    <flux:select.option value="GENAP">GENAP</flux:select.option>
                </flux:select>
                <flux:error name="type" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label badge="Required">Tanggal Mulai</flux:label>
                    <flux:input wire:model="start_date" type="date" />
                    <flux:error name="start_date" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Required">Tanggal Selesai</flux:label>
                    <flux:input wire:model="end_date" type="date" />
                    <flux:error name="end_date" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Modal -->
    <flux:modal name="delete-semester-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Semester?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus data ini?<br>
                    Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="destroy" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>