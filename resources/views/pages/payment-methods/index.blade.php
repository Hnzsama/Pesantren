<?php

use Livewire\Attributes\Lazy;
use Livewire\Component;
use App\Models\PaymentChannel;
use App\Models\Setting;
use Flux\Flux;

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

    public $adminFee = 0;

    public function mount()
    {
        $this->adminFee = Setting::getGlobal('midtrans_admin_fee', 0);
    }

    public function saveFee()
    {
        $this->validate([
            'adminFee' => 'required|numeric|min:0',
        ]);

        Setting::setGlobal('midtrans_admin_fee', $this->adminFee);

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Biaya admin berhasil diperbarui.');
    }

    public function toggleChannel($id)
    {
        $channel = PaymentChannel::find($id);

        if ($channel) {
            $channel->is_active = !$channel->is_active;
            $channel->save();

            $status = $channel->is_active ? 'diaktifkan' : 'dinonaktifkan';
            Flux::toast(variant: 'success', heading: 'Berhasil', text: "Channel pembayaran {$status}.");
        }
    }

    public function with(): array
    {
        return [
            'channels' => PaymentChannel::all(),
        ];
    }
}; ?>

<div class="w-full space-y-8">
    <div class="relative w-full mb-6">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Metode Pembayaran') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Kelola metode pembayaran dan biaya layanan') }}</flux:subheading>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- General Settings -->
    <div class="space-y-4">
        <flux:heading size="lg">Pengaturan Umum</flux:heading>

        <form wire:submit="saveFee" class="flex items-end gap-4 max-w-md">
            <flux:field class="flex-1">
                <flux:label>Biaya Admin Midtrans (Global)</flux:label>
                <flux:input wire:model="adminFee" type="number" step="100" min="0" placeholder="0">
                    <x-slot name="prefix">Rp</x-slot>
                </flux:input>
                <flux:error name="adminFee" />
            </flux:field>

            <flux:button type="submit" variant="primary">Simpan</flux:button>
        </form>
    </div>

    <flux:separator variant="subtle" />

    <!-- Payment Channels -->
    <div class="space-y-4">
        <flux:heading size="lg">Channel Pembayaran</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama Channel</flux:table.column>
                <flux:table.column>Tipe</flux:table.column>
                <flux:table.column>Biaya Layanan</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($channels as $channel)
                    <flux:table.row :key="$channel->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                @if($channel->code)
                                    <!-- Placeholder for icon if needed -->
                                    <div class="font-medium">{{ $channel->name }}</div>
                                @else
                                    <div class="font-medium">{{ $channel->name }}</div>
                                @endif
                                <div class="text-xs text-zinc-500 font-mono">{{ $channel->code }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ str_replace('_', ' ', $channel->type) }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-sm">
                                @if($channel->fee_flat > 0)
                                    <div>Rp {{ number_format($channel->fee_flat, 0, ',', '.') }}</div>
                                @endif
                                @if($channel->fee_percent > 0)
                                    <div class="text-zinc-500">{{ $channel->fee_percent }}%</div>
                                @endif
                                @if($channel->fee_flat == 0 && $channel->fee_percent == 0)
                                    <div class="text-zinc-400 italic">Gratis</div>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$channel->is_active ? 'green' : 'red'">
                                {{ $channel->is_active ? 'Aktif' : 'Nonaktif' }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:switch wire:click="toggleChannel({{ $channel->id }})" :checked="$channel->is_active" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-12">
                            <div class="flex flex-col items-center gap-3">
                                <flux:icon.credit-card class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                                <div>
                                    <flux:heading size="lg" class="text-zinc-500">Tidak ada channel pembayaran
                                    </flux:heading>
                                    <flux:subheading>Belum ada channel pembayaran yang terdaftar.</flux:subheading>
                                </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>