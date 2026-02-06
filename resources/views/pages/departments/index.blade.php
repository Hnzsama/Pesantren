<?php

use App\Models\Department;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    public $departmentId = null;

    public $name = '';

    public $code = '';

    public $description = '';

    public $departmentIdToDelete = null;

    public function with(): array
    {
        $user = auth()->user();
        $query = Department::query()
            ->when($user->hasRole('admin'), fn ($q) => $q->where('school_id', $user->school_id))
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('code', 'like', '%'.$this->search.'%'))
            ->orderBy('name');

        return [
            'departments' => $query->paginate(10),
        ];
    }

    public function create()
    {
        $this->reset(['departmentId', 'name', 'code', 'description']);
        $this->resetValidation();
        $this->modal('department-modal')->show();
    }

    public function edit($id)
    {
        $department = Department::find($id);
        $this->departmentId = $department->id;
        $this->name = $department->name;
        $this->code = $department->code;
        $this->description = $department->description;

        $this->modal('department-modal')->show();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('departments')->where(fn ($query) => $query->where('school_id', auth()->user()->school_id))->ignore($this->departmentId),
            ],
            'description' => 'nullable|string',
        ]);

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
        ];

        if (! $this->departmentId) {
            if (auth()->user()->hasRole('admin')) {
                $data['school_id'] = auth()->user()->school_id;
            }
        }

        if ($this->departmentId) {
            Department::find($this->departmentId)->update($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jenjang pendidikan berhasil diperbarui.');
        } else {
            Department::create($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jenjang pendidikan berhasil ditambahkan.');
        }

        $this->modal('department-modal')->close();
        $this->reset(['departmentId', 'name', 'code', 'description']);
    }

    public function confirmDelete($id)
    {
        $this->departmentIdToDelete = $id;
        $this->modal('delete-department-modal')->show();
    }

    public function destroy()
    {
        $department = Department::find($this->departmentIdToDelete);
        if ($department) {
            $department->delete();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jenjang pendidikan berhasil dihapus.');
        }
        $this->departmentIdToDelete = null;
        $this->modal('delete-department-modal')->close();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Jenjang Pendidikan') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola jenjang pendidikan di sekolah') }}
                </flux:subheading>
            </div>
            <flux:modal.trigger name="department-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Jenjang') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex justify-between items-center mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari jenjang..."
            class="w-full md:w-64" />
    </div>

    <flux:table :paginate="$departments">
        <flux:table.columns>
            <flux:table.column>Kode</flux:table.column>
            <flux:table.column>Nama Jenjang</flux:table.column>
            <flux:table.column>Keterangan</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($departments as $dept)
                <flux:table.row :key="$dept->id">
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">{{ $dept->code }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="font-medium">
                        {{ $dept->name }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        {{ $dept->description ?? '-' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                <flux:modal.trigger name="department-modal">
                                    <flux:menu.item wire:click="edit('{{ $dept->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>
                                <flux:modal.trigger name="delete-department-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $dept->id }}')" icon="trash"
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
                            <flux:icon.building-library class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada jenjang</flux:heading>
                                <flux:subheading>Belum ada data jenjang. Klik "Tambah Jenjang" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Modal Create/Edit -->
    <flux:modal name="department-modal" flyout class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $departmentId ? 'Edit Jenjang' : 'Tambah Jenjang' }}</flux:heading>
                <flux:subheading>
                    {{ $departmentId ? 'Perbarui informasi jenjang.' : 'Tambahkan jenjang pendidikan baru.' }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label badge="Required">Nama Jenjang</flux:label>
                <flux:input wire:model="name" placeholder="Contoh: Madrasah Ibtidaiyah" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label badge="Required">Kode</flux:label>
                <flux:input wire:model="code" placeholder="Contoh: MI" />
                <flux:error name="code" />
            </flux:field>

            <flux:field>
                <flux:label badge="Optional">Keterangan</flux:label>
                <flux:textarea wire:model="description" placeholder="Deskripsi singkat..." />
                <flux:error name="description" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Modal -->
    <flux:modal name="delete-department-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Jenjang?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus jenjang ini?<br>
                    Data yang terkait (Kelas, Santri) mungkin akan terpengaruh.
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