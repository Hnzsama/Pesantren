<?php

use Livewire\Component;
use Spatie\Permission\Models\Role;
use Flux\Flux;
use Livewire\Attributes\Url;

new class extends Component {
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