<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $schoolFilter = '';

    public $name = '';
    public $email = '';
    public $phone_number = '';
    public $password = '';
    public $school_id = '';

    public $userId = null;
    public $userIdToDelete = null;

    public function with(): array
    {
        $query = User::query()
            ->with(['school'])
            ->role('wali_santri')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'));

        if (auth()->user()->hasRole('admin')) {
            $query->where('school_id', auth()->user()->school_id);
        } else {
            $query->when($this->schoolFilter, fn($q) => $q->where('school_id', $this->schoolFilter));
        }

        return [
            'users' => $query->paginate(10),
            'schools' => auth()->user()->hasRole('super_admin') ? School::all() : [],
        ];
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->userId)],
            'phone_number' => 'nullable|string|max:20',
            'password' => $this->userId ? 'nullable|string|min:8' : 'required|string|min:8',
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
                // Role is already wali_santri, no need to sync unless we support multiple roles (assuming not here)
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data wali santri berhasil diperbarui.');
            } else {
                $user = User::create($data);
                $user->assignRole('wali_santri');
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data wali santri berhasil ditambahkan.');
            }
        });

        $this->reset(['name', 'email', 'phone_number', 'password', 'school_id', 'userId']);
        $this->resetValidation();
        $this->modal('guardian-modal')->close();
    }

    public function edit($id)
    {
        $user = User::find($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone_number = $user->phone_number;
        $this->school_id = $user->school_id;
        $this->modal('guardian-modal')->show();
    }

    public function confirmDelete($id)
    {
        $this->userIdToDelete = $id;
        $this->modal('delete-guardian-modal')->show();
    }

    public function destroy()
    {
        DB::transaction(function () {
            $user = User::find($this->userIdToDelete);
            if ($user && $user->hasRole('wali_santri')) {
                $user->delete();
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data wali santri berhasil dihapus.');
            }
        });
        $this->reset('userIdToDelete');
        $this->modal('delete-guardian-modal')->close();
    }

    public function create()
    {
        $this->reset(['name', 'email', 'phone_number', 'password', 'school_id', 'userId']);
        $this->resetValidation();
        $this->modal('guardian-modal')->show();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Wali Santri') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola data wali santri') }}
                </flux:subheading>
            </div>
            <flux:modal.trigger name="guardian-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Wali') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari wali santri..."
            class="w-full md:w-64" />

        @role('super_admin')
        <flux:select wire:model.live="schoolFilter" variant="combobox" placeholder="Filter Sekolah"
            class="w-full md:w-64">
            <flux:select.option value="">Semua Sekolah</flux:select.option>
            @foreach ($schools as $school)
                <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
            @endforeach
        </flux:select>
        @endrole
    </div>

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column>Nama Wali</flux:table.column>
            <flux:table.column>Sekolah</flux:table.column>
            <flux:table.column>Telepon</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($users as $user)
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
                        <div class="text-sm">{{ $user->school->name ?? '-' }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="text-sm font-mono text-zinc-500">{{ $user->phone_number ?? '-' }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

                            <flux:menu>
                                <flux:modal.trigger name="guardian-modal">
                                    <flux:menu.item wire:click="edit('{{ $user->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>

                                <flux:modal.trigger name="delete-guardian-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $user->id }}')" icon="trash"
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
                            <flux:icon.user-group class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada wali santri</flux:heading>
                                <flux:subheading>Belum ada data wali santri. Klik "Tambah Wali Santri" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="guardian-modal" flyout class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $userId ? 'Edit Wali Santri' : 'Tambah Wali Santri' }}</flux:heading>
                <flux:subheading>
                    {{ $userId ? 'Perbarui informasi wali santri.' : 'Tambahkan wali santri baru ke dalam sistem.' }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label badge="Required">Nama Lengkap</flux:label>
                <flux:input wire:model="name" placeholder="Contoh: Ahmad Soleh" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label badge="Required">Email</flux:label>
                <flux:input wire:model="email" type="email" placeholder="ahmad@gmail.com" />
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

            <!-- Role Hidden (Auto Wali Santri) -->

            @role('super_admin')
            <flux:field>
                <flux:label badge="Required">Sekolah</flux:label>
                <flux:select wire:model="school_id" variant="combobox" placeholder="Pilih Sekolah">
                    @foreach ($schools as $school)
                        <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="school_id" />
            </flux:field>
            @endrole

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-guardian-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Wali Santri?</flux:heading>
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