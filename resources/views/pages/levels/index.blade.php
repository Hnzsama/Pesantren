<?php

use App\Models\Department;
use App\Models\Level;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

new #[Lazy] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div class="space-y-2">
                    <div class="h-10 w-48 bg-zinc-100 dark:bg-zinc-800 rounded animate-pulse"></div>
                    <div class="h-4 w-64 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
                </div>
                <div class="h-10 w-32 bg-zinc-200 dark:bg-zinc-700 rounded-lg animate-pulse"></div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                @foreach(range(1, 2) as $i)
                    <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
                @endforeach
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="p-6 bg-white dark:bg-zinc-900 space-y-4">
                    @foreach(range(1, 10) as $i)
                        <div class="flex items-center gap-4">
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-zinc-100 dark:bg-zinc-800 rounded w-1/4 animate-pulse"></div>
                                <div class="h-3 bg-zinc-50 dark:bg-zinc-900 rounded w-1/6 animate-pulse"></div>
                            </div>
                            <div class="w-16 h-6 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
                        </div>
                        <div class="h-px bg-zinc-100 dark:bg-zinc-800 w-full"></div>
                    @endforeach
                </div>
            </div>
        </div>
        HTML;
    }

    #[Url]
    public $search = '';

    #[Url]
    public $departmentFilter = '';

    public $levelId = null;
    public $name = '';
    public $alias = '';
    public $grade_level = 1;
    public $department_id = '';

    public $levelIdToDelete = null;

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = Level::query()
            ->with('department')
            ->when($schoolId, fn($q) => $q->where('levels.school_id', $schoolId))
            ->when($this->departmentFilter, fn($q) => $q->where('department_id', $this->departmentFilter))
            ->when($this->search, fn($q) => $q->where('levels.name', 'like', '%' . $this->search . '%')
                ->orWhere('alias', 'like', '%' . $this->search . '%'))
            ->orderBy('levels.grade_level');

        $departments = Department::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();

        return [
            'levels' => $query->paginate(10),
            'departments' => $departments,
        ];
    }

    public function create()
    {
        $this->reset(['levelId', 'name', 'alias', 'grade_level', 'department_id']);
        $this->department_id = $this->departmentFilter; // Pre-select if filter active
        $this->resetValidation();
        $this->modal('level-modal')->show();
    }

    public function edit($id)
    {
        $level = Level::find($id);
        $this->levelId = $level->id;
        $this->name = $level->name;
        $this->alias = $level->alias;
        $this->grade_level = $level->grade_level;
        $this->department_id = $level->department_id;

        $this->modal('level-modal')->show();
    }

    public function save()
    {
        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels')->where(fn($query) => $query->where('school_id', auth()->user()->school_id))->ignore($this->levelId)
            ],
            'alias' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'grade_level' => 'required|integer|min:0',
        ]);

        $data = [
            'name' => $this->name,
            'alias' => $this->alias,
            'department_id' => $this->department_id,
            'grade_level' => $this->grade_level,
        ];

        if (!$this->levelId) {
            if (auth()->user()->hasRole('admin')) {
                $data['school_id'] = auth()->user()->school_id;
            }
        }

        if ($this->levelId) {
            Level::find($this->levelId)->update($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data kelas berhasil diperbarui.');
        } else {
            Level::create($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data kelas berhasil ditambahkan.');
        }

        $this->modal('level-modal')->close();
        $this->reset(['levelId', 'name', 'alias', 'grade_level', 'department_id']);
    }

    public function confirmDelete($id)
    {
        $this->levelIdToDelete = $id;
        $this->modal('delete-level-modal')->show();
    }

    public function destroy()
    {
        $level = Level::find($this->levelIdToDelete);
        if ($level) {
            $level->delete();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data kelas berhasil dihapus.');
        }
        $this->levelIdToDelete = null;
        $this->modal('delete-level-modal')->close();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Data Kelas') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Kelola daftar kelas dan jenjangnya') }}</flux:subheading>
            </div>
            <flux:modal.trigger name="level-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Kelas') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari kelas..." />

        <flux:select wire:model.live="departmentFilter" variant="listbox" placeholder="Filter Jenjang" clearable>
            <flux:select.option value="">Semua Jenjang</flux:select.option>
            @foreach ($departments as $dept)
                <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$levels">
        <flux:table.columns>
            <flux:table.column>Jenjang</flux:table.column>
            <flux:table.column>Nama Kelas</flux:table.column>
            <flux:table.column>Alias / Kode</flux:table.column>
            <flux:table.column>Tingkat (Grade)</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($levels as $lvl)
                <flux:table.row :key="$lvl->id">
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$lvl->department->badge_color ?? 'zinc'">
                            {{ $lvl->department->name ?? '-' }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="font-medium">
                        {{ $lvl->name }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="font-mono text-zinc-500">{{ $lvl->alias ?? '-' }}</span>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc" variant="outline">{{ $lvl->grade_level }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                <flux:modal.trigger name="level-modal">
                                    <flux:menu.item wire:click="edit('{{ $lvl->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>
                                <flux:modal.trigger name="delete-level-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $lvl->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.squares-2x2 class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada kelas</flux:heading>
                                <flux:subheading>Belum ada data kelas. Klik "Tambah Kelas" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Modal Create/Edit -->
    <flux:modal name="level-modal" flyout class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $levelId ? 'Edit Kelas' : 'Tambah Kelas' }}</flux:heading>
                <flux:subheading>{{ $levelId ? 'Perbarui informasi kelas.' : 'Tambahkan kelas baru.' }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label badge="Required">Jenjang</flux:label>
                <flux:select wire:model="department_id" variant="listbox" placeholder="Pilih Jenjang">
                    @foreach ($departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="department_id" />
            </flux:field>

            <flux:field>
                <flux:label badge="Required">Nama Kelas</flux:label>
                <flux:input wire:model="name" placeholder="Contoh: Kelas 1" />
                <flux:error name="name" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label badge="Optional">Alias / Kode</flux:label>
                    <flux:input wire:model="alias" placeholder="Contoh: MI 1" />
                    <flux:error name="alias" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Required">Tingkat (Angka)</flux:label>
                    <flux:input wire:model="grade_level" type="number" min="0" placeholder="1" />
                    <flux:error name="grade_level" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Modal -->
    <flux:modal name="delete-level-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Kelas?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus kelas ini?<br>
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