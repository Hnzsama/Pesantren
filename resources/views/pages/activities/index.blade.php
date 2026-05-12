<?php

use App\Models\Activity;
use App\Models\Level;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] #[Layout('layouts.app')] class extends Component {
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
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                @foreach(range(1, 4) as $i)
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

    public $search = '';

    public $confirmingDeletion = false;

    public $deletingId = null;

    // Filters
    public $levelFilter = '';

    public $typeFilter = '';

    // Form
    public $form = [
        'id' => null,
        'name' => '',
        'type' => 'daily',
        'description' => '',
        'level_ids' => [],
    ];

    public function mount()
    {
        //
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $activities = Activity::query()
            ->with(['levels'])
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->levelFilter, function ($q) {
                $q->whereHas('levels', fn($r) => $r->where('levels.id', $this->levelFilter));
            })
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return [
            'activities' => $activities,
            'levels' => Level::when($schoolId, fn($q) => $q->where('school_id', $schoolId))->orderBy('name')->get(),
        ];
    }

    public function openCreateModal()
    {
        $this->reset('form');
        $this->form['type'] = 'daily';
        $this->modal('activity-modal')->show();
    }

    public function edit($id)
    {
        $activity = Activity::findOrFail($id);
        $this->form = [
            'id' => $activity->id,
            'name' => $activity->name,
            'type' => $activity->type,
            'description' => $activity->description,
            'level_ids' => $activity->levels->pluck('id')->toArray(),
        ];
        $this->modal('activity-modal')->show();
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.type' => 'required|in:daily,rutinan',
            'form.description' => 'required|string',
            'form.level_ids' => 'required|array|min:1',
            'form.level_ids.*' => 'exists:levels,id',
        ]);

        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $data = [
            'school_id' => $schoolId,
            'name' => $this->form['name'],
            'type' => $this->form['type'],
            'description' => $this->form['description'],
        ];

        DB::transaction(function () use ($data) {
            if ($this->form['id']) {
                $activity = Activity::findOrFail($this->form['id']);
                $activity->update($data);
                $activity->levels()->sync($this->form['level_ids']);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kegiatan berhasil diperbarui.');
            } else {
                $activity = Activity::create($data);
                $activity->levels()->sync($this->form['level_ids']);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kegiatan berhasil ditambahkan.');
            }
        });
        
        $this->modal('activity-modal')->close();
    }

    public function confirmDelete($id)
    {
        $this->deletingId = $id;
        $this->modal('delete-confirm-modal')->show();
    }

    public function delete()
    {
        Activity::findOrFail($this->deletingId)->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kegiatan berhasil dihapus.');
        $this->modal('delete-confirm-modal')->close();
    }

    // Helper for labels
    public function getTypeLabel($type)
    {
        return match ($type) {
            'daily' => 'Harian',
            'rutinan' => 'Rutinan',
            default => $type,
        };
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">Kegiatan Harian</flux:heading>
                <flux:subheading size="lg">Kelola kegiatan rutin santri (harian/mingguan)</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Tambah Kegiatan</flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari kegiatan..." />

        <flux:select wire:model.live="levelFilter" variant="listbox" placeholder="Semua Kelas">
            <flux:select.option value="">Semua Kelas</flux:select.option>
            @foreach($levels as $level)
                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="typeFilter" variant="listbox" placeholder="Semua Tipe">
            <flux:select.option value="">Semua Tipe</flux:select.option>
            <flux:select.option value="daily">Harian</flux:select.option>
            <flux:select.option value="rutinan">Rutinan</flux:select.option>
        </flux:select>
    </div>

    <!-- Table -->
    <div class="space-y-4">
        <flux:table :paginate="$activities">
            <flux:table.columns>
                <flux:table.column>Nama Kegiatan</flux:table.column>
                <flux:table.column>Tipe</flux:table.column>
                <flux:table.column>Kelas</flux:table.column>
                <flux:table.column>Deskripsi</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($activities as $activity)
                    <flux:table.row :key="$activity->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $activity->name }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="{{ $activity->type === 'daily' ? 'blue' : 'orange' }}">
                                {{ $this->getTypeLabel($activity->type) }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach($activity->levels as $level)
                                    <flux:badge size="sm" inset="top bottom">{{ $level->name }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-sm text-zinc-500 truncate max-w-xs">
                                {{ Str::limit($activity->description, 50) }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item wire:click="edit('{{ $activity->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="confirmDelete('{{ $activity->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8">
                            <div class="text-zinc-500">Belum ada kegiatan harian</div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Create/Edit Flyout Modal -->
    <flux:modal name="activity-modal" flyout class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $form['id'] ? 'Edit Kegiatan' : 'Tambah Kegiatan Baru' }}</flux:heading>
            <flux:subheading>Isi detail kegiatan rutinan di bawah ini.</flux:subheading>
        </div>

        <flux:separator />

        <div class="space-y-6">
            <flux:input wire:model="form.name" label="Nama Kegiatan" placeholder="Contoh: Sholat Dhuha" />

            <flux:textarea wire:model="form.description" label="Deskripsi" placeholder="Deskripsi kegiatan..." />

            <div class="grid grid-cols-1 gap-4">
                <flux:select wire:model="form.type" label="Tipe Kegiatan" variant="listbox">
                    <flux:select.option value="daily">Harian (Daily)</flux:select.option>
                    <flux:select.option value="rutinan">Rutinan (Weekly/Monthly)</flux:select.option>
                </flux:select>

                <flux:select wire:model="form.level_ids" label="Kelas" variant="listbox" multiple placeholder="Pilih satu atau lebih kelas...">
                    @foreach($levels as $level)
                        <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">Batal</flux:button>
            </flux:modal.close>
            <flux:button wire:click="save" variant="primary">Simpan</flux:button>
        </div>
    </flux:modal>

    <!-- Delete Confirmation -->
    <flux:modal name="delete-confirm-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="text-red-500">Hapus Kegiatan?</flux:heading>
                <flux:subheading>Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>