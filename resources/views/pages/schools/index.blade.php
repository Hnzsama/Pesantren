<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';

    public $name = '';

    public $address = '';

    public $phone = '';

    public $email = '';


    public $website = '';

    public $schoolId = null;
    public $schoolIdToDelete = null;

    // Admin Credentials (for new school)
    public $admin_name = '';
    public $admin_email = '';
    public $admin_password = '';

    public function with(): array
    {
        return [
            'schools' => School::query()
                ->withCount([
                    'students',
                    'users as admins_count' => fn($q) => $q->role('admin'),
                    'users as koperasi_admins_count' => fn($q) => $q->role('admin_koperasi'),
                    'users as guardians_count' => fn($q) => $q->role('wali_santri'),
                ])
                ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                ->paginate(10),
        ];
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            if ($this->schoolId) {
                $school = School::find($this->schoolId);
                $school->update($validated);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data sekolah berhasil diperbarui.');
            } else {
                $this->validate([
                    'admin_name' => 'required|string|max:255',
                    'admin_email' => 'required|email|max:255|unique:users,email',
                    'admin_password' => 'required|string|min:8',
                ]);

                $validated['slug'] = Str::slug($this->name);
                $school = School::create($validated);

                // Create Admin User
                $user = User::create([
                    'name' => $this->admin_name,
                    'email' => $this->admin_email,
                    'password' => bcrypt($this->admin_password),
                    'school_id' => $school->id,
                ]);
                $user->assignRole('admin');

                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data sekolah dan admin berhasil ditambahkan.');
            }
        });

        $this->reset(['name', 'address', 'phone', 'email', 'website', 'schoolId', 'admin_name', 'admin_email', 'admin_password']);
        $this->resetValidation();
        $this->modal('school-modal')->close();
    }

    public function edit($id)
    {
        $school = School::find($id);
        $this->schoolId = $school->id;
        $this->name = $school->name;
        $this->address = $school->address;
        $this->phone = $school->phone;
        $this->email = $school->email;
        $this->website = $school->website;
    }

    public function confirmDelete($id)
    {
        $this->schoolIdToDelete = $id;
    }

    public function destroy()
    {
        DB::transaction(function () {
            $school = School::find($this->schoolIdToDelete);
            if ($school) {
                $school->delete();
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data sekolah berhasil dihapus.');
            }
        });
        $this->reset('schoolIdToDelete');
        $this->modal('delete-school-modal')->close();
    }

    public function create()
    {
        $this->reset(['name', 'address', 'phone', 'email', 'website', 'schoolId', 'admin_name', 'admin_email', 'admin_password']);
        $this->resetValidation();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Sekolah') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Kelola data sekolah dan informasi terkait') }}
                </flux:subheading>
            </div>
            <flux:modal.trigger name="school-modal">
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Sekolah') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari sekolah..."
            class="w-full md:w-64" />
    </div>

    <flux:table :paginate="$schools">
        <flux:table.columns>
            <flux:table.column>Nama Sekolah</flux:table.column>
            <flux:table.column>Kontak</flux:table.column>
            <flux:table.column>Admin</flux:table.column>
            <flux:table.column>Koperasi</flux:table.column>
            <flux:table.column>Wali</flux:table.column>
            <flux:table.column>Santri</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($schools as $school)
                <flux:table.row :key="$school->id">
                    <flux:table.cell>
                        <div class="font-medium">{{ $school->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $school->address }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            @if($school->phone)
                                <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                    <flux:icon name="phone" variant="mini" class="size-4 shrink-0" />
                                    <span>{{ $school->phone }}</span>
                                </div>
                            @endif

                            @if($school->email)
                                <div class="flex items-center gap-2 text-sm">
                                    <flux:icon name="envelope" variant="mini" class="size-4 text-zinc-400 shrink-0" />
                                    <a href="mailto:{{ $school->email }}"
                                        class="text-zinc-700 dark:text-zinc-300 hover:text-blue-600 dark:hover:text-blue-400 hover:underline transition-colors truncate max-w-[200px]">
                                        {{ $school->email }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="blue">{{ $school->admins_count }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="emerald">{{ $school->koperasi_admins_count }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="orange">{{ $school->guardians_count }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="violet">{{ $school->students_count }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

                            <flux:menu>
                                <flux:modal.trigger name="school-modal">
                                    <flux:menu.item wire:click="edit('{{ $school->id }}')" icon="pencil-square">Edit
                                    </flux:menu.item>
                                </flux:modal.trigger>

                                <flux:modal.trigger name="delete-school-modal">
                                    <flux:menu.item wire:click="confirmDelete('{{ $school->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.building-office-2 class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada sekolah</flux:heading>
                                <flux:subheading>Belum ada data sekolah. Klik "Tambah Sekolah" untuk menambahkan.
                                </flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="school-modal" flyout class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $schoolId ? 'Edit Sekolah' : 'Tambah Sekolah' }}</flux:heading>
                <flux:subheading>
                    {{ $schoolId ? 'Perbarui informasi sekolah.' : 'Tambahkan sekolah baru ke dalam sistem.' }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label badge="Required">Nama Sekolah</flux:label>
                <flux:input wire:model="name" placeholder="Contoh: Pesantren Al-Falah" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label badge="Optional">Alamat</flux:label>
                <flux:input wire:model="address" placeholder="Jl. Raya No. 123" />
                <flux:error name="address" />
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label badge="Optional">Telepon</flux:label>
                    <flux:input wire:model="phone" placeholder="021-1234567" />
                    <flux:error name="phone" />
                </flux:field>
                <flux:field>
                    <flux:label badge="Optional">Email</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="info@sekolah.com" />
                    <flux:error name="email" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label badge="Optional">Website</flux:label>
                <flux:input wire:model="website" placeholder="https://sekolah.com" />
                <flux:error name="website" />
            </flux:field>

            @if(!$schoolId)
                <flux:separator variant="subtle" />

                <div>
                    <flux:heading size="lg">Akun Admin</flux:heading>
                    <flux:subheading>Buat akun administrator untuk sekolah ini.</flux:subheading>
                </div>

                <flux:field>
                    <flux:label badge="Required">Nama Admin</flux:label>
                    <flux:input wire:model="admin_name" placeholder="Nama Lengkap Admin" />
                    <flux:error name="admin_name" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Required">Email Admin</flux:label>
                    <flux:input wire:model="admin_email" type="email" placeholder="admin@sekolah.com" />
                    <flux:error name="admin_email" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Required">Password</flux:label>
                    <flux:input wire:model="admin_password" type="password" viewable />
                    <flux:error name="admin_password" />
                </flux:field>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-school-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Sekolah?</flux:heading>
                <flux:text class="mt-2">
                    Apakah Anda yakin ingin menghapus sekolah ini?<br>
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