<?php

use App\Models\StudentPermission;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Flux\Flux;
use Illuminate\Support\Str;

new #[Lazy] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

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
            
            <div class="flex gap-4 mb-6">
                <div class="h-10 grow bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
                <div class="h-10 w-48 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 animate-pulse"></div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="p-6 bg-white dark:bg-zinc-900 space-y-4">
                    @foreach(range(1, 10) as $i)
                        <div class="flex items-center gap-4">
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-zinc-100 dark:bg-zinc-800 rounded w-1/4 animate-pulse"></div>
                                <div class="h-3 bg-zinc-50 dark:bg-zinc-900 rounded w-1/6 animate-pulse"></div>
                            </div>
                            <div class="w-24 h-6 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
                        </div>
                        <div class="h-px bg-zinc-100 dark:bg-zinc-800 w-full"></div>
                    @endforeach
                </div>
            </div>
        </div>
        HTML;
    }

    public $search = '';
    public $statusFilter = '';

    public $permissionId = null;
    public $student_id = '';
    public $reason = '';
    public $start_date = '';
    public $end_date = '';
    public $status = 'pending';
    public $admin_note = '';

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $permissions = StudentPermission::query()
            ->with('student')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->search, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('full_name', 'like', '%' . $this->search . '%')))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $students = Student::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('full_name')
            ->get();

        return [
            'permissions' => $permissions,
            'students' => $students
        ];
    }

    public function create()
    {
        $this->reset(['permissionId', 'student_id', 'reason', 'start_date', 'end_date', 'status', 'admin_note']);
        $this->status = 'pending';
        Flux::modal('permission-form')->show();
    }

    public function edit($id)
    {
        $permission = StudentPermission::findOrFail($id);
        $this->permissionId = $permission->id;
        $this->student_id = $permission->student_id;
        $this->reason = $permission->reason;
        $this->start_date = $permission->start_date->format('Y-m-d');
        $this->end_date = $permission->end_date->format('Y-m-d');
        $this->status = $permission->status;
        $this->admin_note = $permission->admin_note;

        Flux::modal('permission-form')->show();
    }

    public function save()
    {
        $this->validate([
            'student_id' => 'required|exists:students,id',
            'reason' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:pending,approved,rejected',
            'admin_note' => 'nullable|string',
        ]);

        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;
        $student = Student::find($this->student_id);

        if (!$student) {
            Flux::toast(variant: 'danger', heading: 'Error', text: 'Data santri tidak ditemukan.');
            return;
        }

        if ($this->permissionId) {
            $permission = StudentPermission::find($this->permissionId);
            $permission->update([
                'student_id' => $this->student_id,
                'student_name' => $student->full_name, // Update name as backup
                'reason' => $this->reason,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'status' => $this->status,
                'admin_note' => $this->admin_note,
            ]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data perizinan diperbarui.');
        } else {
            StudentPermission::create([
                'school_id' => $schoolId,
                'student_id' => $this->student_id,
                'student_name' => $student->full_name,
                'reason' => $this->reason,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'status' => $this->status,
                'admin_note' => $this->admin_note,
            ]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data perizinan dibuat.');
        }

        Flux::modal('permission-form')->close();
    }

    public function delete($id)
    {
        $permission = StudentPermission::findOrFail($id);
        $permission->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data perizinan dihapus.');
    }

    public function getStatusColor($status)
    {
        return match ($status) {
            'approved' => 'green',
            'pending' => 'orange',
            'rejected' => 'red',
            default => 'zinc'
        };
    }

    public function getStatusLabel($status)
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'pending' => 'Menunggu',
            'rejected' => 'Ditolak',
            default => $status
        };
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">Perizinan Santri</flux:heading>
                <flux:subheading size="lg">Kelola data perizinan pulang/sakit santri.</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah Izin</flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari nama santri..." class="flex-1" />
        <flux:select wire:model.live="statusFilter" placeholder="Filter Status" class="w-48" clearable>
            <flux:select.option value="pending">Menunggu</flux:select.option>
            <flux:select.option value="approved">Disetujui</flux:select.option>
            <flux:select.option value="rejected">Ditolak</flux:select.option>
        </flux:select>
    </div>

    <!-- Table -->
    <flux:table :paginate="$permissions">
        <flux:table.columns>
            <flux:table.column>Santri</flux:table.column>
            <flux:table.column>Alasan</flux:table.column>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Catatan Admin</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($permissions as $permission)
                <flux:table.row :key="$permission->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$permission->student->full_name ?? $permission->student_name" />
                            <div>
                                <div class="font-medium">{{ $permission->student->full_name ?? $permission->student_name }}
                                </div>
                                <div class="text-xs text-zinc-500">{{ $permission->student->nis ?? '-' }}</div>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="line-clamp-2" title="{{ $permission->reason }}">{{ $permission->reason }}</span>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="text-sm">
                            <div>{{ $permission->start_date->format('d M Y') }}</div>
                            <div class="text-zinc-400 text-xs">s.d {{ $permission->end_date->format('d M Y') }}</div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$this->getStatusColor($permission->status)">
                            {{ $this->getStatusLabel($permission->status) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="text-zinc-500 text-sm line-clamp-1">{{ $permission->admin_note ?? '-' }}</span>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                <flux:menu.item wire:click="edit('{{ $permission->id }}')" icon="pencil">Edit / Update
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="delete('{{ $permission->id }}')" icon="trash" variant="danger">
                                    Hapus</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-8">
                        <div class="text-zinc-500">Belum ada data perizinan.</div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Create/Edit Flyout -->
    <flux:modal name="permission-form" flyout class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $permissionId ? 'Edit Perizinan' : 'Tambah Izin Baru' }}</flux:heading>
            <flux:subheading>Isi detail perizinan santri.</flux:subheading>
        </div>

        <flux:separator />

        <div class="space-y-6">
            <flux:field>
                <flux:label>Pilih Santri</flux:label>
                <flux:select wire:model="student_id" variant="combobox" placeholder="Cari santri...">
                    @foreach($students as $student)
                        <flux:select.option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->nis }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="student_id" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="date" wire:model="start_date" label="Tanggal Mulai" />
                <flux:input type="date" wire:model="end_date" label="Tanggal Selesai" />
            </div>

            <flux:textarea wire:model="reason" label="Alasan Izin" rows="3"
                placeholder="Contoh: Sakit demam, Acara keluarga..." />

            <flux:separator variant="subtle" />

            <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:label class="mb-3">Status Persetujuan</flux:label>
                <flux:radio.group wire:model="status" layout="horizontal" class="gap-4">
                    <flux:radio value="pending" label="Menunggu" />
                    <flux:radio value="approved" label="Disetujui" />
                    <flux:radio value="rejected" label="Ditolak" />
                </flux:radio.group>
            </div>

            <flux:textarea wire:model="admin_note" label="Catatan Admin (Opsional)" rows="2"
                placeholder="Catatan khusus dari admin..." />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="$dispatch('close-modal', { name: 'permission-form' })">Batal
                </flux:button>
                <flux:button variant="primary" wire:click="save">Simpan Data</flux:button>
            </div>
        </div>
    </flux:modal>
</div>