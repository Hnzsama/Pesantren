<?php

use App\Models\School;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Lazy] class extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $roleFilter = '';

    #[Url]
    public $schoolFilter = '';

    public $name = '';

    public $email = '';

    public $phone_number = '';

    public $password = '';

    public $role = '';

    public $school_id = '';

    public $userId = null;

    public $userIdToDelete = null;

    public function with(): array
    {
        $query = User::query()
            ->with(['school', 'roles'])
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'admin_koperasi']);
            })
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->roleFilter, fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $this->roleFilter)));

        if (auth()->user()->hasRole('admin')) {
            $query->where('school_id', auth()->user()->school_id);
        } else {
            $query->when($this->schoolFilter, fn ($q) => $q->where('school_id', $this->schoolFilter));
        }

        return [
            'users' => $query->paginate(10),
            'schools' => auth()->user()->hasRole('super_admin') ? School::all() : [],
            'roles' => Role::whereIn('name', ['admin', 'admin_koperasi'])->get(),
        ];
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->userId)],
            'phone_number' => 'nullable|string|max:20',
            'password' => $this->userId ? 'nullable|string|min:8' : 'required|string|min:8',
            'role' => 'required|exists:roles,name',

            'school_id' => auth()->user()->hasRole('super_admin') ? 'required|exists:schools,id' : 'nullable',
        ]);

        DB::transaction(function () {
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'phone_number' => $this->phone_number,
                'school_id' => auth()->user()->hasRole('admin') ? auth()->user()->school_id : $this->school_id,
            ];

            if ($this->password) {
                $data['password'] = bcrypt($this->password);
            }

            if ($this->userId) {
                $user = User::find($this->userId);
                $user->update($data);
                $user->syncRoles([$this->role]);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data pengguna berhasil diperbarui.');
            } else {
                $user = User::create($data);
                $user->assignRole($this->role);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data pengguna berhasil ditambahkan.');
            }
        });

        $this->reset(['name', 'email', 'phone_number', 'password', 'role', 'school_id', 'userId']);
        $this->resetValidation();
        $this->modal('user-modal')->close();
    }

    public function edit($id)
    {
        $user = User::find($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone_number = $user->phone_number;
        $this->role = $user->roles->first()?->name ?? '';
        $this->school_id = $user->school_id;
        $this->modal('user-modal')->show();
    }

    public function confirmDelete($id)
    {
        $this->userIdToDelete = $id;
        $this->modal('delete-user-modal')->show();
    }

    public function destroy()
    {
        DB::transaction(function () {
            $user = User::find($this->userIdToDelete);
            if ($user) {
                $user->delete();
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data pengguna berhasil dihapus.');
            }
        });
        $this->reset('userIdToDelete');
        $this->modal('delete-user-modal')->close();
    }

    public function create()
    {
        $this->reset(['name', 'email', 'phone_number', 'password', 'role', 'school_id', 'userId']);
        $this->resetValidation();
        $this->modal('user-modal')->show();
    }

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
            
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="h-10 w-full md:w-64 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
                <div class="h-10 w-full md:w-48 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
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
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Pengguna') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola pengguna sekolah dan koperasi') }}
                </flux:subheading>
            </div>
            <flux:modal.trigger name="user-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Pengguna') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari pengguna..."
            class="w-full md:w-64" />

        <flux:select wire:model.live="roleFilter" variant="listbox" placeholder="Filter Role" clearable>
            <flux:select.option value="">Semua Role</flux:select.option>
            @foreach ($roles as $role)
                <flux:select.option value="{{ $role->name }}">{{ ucfirst($role->name) }}</flux:select.option>
            @endforeach
        </flux:select>

        @if (auth()->user()->hasRole('super_admin'))
            <flux:select wire:model.live="schoolFilter" variant="listbox" placeholder="Filter Sekolah" clearable>
                <flux:select.option value="">Semua Sekolah</flux:select.option>
                @foreach ($schools as $school)
                    <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Nomor Telepon</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            @if (auth()->user()->hasRole('super_admin'))
                <flux:table.column>Sekolah</flux:table.column>
            @endif
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>{{ $user->phone_number ?? '-' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="lime">{{ ucfirst($user->roles->first()?->name ?? 'N/A') }}</flux:badge>
                    </flux:table.cell>
                    @if (auth()->user()->hasRole('super_admin'))
                        <flux:table.cell>{{ $user->school?->name ?? '-' }}</flux:table.cell>
                    @endif
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="edit('{{ $user->id }}')">Edit
                            </flux:button>
                            <flux:button variant="ghost" size="sm" icon="trash"
                                wire:click="confirmDelete('{{ $user->id }}')">Hapus</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.user-group class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada pengguna</flux:heading>
                                <flux:subheading>Belum ada data pengguna. Klik "Tambah Pengguna" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- User Form Modal -->
    <flux:modal name="user-modal" class="md:w-96 space-y-6">
        <div>
            <flux:heading size="lg">{{ $userId ? 'Edit Pengguna' : 'Tambah Pengguna' }}</flux:heading>
            <flux:subheading>{{ $userId ? 'Perbarui data pengguna' : 'Tambah data pengguna baru' }}</flux:subheading>
        </div>

        <flux:separator />

        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="name" label="Nama" placeholder="Masukkan nama lengkap" />

            <flux:input wire:model="email" type="email" label="Email" placeholder="email@example.com" />

            <flux:input wire:model="phone_number" label="Nomor Telepon" placeholder="08123456789" />

            <flux:input wire:model="password" type="password" label="Password"
                placeholder="{{ $userId ? 'Kosongkan jika tidak ingin mengubah' : 'Minimal 8 karakter' }}" />

            <flux:select wire:model="role" label="Role" variant="listbox" placeholder="Pilih role">
                @foreach ($roles as $role)
                    <flux:select.option value="{{ $role->name }}">{{ ucfirst($role->name) }}</flux:select.option>
                @endforeach
            </flux:select>

            @if (auth()->user()->hasRole('super_admin'))
                <flux:select wire:model="school_id" label="Sekolah" variant="listbox" placeholder="Pilih sekolah">
                    @foreach ($schools as $school)
                        <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $userId ? 'Simpan' : 'Tambah' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-user-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Pengguna?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus pengguna ini?<br>
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