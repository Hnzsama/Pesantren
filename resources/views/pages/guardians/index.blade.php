<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Notifications\GuardianCreatedNotification;
use Flux\Flux;
use Illuminate\Support\Str;

new #[Lazy] class extends Component {
    use WithPagination, WithFileUploads;

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
                <div class="h-10 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse w-full md:w-64"></div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="p-6 bg-white dark:bg-zinc-900 space-y-4">
                    @foreach(range(1, 10) as $i)
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-800 animate-pulse"></div>
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
    public $userIdToResend = null;

    // Import properties
    public $importFile;
    public $import_school_id = '';

    public function with(): array
    {
        $query = User::query()
            ->with(['school'])
            ->role('wali_santri')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'));

        if (auth()->user()->hasRole('super_admin')) {
            $query->when($this->schoolFilter, fn($q) => $q->where('school_id', $this->schoolFilter));
        } else {
            $query->where('school_id', auth()->user()->school_id);
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

                // Send email notification with credentials
                $user->notify(new GuardianCreatedNotification($this->password));

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

    public function confirmResendAccess($id)
    {
        $this->userIdToResend = $id;
        $this->modal('resend-access-modal')->show();
    }

    public function resendAccess()
    {
        $user = User::findOrFail($this->userIdToResend);

        // Reset password to a random 8-character string
        $tempPassword = Str::random(8);
        $user->update([
            'password' => bcrypt($tempPassword)
        ]);

        // Notify user
        $user->notify(new GuardianCreatedNotification($tempPassword));

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Akses baru telah dikirim ke email ' . $user->email);
        $this->modal('resend-access-modal')->close();
        $this->reset('userIdToResend');
    }

    public function create()
    {
        $this->reset(['name', 'email', 'phone_number', 'password', 'school_id', 'userId']);
        $this->resetValidation();
        $this->modal('guardian-modal')->show();
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=template_wali_santri.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $columns = ['Nama Lengkap', 'Email', 'Nomor Telepon'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, ['Ahmad Soleh', 'ahmad@example.com', '08123456789']);
            fputcsv($file, ['Siti Aminah', 'siti@example.com', '08987654321']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:2048',
            'import_school_id' => auth()->user()->hasRole('super_admin') ? 'required|exists:schools,id' : 'nullable',
        ], [
            'importFile.required' => 'File CSV wajib diunggah.',
            'importFile.mimes' => 'File harus berformat CSV.',
            'import_school_id.required' => 'Sekolah wajib dipilih.'
        ]);

        $path = $this->importFile->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file);

        if (!$header || count($header) < 3) {
            Flux::toast(variant: 'danger', heading: 'Gagal', text: 'Format CSV tidak valid. Gunakan template yang disediakan.');
            fclose($file);
            return;
        }

        $importedCount = 0;
        $failedCount = 0;
        $schoolId = auth()->user()->hasRole('admin') ? auth()->user()->school_id : $this->import_school_id;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($file)) !== false) {
                if (count($row) < 3) continue;

                $name = trim($row[0]);
                $email = trim($row[1]);
                $phone = trim($row[2]);

                if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failedCount++;
                    continue;
                }

                // Check if email already exists
                if (User::where('email', $email)->exists()) {
                    $failedCount++;
                    continue;
                }

                $password = Str::random(8);

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'phone_number' => $phone,
                    'password' => bcrypt($password),
                    'school_id' => $schoolId,
                ]);

                $user->assignRole('wali_santri');
                $user->notify(new GuardianCreatedNotification($password));
                $importedCount++;
            }
            DB::commit();

            $message = "Berhasil mengimpor {$importedCount} data.";
            if ($failedCount > 0) {
                $message .= " Namun {$failedCount} baris gagal (email sudah ada atau format salah).";
            }
            
            Flux::toast(variant: 'success', heading: 'Import Selesai', text: $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Flux::toast(variant: 'danger', heading: 'Gagal', text: 'Terjadi kesalahan saat memproses import.');
        }

        fclose($file);
        $this->reset(['importFile', 'import_school_id']);
        $this->modal('import-guardian-modal')->close();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Wali Santri') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Kelola data wali santri') }}</flux:subheading>
            </div>
            <div class="flex flex-col md:flex-row gap-2">
                <flux:modal.trigger name="import-guardian-modal">
                    <flux:button variant="subtle" icon="arrow-down-tray">{{ __('Import CSV') }}</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="guardian-modal">
                    <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Tambah Wali') }}</flux:button>
                </flux:modal.trigger>
            </div>
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
                                <flux:menu.item wire:click="confirmResendAccess('{{ $user->id }}')" icon="key">Akses
                                </flux:menu.item>

                                <flux:menu.separator />

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
                <flux:description>Gunakan email aktif wali santri untuk pengiriman akses login.</flux:description>
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

    <flux:modal name="delete-guardian-modal" class="w-full max-w-sm">
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

    <flux:modal name="resend-access-modal" class="w-full max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Kirim Ulang Akses?</flux:heading>
                <flux:text class="mt-2">
                    Sistem akan membuatkan password baru secara acak dan mengirimkannya ke email wali santri yang
                    bersangkutan.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="resendAccess" variant="primary">Kirim Akses Baru</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="import-guardian-modal" class="w-full max-w-lg">
        <form wire:submit="import" class="space-y-6">
            <div>
                <flux:heading size="lg">Import Data Wali Santri</flux:heading>
                <flux:subheading>
                    Gunakan file berformat .csv untuk mengimpor data sekaligus secara masal.
                </flux:subheading>
            </div>

            <div class="p-4 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl space-y-3">
                <div class="flex items-center gap-2">
                    <div class="p-2 bg-accent/10 rounded-lg">
                        <flux:icon.table-cells class="w-5 h-5 text-accent" />
                    </div>
                    <div class="text-sm font-medium">Langkah 1: Siapkan Data</div>
                </div>
                <div class="text-sm text-zinc-500 pl-11">
                    Pastikan susunan baris pertama terdiri dari header (bebas), lalu data berisi: <strong>Nama Lengkap, Email, Nomor Telepon</strong>. Email harus valid dan unik.
                </div>
                <div class="pl-11">
                    <flux:button wire:click="downloadTemplate" variant="subtle" size="sm" icon="document-arrow-down">Unduh Template CSV</flux:button>
                </div>
            </div>

            @role('super_admin')
            <flux:field>
                <flux:label badge="Required">Tujuan Sekolah</flux:label>
                <flux:select wire:model="import_school_id" variant="combobox" placeholder="Pilih Sekolah">
                    @foreach ($schools as $school)
                        <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="import_school_id" />
            </flux:field>
            @endrole

            <flux:field>
                <flux:label badge="Required">Pilih File CSV</flux:label>
                <input type="file" wire:model="importFile" accept=".csv" 
                       class="block w-full text-sm text-zinc-500
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-full file:border-0
                              file:text-sm file:font-semibold
                              file:bg-accent/10 file:text-accent
                              hover:file:bg-accent/20 cursor-pointer transition-colors" />
                <flux:error name="importFile" />
            </flux:field>

            <div wire:loading wire:target="importFile" class="text-sm text-blue-500 font-medium">
                Mengunggah dan bersiap membaca file...
            </div>
            <div wire:loading wire:target="import" class="text-sm text-blue-500 font-medium">
                Sedang mengimpor data...
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="importFile, import">Mulai Import</flux:button>
            </div>
        </form>
    </flux:modal>
</div>