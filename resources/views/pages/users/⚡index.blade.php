<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Flux\Flux;

new class extends Component {
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
        return [
            'users' => User::query()
                ->with(['school', 'roles'])
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'admin_koperasi']);
                })
                ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                ->when($this->roleFilter, fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', $this->roleFilter)))
                ->when($this->schoolFilter, fn($q) => $q->where('school_id', $this->schoolFilter))
                ->paginate(10),
            'schools' => School::all(),
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
            'school_id' => 'required|exists:schools,id',
        ]);

        DB::transaction(function () {
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'phone_number' => $this->phone_number,
                'school_id' => $this->school_id,
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

        <flux:select wire:model.live="roleFilter" variant="listbox" placeholder="Filter Role" class="w-full md:w-48"
            clearable>
            <flux:select.option value="admin">Admin Sekolah</flux:select.option>
            <flux:select.option value="admin_koperasi">Admin Koperasi</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="schoolFilter" variant="combobox" placeholder="Filter Sekolah"
            class="w-full md:w-64">
            <flux:select.option value="">Semua Sekolah</flux:select.option>
            @foreach ($schools as $school)
                <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column>Nama Pengguna</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column>Sekolah</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                            <div>
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$user->hasRole('admin') ? 'blue' : 'emerald'">
                            {{ $user->hasRole('admin') ? 'Admin Sekolah' : 'Admin Koperasi' }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="text-sm">{{ $user->school->name ?? '-' }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

                            <flux:menu>
                                <flux:modal.trigger name="user-modal">
                                    <flux:menu.item wire:click="edit('{{ $user->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>

                                <flux:modal.trigger name="delete-user-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $user->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="user-modal" flyout class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $userId ? 'Edit Pengguna' : 'Tambah Pengguna' }}</flux:heading>
                <flux:subheading>
                    {{ $userId ? 'Perbarui informasi pengguna.' : 'Tambahkan pengguna baru ke dalam sistem.' }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label badge="Required">Nama Lengkap</flux:label>
                <flux:input wire:model="name" placeholder="Contoh: Budi Santoso" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label badge="Required">Email</flux:label>
                <flux:input wire:model="email" type="email" placeholder="budi@sekolah.com" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label badge="Optional">Nomor Telepon</flux:label>
                <flux:input wire:model="phone_number" placeholder="08123456789" />
                <flux:error name="phone_number" />
            </flux:field>

            <flux:field>
                <flux:label badge="{{ $userId ? 'Optional' : 'Required' }}">Password</flux:label>
                <flux:input wire:model="password" type="password" viewable
                    placeholder="{{ $userId ? 'Biarkan kosong jika tidak ingin mengubah' : '' }}" />
                <flux:error name="password" />
            </flux:field>

            <flux:separator variant="subtle" />

            <flux:field>
                <flux:label badge="Required">Role</flux:label>
                <flux:select wire:model="role" variant="listbox" placeholder="Pilih Role">
                    <flux:select.option value="admin">Admin Sekolah</flux:select.option>
                    <flux:select.option value="admin_koperasi">Admin Koperasi</flux:select.option>
                </flux:select>
                <flux:error name="role" />
            </flux:field>

            <flux:field>
                <flux:label badge="Required">Sekolah</flux:label>
                <flux:select wire:model="school_id" variant="combobox" placeholder="Pilih Sekolah">
                    @foreach ($schools as $school)
                        <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="school_id" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

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