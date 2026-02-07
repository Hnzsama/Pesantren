<?php

use App\Models\Feedback;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
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

    public $search = '';
    public $category = '';
    public $status = ''; // 'read', 'unread', 'replied'

    public $detailFeedback = null;
    public $replyMessage = '';

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        return [
            'feedbacks' => Feedback::query()
                ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->when($this->search, function ($q) {
                    $q->where('sender_name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('message', 'like', '%' . $this->search . '%');
                })
                ->when($this->category, fn($q) => $q->where('category', $this->category))
                ->when($this->status, function ($q) {
                    if ($this->status === 'read')
                        $q->where('is_read', true);
                    if ($this->status === 'unread')
                        $q->where('is_read', false);
                    if ($this->status === 'replied')
                        $q->whereNotNull('replied_at');
                })
                ->latest()
                ->paginate(10),
        ];
    }

    public function show($id)
    {
        $this->detailFeedback = Feedback::with(['user', 'school'])->findOrFail($id);

        // Mark as read if unread
        if (!$this->detailFeedback->is_read) {
            $this->detailFeedback->update(['is_read' => true]);
        }

        $this->replyMessage = $this->detailFeedback->admin_reply ?? '';
        Flux::modal('feedback-detail')->show();
    }

    public function saveReply()
    {
        $this->validate([
            'replyMessage' => 'required|string|min:5',
        ]);

        if ($this->detailFeedback) {
            $this->detailFeedback->update([
                'admin_reply' => $this->replyMessage,
                'replied_at' => now(),
            ]);

            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Balasan telah dikirim.');
            Flux::modal('feedback-detail')->close();
        }
    }

    public function closeDetail()
    {
        $this->detailFeedback = null;
        Flux::modal('feedback-detail')->close();
    }

    public function delete($id)
    {
        $feedback = Feedback::findOrFail($id);
        $feedback->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Feedback dihapus.');
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Feedback & Masukan</flux:heading>
                <flux:subheading size="lg" class="mb-6">Kelola masukan dari wali santri dan pengguna.</flux:subheading>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Cari pengirim, pesan..." />

        <flux:select wire:model.live="category" variant="listbox" placeholder="Semua Kategori" clearable>
            <flux:select.option value="fasilitas">Fasilitas</flux:select.option>
            <flux:select.option value="akademik">Akademik</flux:select.option>
            <flux:select.option value="layanan">Layanan</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="status" variant="listbox" placeholder="Semua Status" clearable>
            <flux:select.option value="unread">Belum Dibaca</flux:select.option>
            <flux:select.option value="read">Sudah Dibaca</flux:select.option>
            <flux:select.option value="replied">Sudah Dibalas</flux:select.option>
        </flux:select>
    </div>

    <!-- Table -->
    <flux:table :paginate="$feedbacks">
        <flux:table.columns>
            <flux:table.column>Pengirim</flux:table.column>
            <flux:table.column>Kontak</flux:table.column>
            <flux:table.column>Kategori</flux:table.column>
            <flux:table.column>Pesan</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($feedbacks as $item)
                <flux:table.row wire:key="{{ $item->id }}">
                    <flux:table.cell>
                        <div class="flex items-center gap-2 mb-1">
                            <div
                                class="{{ !$item->is_read ? 'font-bold text-zinc-900 dark:text-white' : 'font-medium text-zinc-600 dark:text-zinc-300' }}">
                                {{ $item->sender_name }}
                            </div>
                            @if($item->user_id)
                                <flux:badge size="xs" color="blue" inset="top bottom">User</flux:badge>
                            @else
                                <flux:badge size="xs" color="zinc" inset="top bottom">Tamu</flux:badge>
                            @endif
                        </div>
                        <div class="text-xs text-zinc-500">{{ $item->school->name ?? 'Umum' }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="text-sm">{{ $item->email }}</div>
                        <div class="text-xs text-zinc-500">{{ $item->phone }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">{{ ucfirst($item->category) }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div
                            class="truncate max-w-xs text-sm {{ !$item->is_read ? 'font-semibold text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}">
                            {{ Str::limit($item->message, 50) }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($item->user_id && $item->replied_at)
                            <flux:badge size="sm" color="green" icon="check-circle">Dibalas</flux:badge>
                        @elseif(!$item->is_read)
                            <flux:badge size="sm" color="orange">Baru</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Dibaca</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="text-sm">{{ $item->created_at->format('d/m/Y') }}</div>
                        <div class="text-xs text-zinc-500">{{ $item->created_at->format('H:i') }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button wire:click="show('{{ $item->id }}')" size="sm" icon="eye">Lihat</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-6 text-zinc-500">
                        Belum ada feedback.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Detail & Reply Modal -->
    <flux:modal name="feedback-detail" class="space-y-6 md:w-2xl">
        @if($detailFeedback)
            <div>
                <flux:heading size="lg">Detail Feedback</flux:heading>
                <div class="text-sm text-zinc-500 mt-1">
                    Dari {{ $detailFeedback->sender_name }} • {{ $detailFeedback->created_at->format('d M Y H:i') }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="font-medium text-zinc-500">Email</div>
                    <a href="mailto:{{ $detailFeedback->email }}" class="text-blue-600 hover:underline">
                        {{ $detailFeedback->email }}
                    </a>
                </div>
                <div>
                    <div class="font-medium text-zinc-500">Telepon</div>
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $detailFeedback->phone) }}" target="_blank"
                        class="text-blue-600 hover:underline">
                        {{ $detailFeedback->phone }}
                    </a>
                </div>
                <div>
                    <div class="font-medium text-zinc-500">Kategori</div>
                    <div>{{ ucfirst($detailFeedback->category) }}</div>
                </div>
                <div>
                    <div class="font-medium text-zinc-500">Sekolah</div>
                    <div>{{ $detailFeedback->school->name ?? '-' }}</div>
                </div>
            </div>

            <flux:separator />

            <div>
                <flux:label>Pesan</flux:label>
                <div
                    class="mt-2 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg text-sm whitespace-pre-wrap border border-zinc-200 dark:border-zinc-800 text-start">
                    {{ $detailFeedback->message }}
                </div>
            </div>

            @if($detailFeedback->user_id)
                <div>
                    <flux:textarea wire:model="replyMessage" label="Balasan Admin" placeholder="Tulis balasan anda disini..."
                        rows="4" />
                    @if($detailFeedback->replied_at)
                        <div class="text-xs text-green-600 mt-2 flex items-center gap-1">
                            <flux:icon name="check-circle" size="xs" />
                            Dibalas pada {{ $detailFeedback->replied_at->format('d M Y H:i') }}
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex justify-between items-center">
                <flux:button variant="danger" wire:click="delete('{{ $detailFeedback->id }}')"
                    wire:confirm="Hapus feedback ini?">Hapus</flux:button>
                <div class="flex gap-2">
                    <flux:button variant="ghost" wire:click="closeDetail">Tutup</flux:button>
                    @if($detailFeedback->user_id)
                        <flux:button variant="primary" wire:click="saveReply">Kirim Balasan</flux:button>
                    @endif
                </div>
            </div>
        @else
            <div class="flex justify-center py-8">
                <flux:icon.loading class="w-8 h-8 animate-spin text-zinc-400" />
            </div>
        @endif
    </flux:modal>
</div>