<?php

use App\Models\StudentCard;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
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

    #[Url]
    public $search = '';

    #[Url]
    public $statusFilter = '';



    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = StudentCard::query()
            ->with(['student', 'student.level', 'issuer'])
            ->when($schoolId, fn($q) => $q->whereHas('student', fn($s) => $s->where('school_id', $schoolId)))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn($q) => $q->where(function ($sub) {
                $sub->where('card_number', 'like', '%' . $this->search . '%')
                    ->orWhereHas('student', fn($s) => $s->where('full_name', 'like', '%' . $this->search . '%')
                        ->orWhere('nis', 'like', '%' . $this->search . '%'));
            }))
            ->orderBy('created_at', 'desc');

        return [
            'cards' => $query->paginate(15),
        ];
    }

    public function requestPrint($id)
    {
        $card = StudentCard::find($id);
        if ($card) {
            $card->update(['status' => 'request_print']);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Permintaan cetak berhasil dikirim.');
        }
    }

    public $bulkRequestCount = 0;

    public function openBulkRequestModal()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $this->bulkRequestCount = StudentCard::query()
            ->when($schoolId, fn($q) => $q->whereHas('student', fn($s) => $s->where('school_id', $schoolId)))
            ->where('status', 'generated')
            ->count();

        $this->modal('bulk-request-modal')->show();
    }

    public function bulkRequestPrint()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;
        $limit = 100;

        $affected = StudentCard::query()
            ->when($schoolId, fn($q) => $q->whereHas('student', fn($s) => $s->where('school_id', $schoolId)))
            ->where('status', 'generated')
            ->limit($limit)
            ->update(['status' => 'request_print']);

        if ($affected > 0) {
            Flux::toast(
                variant: 'success',
                heading: 'Berhasil',
                text: "Berhasil mengajukan cetak untuk {$affected} kartu."
            );
        } else {
            Flux::toast(
                variant: 'info',
                heading: 'Info',
                text: 'Tidak ada kartu baru yang perlu diajukan.'
            );
        }

        $this->modal('bulk-request-modal')->close();
    }

    public function getStatusColor($status)
    {
        return match ($status) {
            'generated' => 'zinc',
            'request_print' => 'orange',
            'printed' => 'green',
            default => 'zinc',
        };
    }

    public function getStatusLabel($status)
    {
        return match ($status) {
            'generated' => 'Generated',
            'request_print' => 'Menunggu Cetak',
            'printed' => 'Sudah Dicetak',
            default => $status,
        };
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Kartu Santri</flux:heading>
                <flux:subheading size="lg" class="mb-6">Kelola dan ajukan pencetakan kartu santri</flux:subheading>
            </div>
            <flux:button variant="filled" icon="printer" wire:click="openBulkRequestModal">
                {{ __('Ajukan Cetak Massal') }}
            </flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari Nama / NIS / No. Kartu..." />

        <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Filter Status" clearable>
            <flux:select.option value="">Semua Status</flux:select.option>
            <flux:select.option value="generated">Generated</flux:select.option>
            <flux:select.option value="request_print">Menunggu Cetak</flux:select.option>
            <flux:select.option value="printed">Sudah Dicetak</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$cards">
        <flux:table.columns>
            <flux:table.column>Santri</flux:table.column>
            <flux:table.column>Nomor Kartu</flux:table.column>
            <flux:table.column>Kelas</flux:table.column>
            <flux:table.column>Dibuat Oleh</flux:table.column>
            <flux:table.column>Tanggal Dibuat</flux:table.column>
            <flux:table.column>Tanggal Expired</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($cards as $card)
                <flux:table.row :key="$card->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar :name="$card->student->full_name" :src="$card->student->photo_url" size="sm" />
                            <div>
                                <div class="font-medium">{{ $card->student->full_name }}</div>
                                <div class="text-xs text-zinc-500 font-mono">{{ $card->student->nis }}</div>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="font-mono">{{ $card->card_number }}</flux:table.cell>

                    <flux:table.cell>
                        @if($card->student->level)
                            {{ $card->student->level->name }}
                        @else
                            -
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>{{ $card->issuer->name ?? '-' }}</flux:table.cell>

                    <flux:table.cell>{{ $card->issued_at ? $card->issued_at->format('d M Y') : '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $card->expires_at ? $card->expires_at->format('d M Y') : '-' }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$this->getStatusColor($card->status)">
                            {{ $this->getStatusLabel($card->status) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($card->status === 'generated')
                            <flux:button variant="primary" size="sm" icon="printer" wire:click="requestPrint('{{ $card->id }}')"
                                wire:confirm="Ajukan pencetakan kartu untuk santri ini?">
                                Ajukan Cetak
                            </flux:button>
                        @elseif($card->status === 'request_print')
                            <flux:button variant="ghost" size="sm" disabled>
                                Sedang Diajukan
                            </flux:button>
                        @elseif($card->status === 'printed')
                            <flux:button variant="ghost" size="sm" disabled icon="check">
                                Selesai
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.credit-card class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada kartu santri</flux:heading>
                                <flux:subheading>Gunakan menu Santri untuk generate kartu baru.</flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Bulk Request Modal -->
    <flux:modal name="bulk-request-modal" class="min-w-[25rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Ajukan Cetak Massal?</flux:heading>
                <div class="mt-3 space-y-3 text-sm text-zinc-500">
                    <p>
                        Akan mengajukan pencetakan untuk <strong
                            class="text-zinc-900 dark:text-zinc-100">{{ min(100, $bulkRequestCount) }}</strong> kartu
                        @if($bulkRequestCount > 100)
                            dari total <strong class="text-zinc-900 dark:text-zinc-100">{{ $bulkRequestCount }}</strong>
                            kartu
                        @endif
                        yang berstatus <strong>Generated</strong> (Belum diajukan).
                    </p>

                    <div
                        class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 rounded-lg border border-amber-200 dark:border-amber-800/30">
                        <p class="font-medium mb-1">Batasan Sistem</p>
                        <p class="text-xs opacity-90">
                            Sistem membatasi proses maksimal <strong>100 data</strong> per pengajuan.
                        </p>
                        @if($bulkRequestCount > 100)
                            <p class="text-xs opacity-90 mt-2 font-medium">
                                Karena data lebih dari 100, silakan ulangi proses int ini kembali setelah selesai.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="bulkRequestPrint" variant="primary">Lanjutkan Pengajuan</flux:button>
            </div>
        </div>
    </flux:modal>


</div>