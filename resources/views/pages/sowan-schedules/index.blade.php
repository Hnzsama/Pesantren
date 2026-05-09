<?php

use App\Models\SowanSchedule;
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

    public $scheduleId = null;
    public $guest_name = '';
    public $request_date = '';
    public $request_time = '';
    public $topic = '';
    public $status = 'pending';

    public function mount()
    {
        $this->request_date = now()->format('Y-m-d');
        $this->request_time = now()->format('H:i');
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        return [
            'schedules' => SowanSchedule::query()
                ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->when($this->search, fn($q) => $q->where('guest_name', 'like', '%' . $this->search . '%'))
                ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
                ->orderBy('request_date', 'desc')
                ->paginate(10),
        ];
    }

    public function create()
    {
        $this->reset(['scheduleId', 'guest_name', 'topic', 'status']);
        $this->request_date = now()->format('Y-m-d');
        $this->request_time = now()->format('H:i');
        $this->status = 'pending';
        Flux::modal('sowan-form')->show();
    }

    public function edit($id)
    {
        $schedule = SowanSchedule::findOrFail($id);
        $this->scheduleId = $schedule->id;
        $this->guest_name = $schedule->guest_name;
        $this->topic = $schedule->topic;
        $this->request_date = $schedule->request_date->format('Y-m-d');
        $this->request_time = $schedule->request_date->format('H:i');
        $this->status = $schedule->status;

        Flux::modal('sowan-form')->show();
    }

    public function save()
    {
        $this->validate([
            'guest_name' => 'required|string|max:255',
            'request_date' => 'required|date',
            'request_time' => 'required',
            'topic' => 'required|string',
            'status' => 'required|in:pending,confirmed,completed',
        ]);

        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $dateTime = \Carbon\Carbon::parse($this->request_date . ' ' . $this->request_time);

        if ($this->scheduleId) {
            $schedule = SowanSchedule::find($this->scheduleId);
            $schedule->update([
                'guest_name' => $this->guest_name,
                'request_date' => $dateTime,
                'topic' => $this->topic,
                'status' => $this->status,
            ]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jadwal sowan diperbarui.');
        } else {
            SowanSchedule::create([
                'school_id' => $schoolId,
                'guest_name' => $this->guest_name,
                'request_date' => $dateTime,
                'topic' => $this->topic,
                'status' => $this->status,
            ]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jadwal sowan dibuat.');
        }

        Flux::modal('sowan-form')->close();
    }

    public function delete($id)
    {
        $schedule = SowanSchedule::findOrFail($id);
        $schedule->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jadwal sowan dihapus.');
    }

    public function markAsConfirmed($id)
    {
        $schedule = SowanSchedule::findOrFail($id);
        $schedule->update(['status' => 'confirmed']);
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jadwal sowan dikonfirmasi.');
    }

    public function markAsCompleted($id)
    {
        $schedule = SowanSchedule::findOrFail($id);
        $schedule->update(['status' => 'completed']);
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jadwal sowan selesai.');
    }

    public function getStatusColor($status)
    {
        return match ($status) {
            'confirmed' => 'blue',
            'pending' => 'orange',
            'completed' => 'green',
            default => 'zinc'
        };
    }

    public function getStatusLabel($status)
    {
        return match ($status) {
            'confirmed' => 'Dikonfirmasi',
            'pending' => 'Menunggu',
            'completed' => 'Selesai',
            default => $status
        };
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">Jadwal Sowan</flux:heading>
                <flux:subheading size="lg">Kelola jadwal tamu dan sowan pengasuh.</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="create">Catat Jadwal</flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari nama tamu..." class="flex-1" />
        <flux:select wire:model.live="statusFilter" placeholder="Filter Status" class="w-48" clearable>
            <flux:select.option value="pending">Menunggu</flux:select.option>
            <flux:select.option value="confirmed">Dikonfirmasi</flux:select.option>
            <flux:select.option value="completed">Selesai</flux:select.option>
        </flux:select>
    </div>

    <!-- Table -->
    <flux:table :paginate="$schedules">
        <flux:table.columns>
            <flux:table.column>Nama Tamu</flux:table.column>
            <flux:table.column>Waktu</flux:table.column>
            <flux:table.column>Keperluan</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($schedules as $schedule)
                <flux:table.row :key="$schedule->id">
                    <flux:table.cell>
                        <div class="font-medium">{{ $schedule->guest_name }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium">{{ $schedule->request_date->isoFormat('dddd, D MMMM Y') }}</span>
                            <span class="text-sm text-zinc-500">{{ $schedule->request_date->format('H:i') }} WIB</span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="line-clamp-2">{{ $schedule->topic }}</span>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$this->getStatusColor($schedule->status)">
                            {{ $this->getStatusLabel($schedule->status) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            @if($schedule->status === 'pending')
                                <flux:button size="sm" variant="primary" wire:click="markAsConfirmed('{{ $schedule->id }}')">
                                    Konfirmasi</flux:button>
                            @endif
                            @if($schedule->status === 'confirmed')
                                <flux:button size="sm" variant="filled" wire:click="markAsCompleted('{{ $schedule->id }}')">
                                    Selesai</flux:button>
                            @endif

                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item wire:click="edit('{{ $schedule->id }}')" icon="pencil">Edit
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item wire:click="delete('{{ $schedule->id }}')" icon="trash"
                                        variant="danger">
                                        Hapus</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center py-8">
                        <div class="text-zinc-500">Belum ada jadwal sowan.</div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Create/Edit Flyout -->
    <flux:modal name="sowan-form" flyout class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $scheduleId ? 'Edit Jadwal' : 'Catat Jadwal Baru' }}</flux:heading>
            <flux:subheading>Isi detail jadwal sowan.</flux:subheading>
        </div>

        <flux:separator />

        <div class="space-y-6">
            <flux:input wire:model="guest_name" label="Nama Tamu" placeholder="Nama lengkap tamu..." />

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="date" wire:model="request_date" label="Tanggal" />
                <flux:input type="time" wire:model="request_time" label="Jam" />
            </div>

            <flux:textarea wire:model="topic" label="Keperluan / Topik Pembicaraan" rows="4"
                placeholder="Jelaskan keperluan..." />

            <flux:separator variant="subtle" />

            <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:label class="mb-3">Status Jadwal</flux:label>
                <flux:radio.group wire:model="status" layout="horizontal" class="gap-4">
                    <flux:radio value="pending" label="Menunggu" />
                    <flux:radio value="confirmed" label="Dikonfirmasi" />
                    <flux:radio value="completed" label="Selesai" />
                </flux:radio.group>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="$dispatch('close-modal', { name: 'sowan-form' })">Batal
                </flux:button>
                <flux:button variant="primary" wire:click="save">Simpan Jadwal</flux:button>
            </div>
        </div>
    </flux:modal>
</div>