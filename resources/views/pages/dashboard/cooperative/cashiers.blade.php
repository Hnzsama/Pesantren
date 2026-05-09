<?php

use App\Models\School;
use App\Models\User;
use App\Notifications\AdminCreatedNotification;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Lazy] class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';

    public $name = '';
    public $email = '';
    public $phone_number = '';
    public $password = '';
    public $userId = null;
    public $userIdToDelete = null;
    public $userIdToResend = null;

    public function with(): array
    {
        $query = User::query()
            ->with(['roles'])
            ->whereHas('roles', function ($q) {
                $q->where('name', 'kasir');
            })
            ->where('school_id', auth()->user()->school_id)
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'));

        return [
            'cashiers' => $query->paginate(10),
        ];
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->userId)],
            'phone_number' => 'nullable|string|max:20',
            'password' => $this->userId ? 'nullable|string|min:8' : 'required|string|min:8',
        ]);

        DB::transaction(function () {
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'phone_number' => $this->phone_number,
                'school_id' => auth()->user()->school_id,
            ];

            if ($this->password) {
                $data['password'] = bcrypt($this->password);
            }

            if ($this->userId) {
                $user = User::find($this->userId);
                $user->update($data);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data kasir berhasil diperbarui.');
            } else {
                $user = User::create($data);
                $user->assignRole('kasir');

                $user->notify(new AdminCreatedNotification($this->password));

                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data kasir berhasil ditambahkan.');
            }
        });

        $this->reset(['name', 'email', 'phone_number', 'password', 'userId']);
        $this->resetValidation();
        $this->modal('cashier-modal')->close();
    }

    public function edit($id)
    {
        $user = User::find($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone_number = $user->phone_number;
        $this->modal('cashier-modal')->show();
    }

    public function confirmDelete($id)
    {
        $this->userIdToDelete = $id;
        $this->modal('delete-cashier-modal')->show();
    }

    public function destroy()
    {
        DB::transaction(function () {
            $user = User::find($this->userIdToDelete);
            if ($user && $user->hasRole('kasir')) {
                $user->delete();
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data kasir berhasil dihapus.');
            }
        });
        $this->reset('userIdToDelete');
        $this->modal('delete-cashier-modal')->close();
    }

    public function confirmResendAccess($id)
    {
        $this->userIdToResend = $id;
        $this->modal('resend-access-modal')->show();
    }

    public function resendAccess()
    {
        $user = User::find($this->userIdToResend);
        if ($user && $user->hasRole('kasir')) {
            $newPassword = Str::random(10);
            $user->update([
                'password' => bcrypt($newPassword),
            ]);

            $user->notify(new AdminCreatedNotification($newPassword));

            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Email akses baru telah dikirimkan ke ' . $user->email);
        }
        $this->reset('userIdToResend');
        $this->modal('resend-access-modal')->close();
    }

    public function create()
    {
        $this->reset(['name', 'email', 'phone_number', 'password', 'userId']);
        $this->resetValidation();
        $this->modal('cashier-modal')->show();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Kelola Kasir') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Daftar akun kasir yang bertugas di Koperasi') }}
                </flux:subheading>
            </div>
            <flux:modal.trigger name="cashier-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Kasir') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari kasir..."
            class="w-full md:w-64" />
    </div>

    <flux:table :paginate="$cashiers">
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Nomor Telepon</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($cashiers as $cashier)
                <flux:table.row :key="$cashier->id">
                    <flux:table.cell>{{ $cashier->name }}</flux:table.cell>
                    <flux:table.cell>{{ $cashier->email }}</flux:table.cell>
                    <flux:table.cell>{{ $cashier->phone_number ?? '-' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="edit('{{ $cashier->id }}')">Edit
                            </flux:button>
                            <flux:button variant="ghost" size="sm" icon="paper-airplane"
                                wire:click="confirmResendAccess('{{ $cashier->id }}')">Akses
                            </flux:button>
                            <flux:button variant="ghost" size="sm" icon="trash"
                                wire:click="confirmDelete('{{ $cashier->id }}')">Hapus</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.user-group class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada kasir</flux:heading>
                                <flux:subheading>Belum ada data kasir. Klik "Tambah Kasir" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Cashier Form Modal -->
    <flux:modal name="cashier-modal" class="md:w-96 space-y-6">
        <div>
            <flux:heading size="lg">{{ $userId ? 'Edit Kasir' : 'Tambah Kasir' }}</flux:heading>
            <flux:subheading>{{ $userId ? 'Perbarui data kasir' : 'Tambah data kasir baru' }}</flux:subheading>
        </div>

        <flux:separator />

        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="name" label="Nama" placeholder="Masukkan nama lengkap" />

            <flux:input wire:model="email" type="email" label="Email" placeholder="email@example.com" />
            <flux:subheading size="sm" class="mt-1">
                {{ __('Penting: Password sementara akan dikirimkan ke email ini.') }}
            </flux:subheading>

            <flux:input wire:model="phone_number" label="Nomor Telepon" placeholder="08123456789" />

            <flux:input wire:model="password" type="password" label="Password"
                placeholder="{{ $userId ? 'Kosongkan jika tidak ingin mengubah' : 'Minimal 8 karakter' }}" />

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
    <flux:modal name="delete-cashier-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Kasir?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus akun kasir ini?<br>
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

    <flux:modal name="resend-access-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Kirim Ulang Akses?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin mengatur ulang password dan mengirimkan akses baru ke kasir ini?
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="resendAccess" variant="primary" icon="paper-airplane">Kirim Ulang</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
