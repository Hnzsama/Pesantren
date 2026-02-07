<?php

use App\Models\BillCategory;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

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

    public $categoryId = null;
    public $name = '';
    public $description = '';

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = BillCategory::query()
            ->withCount('bills')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name');

        return [
            'categories' => $query->paginate(15),
        ];
    }

    public function openCreateModal()
    {
        $this->reset(['categoryId', 'name', 'description']);
        $this->resetValidation();
        $this->modal('category-modal')->show();
    }

    public function edit($id)
    {
        $category = BillCategory::findOrFail($id);
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->description = $category->description;
        $this->modal('category-modal')->show();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () {
            $user = auth()->user();
            $data = [
                'school_id' => $user->school_id,
                'name' => $this->name,
                'description' => $this->description,
            ];

            if ($this->categoryId) {
                BillCategory::findOrFail($this->categoryId)->update($data);
                $message = 'Kategori tagihan berhasil diperbarui.';
            } else {
                BillCategory::create($data);
                $message = 'Kategori tagihan berhasil ditambahkan.';
            }

            Flux::toast(variant: 'success', heading: 'Berhasil', text: $message);
        });

        $this->reset(['categoryId', 'name', 'description']);
        $this->modal('category-modal')->close();
    }

    public function confirmDelete($id)
    {
        $this->categoryId = $id;
        $this->modal('delete-category-modal')->show();
    }

    public function delete()
    {
        DB::transaction(function () {
            $category = BillCategory::findOrFail($this->categoryId);

            // Check if category has bills
            if ($category->bills()->count() > 0) {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Gagal',
                    text: 'Kategori tidak dapat dihapus karena masih memiliki tagihan.'
                );
                return;
            }

            $category->delete();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kategori tagihan berhasil dihapus.');
        });

        $this->reset(['categoryId']);
        $this->modal('delete-category-modal')->close();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Kategori Tagihan') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola kategori untuk tagihan santri') }}
                </flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Tambah Kategori') }}
            </flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Search -->
    <div class="mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari kategori..." />
    </div>

    <flux:table :paginate="$categories">
        <flux:table.columns>
            <flux:table.column>Nama Kategori</flux:table.column>
            <flux:table.column>Deskripsi</flux:table.column>
            <flux:table.column>Jumlah Tagihan</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($categories as $category)
                <flux:table.row :key="$category->id">
                    <flux:table.cell>
                        <div class="font-medium">{{ $category->name }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $category->description ?? '-' }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="blue">{{ $category->bills_count ?? 0 }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

                            <flux:menu>
                                <flux:modal.trigger name="category-modal">
                                    <flux:menu.item wire:click="edit('{{ $category->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>

                                <flux:modal.trigger name="delete-category-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $category->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.tag class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada kategori</flux:heading>
                                <flux:subheading>Belum ada kategori tagihan. Klik "Tambah Kategori" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Create/Edit Modal -->
    <flux:modal name="category-modal" class="md:w-[500px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $categoryId ? 'Edit Kategori' : 'Tambah Kategori' }}</flux:heading>
                <flux:subheading>{{ $categoryId ? 'Perbarui informasi kategori' : 'Tambahkan kategori baru' }}
                </flux:subheading>
            </div>

            <flux:separator />

            <flux:input wire:model="name" label="Nama Kategori" placeholder="Contoh: SPP Bulanan" required />

            <flux:textarea wire:model="description" label="Deskripsi" placeholder="Deskripsi kategori (opsional)"
                rows="3" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $categoryId ? 'Perbarui' : 'Simpan' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-category-modal" class="md:w-[400px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Kategori</flux:heading>
                <flux:subheading>Apakah Anda yakin ingin menghapus kategori ini?</flux:subheading>
            </div>

            <flux:separator />

            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <flux:icon.exclamation-triangle
                        class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div class="text-sm text-amber-800 dark:text-amber-200">
                        Kategori yang masih memiliki tagihan tidak dapat dihapus.
                    </div>
                </div>
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