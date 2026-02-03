<?php

use App\Models\AcademicYear;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';

    public $yearId = null;
    public $name = '';
    public $start_date = '';
    public $end_date = '';
    public $is_active = false;

    public $yearIdToDelete = null;

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = AcademicYear::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('start_date', 'desc');

        return [
            'academicYears' => $query->paginate(10),
        ];
    }

    public function create()
    {
        $this->reset(['yearId', 'name', 'start_date', 'end_date', 'is_active']);
        $this->resetValidation();
        $this->modal('year-modal')->show();
    }

    public function edit($id)
    {
        $year = AcademicYear::find($id);
        $this->yearId = $year->id;
        $this->name = $year->name;
        $this->start_date = $year->start_date?->format('Y-m-d');
        $this->end_date = $year->end_date?->format('Y-m-d');
        $this->is_active = $year->is_active;

        $this->modal('year-modal')->show();
    }

    public function save()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_years')->where(fn($query) => $query->where('school_id', $schoolId))->ignore($this->yearId)
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $this->name,
            'start_date' => $this->start_date ?: null,
            'end_date' => $this->end_date ?: null,
            'is_active' => $this->is_active,
        ];

        if (!$this->yearId && $schoolId) {
            $data['school_id'] = $schoolId;
        }

        if ($this->yearId) {
            $year = AcademicYear::find($this->yearId);
            $year->update($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Tahun ajaran berhasil diperbarui.');
        } else {
            $year = AcademicYear::create($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Tahun ajaran berhasil ditambahkan.');
        }

        // If this year is set to active, deactivate others
        if ($this->is_active && $schoolId) {
            AcademicYear::where('school_id', $schoolId)
                ->where('id', '!=', $year->id)
                ->update(['is_active' => false]);
        }

        $this->modal('year-modal')->close();
        $this->reset(['yearId', 'name', 'start_date', 'end_date', 'is_active']);
    }

    public function toggleActive($id)
    {
        $year = AcademicYear::find($id);
        if (!$year)
            return;

        $schoolId = auth()->user()->school_id;

        // If activating, deactivate others
        if (!$year->is_active) {
            AcademicYear::where('school_id', $schoolId)->update(['is_active' => false]);
            $year->update(['is_active' => true]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Tahun ajaran diaktifkan.');
        } else {
            // Optional: allow deactivating the only active one? usually yes.
            $year->update(['is_active' => false]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Tahun ajaran dinonaktifkan.');
        }
    }

    public function confirmDelete($id)
    {
        $this->yearIdToDelete = $id;
        $this->modal('delete-year-modal')->show();
    }

    public function destroy()
    {
        $year = AcademicYear::find($this->yearIdToDelete);
        if ($year) {
            $year->delete();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Tahun ajaran berhasil dihapus.');
        }
        $this->yearIdToDelete = null;
        $this->modal('delete-year-modal')->close();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Tahun Ajaran') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola tahun ajaran sekolah') }}</flux:subheading>
            </div>
            <flux:modal.trigger name="year-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Tahun Ajaran') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex justify-between items-center mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari tahun ajaran..."
            class="w-full md:w-64" />
    </div>

    <flux:table :paginate="$academicYears">
        <flux:table.columns>
            <flux:table.column>Nama Tahun Ajaran</flux:table.column>
            <flux:table.column>Periode</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($academicYears as $year)
                <flux:table.row :key="$year->id">
                    <flux:table.cell class="font-medium">
                        {{ $year->name }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        @if($year->start_date && $year->end_date)
                            {{ $year->start_date->format('d M Y') }} - {{ $year->end_date->format('d M Y') }}
                        @else
                            -
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:switch wire:click="toggleActive('{{ $year->id }}')" :checked="$year->is_active" />
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                <flux:modal.trigger name="year-modal">
                                    <flux:menu.item wire:click="edit('{{ $year->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>
                                <flux:modal.trigger name="delete-year-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $year->id }}')" icon="trash"
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
                            <flux:icon.calendar-days class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada tahun ajaran</flux:heading>
                                <flux:subheading>Belum ada data tahun ajaran. Klik "Tambah Tahun Ajaran" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Modal Create/Edit -->
    <flux:modal name="year-modal" flyout class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $yearId ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran' }}</flux:heading>
                <flux:subheading>{{ $yearId ? 'Perbarui informasi.' : 'Tambahkan tahun ajaran baru.' }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label badge="Required">Nama Tahun Ajaran</flux:label>
                <flux:input wire:model="name" placeholder="Contoh: 2024/2025" />
                <flux:error name="name" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label badge="Optional">Tanggal Mulai</flux:label>
                    <flux:input wire:model="start_date" type="date" />
                    <flux:error name="start_date" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Optional">Tanggal Selesai</flux:label>
                    <flux:input wire:model="end_date" type="date" />
                    <flux:error name="end_date" />
                </flux:field>
            </div>

            <flux:field>
                <flux:switch wire:model="is_active" label="Set sebagai Aktif?" />
                <flux:description>Jika diaktifkan, tahun ajaran aktif lainnya akan dinonaktifkan.</flux:description>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Modal -->
    <flux:modal name="delete-year-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Tahun Ajaran?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus data ini?<br>
                    Data Semester dan Kelas terkait mungkin terpengaruh.
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