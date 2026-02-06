<?php

use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Lazy] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full">
            <div class="relative mb-6 w-full">
                <div class="h-10 w-48 bg-zinc-100 dark:bg-zinc-800 rounded animate-pulse"></div>
                <div class="h-4 w-96 mt-2 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
            </div>
            
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="p-6 bg-white dark:bg-zinc-900 space-y-4">
                    @foreach(range(1, 4) as $i)
                        <div class="flex items-center gap-4">
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-zinc-100 dark:bg-zinc-800 rounded w-1/4 animate-pulse"></div>
                                <div class="h-3 bg-zinc-50 dark:bg-zinc-900 rounded w-1/6 animate-pulse"></div>
                            </div>
                        </div>
                        <div class="h-px bg-zinc-100 dark:bg-zinc-800 w-full"></div>
                    @endforeach
                </div>
            </div>
        </div>
        HTML;
    }
    #[Url]
    public $sortBy = 'name';

    #[Url]
    public $sortDirection = 'asc';

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function with(): array
    {
        return [
            'roles' => Role::orderBy($this->sortBy, $this->sortDirection)->get(),
        ];
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Hak Akses') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Daftar akses yang tersedia dalam sistem') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                wire:click="sort('name')">Nama Role</flux:table.column>
            <flux:table.column>Guard Name</flux:table.column>
            <flux:table.column>Dibuat Pada</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($roles as $role)
                @php
                    $color = match ($role->name) {
                        'admin' => 'violet',
                        'admin_koperasi' => 'emerald',
                        'super_admin' => 'rose',
                        'wali_santri' => 'cyan',
                        default => 'zinc',
                    };

                    $label = match ($role->name) {
                        'admin' => 'Admin',
                        'admin_koperasi' => 'Admin koperasi',
                        'super_admin' => 'Super admin',
                        'wali_santri' => 'Wali santri',
                        default => $role->name,
                    };
                @endphp
                <flux:table.row :key="$role->id">
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$color">{{ $label }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="text-sm font-medium text-zinc-500 font-mono">{{ $role->guard_name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="text-sm text-zinc-500">{{ $role->created_at->format('d M Y') }}</div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.shield-check class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada role</flux:heading>
                                <flux:subheading>Belum ada data hak akses.</flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>