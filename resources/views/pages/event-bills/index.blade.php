<?php

use App\Models\Bill;
use App\Models\BillCategory;
use App\Models\Student;
use App\Models\Event; // Add Event model
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Flux\Flux;

new #[Lazy] #[Layout('layouts.app')] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full animate-pulse">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div class="space-y-2">
                    <div class="h-8 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
                    <div class="h-4 w-48 bg-zinc-100 dark:bg-zinc-900 rounded-lg"></div>
                </div>
                <div class="h-10 w-32 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            </div>
            <div class="space-y-4">
                <div class="h-12 w-full bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-64 w-full bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800"></div>
            </div>
        </div>
        HTML;
    }

    use WithPagination;

    // Filters
    public $search = '';
    public $studentFilter = '';
    public $eventFilter = ''; // Filter by Event
    public $statusFilter = '';
    public $dateFilter = '';

    // Modal States
    public $selectedBill = null;

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = Bill::query()
            ->with(['student', 'category', 'event', 'school'])
            ->whereNotNull('event_id') // Only Event Bills
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->studentFilter, fn($q) => $q->where('student_id', $this->studentFilter))
            ->when($this->eventFilter, fn($q) => $q->where('event_id', $this->eventFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn($q) => $q->where(function ($sub) {
                $sub->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('invoice_code', 'like', '%' . $this->search . '%')
                    ->orWhereHas('student', fn($s) => $s->where('full_name', 'like', '%' . $this->search . '%')
                        ->orWhere('nis', 'like', '%' . $this->search . '%'));
            }))
            ->orderBy('created_at', 'desc');

        return [
            'bills' => $query->paginate(15),
            'students' => $schoolId ? Student::where('school_id', $schoolId)->orderBy('full_name')->get() : [],
            'events' => $schoolId ? Event::where('school_id', $schoolId)->orderBy('title')->get() : [],
        ];
    }

    public function viewDetail($id)
    {
        $this->selectedBill = Bill::with(['student', 'category', 'event'])->find($id);
        $this->modal('bill-detail-modal')->show();
    }

    public function closeDetail()
    {
        $this->selectedBill = null;
        $this->modal('bill-detail-modal')->close();
    }

    // Deletion could be allowed but usually event bills are tied to event attendance/participation logic.
    // For now allow deletion.
    public function delete($id)
    {
        $bill = Bill::findOrFail($id);
        if ($bill->status === 'PAID') {
            Flux::toast(variant: 'danger', heading: 'Gagal', text: 'Tagihan yang sudah lunas tidak dapat dihapus.');
            return;
        }
        $bill->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Tagihan berhasil dihapus.');
    }

    public function getStatusColor($status)
    {
        return match ($status) {
            'PAID' => 'green',
            'UNPAID' => 'orange',
            'CANCELLED' => 'red',
            default => 'zinc',
        };
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Tagihan Kegiatan') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Kelola tagihan dari agenda/event sekolah') }}
                </flux:subheading>
            </div>
            <!-- No Create Button here, Bills are created from Events Page -->
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Cari tagihan/siswa..." />

            <flux:select wire:model.live="eventFilter" variant="listbox" placeholder="Filter Kegiatan" clearable>
                @foreach ($events as $event)
                    <flux:select.option value="{{ $event->id }}">{{ $event->title }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="studentFilter" variant="listbox" placeholder="Filter Santri" searchable
                clearable>
                @foreach ($students as $student)
                    <flux:select.option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->nis }})
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Status" clearable>
                <flux:select.option value="UNPAID">Belum Lunas</flux:select.option>
                <flux:select.option value="PAID">Lunas</flux:select.option>
                <flux:select.option value="CANCELLED">Dibatalkan</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$bills">
            <flux:table.columns>
                <flux:table.column>Invoice</flux:table.column>
                <flux:table.column>Santri</flux:table.column>
                <flux:table.column>Kegiatan</flux:table.table.column> <!-- Event Title -->
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Jumlah</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($bills as $bill)
                                <flux:table.row :key="$bill->id">
                                    <flux:table.cell>
                                        <div class="font-mono text-sm">{{ $bill->invoice_code }}</div>
                                        <div class="text-xs text-zinc-500">
                                            {{ \Carbon\Carbon::parse($bill->created_at)->format('d M Y') }}</div>
                                    </flux:table.cell>

                                    <flux:table.cell>
                                        <div class="flex items-center gap-2">
                                            @if($bill->student)
                                                <flux:avatar size="sm" :name="$bill->student->full_name" />
                                                <div>
                                                    <div class="font-medium">{{ $bill->student->full_name }}</div>
                                                    <div class="text-xs text-zinc-500">{{ $bill->student->nis }}</div>
                                                </div>
                                            @else
                                                <span class="text-zinc-400">-</span>
                                            @endif
                                        </div>
                                    </flux:table.cell>

                                    <flux:table.cell>
                                        @if($bill->event)
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $bill->event->title }}</div>
                                            <div class="text-xs text-zinc-500">{{ $bill->event->event_date->format('d M Y') }}</div>
                                        @else
                                            <span class="text-zinc-400">Manual/Terhapus</span>
                                        @endif
                                    </flux:table.cell>

                                    <flux:table.cell>
                                        <flux:badge :color="$this->getStatusColor($bill->status)">
                                            {{ match ($bill->status) {
                        'PAID' => 'Lunas',
                        'UNPAID' => 'Belum Lunas',
                        'CANCELLED' => 'Dibatalkan',
                        default => $bill->status
                    } }}
                                        </flux:badge>
                                    </flux:table.cell>

                                    <flux:table.cell>
                                        <div class="font-medium">Rp {{ number_format($bill->amount, 0, ',', '.') }}</div>
                                        @if($bill->paid_at)
                                            <div class="text-xs text-green-600">Dibayar:
                                                {{ \Carbon\Carbon::parse($bill->paid_at)->format('d/m/Y') }}</div>
                                        @endif
                                    </flux:table.cell>

                                    <flux:table.cell>
                                        <flux:dropdown>
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                            <flux:menu>
                                                <flux:menu.item wire:click="viewDetail('{{ $bill->id }}')" icon="eye">Detail
                                                </flux:menu.item>
                                                <flux:menu.item wire:click="delete('{{ $bill->id }}')" icon="trash" variant="danger">
                                                    Hapus</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </flux:table.cell>
                                </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8">
                            <div class="flex flex-col items-center gap-3">
                                <flux:icon.document-text class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                                <div>
                                    <flux:heading size="lg" class="text-zinc-500">Tidak ada tagihan kegiatan</flux:heading>
                                    <flux:subheading>Belum ada data tagihan kegiatan yang dibuat.</flux:subheading>
                                </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>


    <!-- Detail Flyout -->
    <flux:modal name="bill-detail-modal" flyout class="space-y-6">
        @if($selectedBill)
                <div class="space-y-6">
                    <!-- Header -->
                    <div>
                        <flux:heading size="lg">Detail Tagihan Kegiatan</flux:heading>
                        <flux:subheading class="font-mono">{{ $selectedBill->invoice_code }}</flux:subheading>
                    </div>

                    <!-- Status Badge -->
                    <div>
                        <flux:badge :color="$this->getStatusColor($selectedBill->status)">
                            {{ match ($selectedBill->status) {
                'PAID' => 'Lunas',
                'UNPAID' => 'Belum Lunas',
                'CANCELLED' => 'Dibatalkan',
                default => $selectedBill->status
            } }}
                        </flux:badge>
                    </div>

                    <flux:separator />

                    <!-- Student Info -->
                    @if($selectedBill->student)
                        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-lg p-4">
                            <flux:label class="mb-3">Santri</flux:label>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="lg" :name="$selectedBill->student->full_name" />
                                <div>
                                    <div class="font-semibold text-lg">{{ $selectedBill->student->full_name }}</div>
                                    <div class="text-sm text-zinc-500">NIS: {{ $selectedBill->student->nis ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Amount Card -->
                    <div
                        class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-6">
                        <flux:label class="mb-3">Total Tagihan</flux:label>
                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                            Rp {{ number_format($selectedBill->amount, 0, ',', '.') }}
                        </div>
                    </div>

                    <!-- Bill Details -->
                    <div class="space-y-4">
                        @if($selectedBill->event)
                            <div>
                                <flux:label class="mb-2">Nama Kegiatan</flux:label>
                                <div class="font-medium text-lg">{{ $selectedBill->event->title }}</div>
                                <div class="text-sm text-zinc-500">{{ $selectedBill->event->event_date->format('d M Y') }}</div>
                            </div>
                        @endif

                        <div>
                            <flux:label class="mb-2">Judul Tagihan</flux:label>
                            <p class="text-zinc-700 dark:text-zinc-300">{{ $selectedBill->title }}</p>
                        </div>

                        <div>
                            <flux:label class="mb-2">Jatuh Tempo</flux:label>
                            <div class="font-medium">{{ \Carbon\Carbon::parse($selectedBill->due_date)->format('d M Y') }}</div>
                        </div>
                    </div>
                </div>
        @endif
    </flux:modal>
</div>