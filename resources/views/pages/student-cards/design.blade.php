<?php

use App\Models\School;
use App\Models\StudentCard;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component {
    public $schoolFilter = '';
    public $statusFilter = 'request_print';
    public $previewCardId = null;
    public $previewSide = 'front'; // Added: front or back

    public function mount()
    {
        $firstSchool = School::first();
        if ($firstSchool) {
            $this->schoolFilter = $firstSchool->id;
            $this->updatePreview();
        }
    }

    public function updatedSchoolFilter()
    {
        $this->updatePreview();
    }

    public function updatedStatusFilter()
    {
        $this->updatePreview();
    }

    public function updatePreview()
    {
        $this->previewCardId = null;
        
        if ($this->schoolFilter) {
            $card = StudentCard::query()
                ->whereHas('student', fn($q) => $q->where('school_id', $this->schoolFilter))
                ->where('status', $this->statusFilter)
                ->latest()
                ->first();
                
            if ($card) {
                $this->previewCardId = $card->id;
            }
        }
    }

    use \Livewire\Features\SupportFileUploads\WithFileUploads;

    public $cachedLogo = null;
    public $noBackground = false;
    public $processingBatchId = null;
    public $processingTotal = 0;
    public $processingCount = 0;
    public $processingIds = [];
    public $isProcessing = false;
    public $zipDownloadUrl = null;

    public function initBatchDownload()
    {
        $this->validate([
            'schoolFilter' => 'required',
        ]);

        $school = School::find($this->schoolFilter);
        if ($school && $school->logo_path) {
            $fullPath = storage_path('app/public/' . $school->logo_path);
            if (file_exists($fullPath)) {
                $type = pathinfo($fullPath, PATHINFO_EXTENSION);
                $data = file_get_contents($fullPath);
                $this->cachedLogo = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        $query = StudentCard::query()
            ->whereHas('student', fn($q) => $q->where('school_id', $this->schoolFilter))
            ->where('status', $this->statusFilter);
            
        $this->processingIds = $query->pluck('id')->toArray();
        $this->processingTotal = count($this->processingIds);
        $this->processingCount = 0;
        
        if ($this->processingTotal === 0) {
            \Flux\Flux::toast(variant: 'warning', text: 'Tidak ada kartu untuk dicetak.');
            return;
        }

        $this->processingBatchId = uniqid();
        $tempDir = storage_path("app/temp/cards/{$this->processingBatchId}");
        \Illuminate\Support\Facades\File::ensureDirectoryExists($tempDir);

        $this->isProcessing = true;
        $this->dispatch('start-batch-process', ids: $this->processingIds);
        $this->modal('batch-download-modal')->show();
    }

    public function processBatchChunk(array $cardIds)
    {
        $batchId = $this->processingBatchId;
        $tempDir = storage_path("app/temp/cards/{$batchId}");
        
        $items = [];
        $qrRenderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(120, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $qrWriter = new \BaconQrCode\Writer($qrRenderer);

        foreach ($cardIds as $cardId) {
            $card = StudentCard::with(['student.level.department', 'student.school'])->find($cardId);
            if (!$card) continue;

            $qrCodeSvg = $qrWriter->writeString($card->card_number);
            $student = $card->student;
            
            // Render BOTH sides
            foreach(['front', 'back'] as $side) {
                $html = view('pages.student-cards.print', [
                    'card' => $card,
                    'logoBase64' => $this->cachedLogo,
                    'qrCodeSvg' => $qrCodeSvg,
                    'noBackground' => $this->noBackground,
                    'side' => $side,
                ])->render();

                $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $student->full_name);
                $imageName = "{$student->nis}_{$safeName}_{$side}.png";
                $imagePath = "{$tempDir}/{$imageName}";

                $items[] = [
                    'html' => $html,
                    'path' => $imagePath
                ];
            }
        }

        if (empty($items)) return;

        $batchFile = "{$tempDir}/batch_" . uniqid() . ".json";
        file_put_contents($batchFile, json_encode(['items' => $items]));

        try {
            $command = "PATH=\$PATH:/usr/local/bin:/opt/homebrew/bin /home/akuma/.nvm/versions/node/v24.13.1/bin/node " . base_path('batch_render.cjs') . " " . escapeshellarg($batchFile);
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                \Log::error("Node batch render failed: " . implode("\n", $output));
            }
            
            $this->processingCount += count($cardIds); // We increment by number of cards, not items (items = cards * 2)
        } catch (\Exception $e) {
            \Log::error("Failed to process batch chunk: " . $e->getMessage());
        } finally {
            if (file_exists($batchFile)) unlink($batchFile);
        }
    }

    public function finalizeBatch()
    {
        $batchId = $this->processingBatchId;
        $tempDir = storage_path("app/temp/cards/{$batchId}");
        $zipPath = storage_path("app/temp/cards/{$batchId}.zip");
        
        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            $files = \Illuminate\Support\Facades\File::files($tempDir);
            $cards = StudentCard::with(['student.level.department'])->whereIn('id', $this->processingIds)->get()->keyBy('student.nis');

            foreach ($files as $file) {
                 $filename = $file->getFilename();
                 $parts = explode('_', $filename);
                 $nis = $parts[0];
                 $side = str_replace('.png', '', end($parts)); // front or back
                 
                 $folderPath = "Kartu";
                 
                 foreach($cards as $card) {
                     $student = $card->student;
                     if ($student->nis === $nis) {
                        $jenjangName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $student->level->department->name ?? 'Lainnya');
                        $kelasName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $student->level->name ?? 'Tanpa Kelas');
                        $studentNameSafe = preg_replace('/[^A-Za-z0-9_\- ]/', '', $student->full_name);
                        $studentFolder = "{$student->nis}_{$studentNameSafe}";
                        $folderPath = "{$jenjangName}/{$kelasName}/{$studentFolder}";
                        break;
                     }
                 }
                 
                 $zip->addFile($file->getPathname(), "{$folderPath}/{$filename}");
            }
            $zip->close();
        }

        \Illuminate\Support\Facades\File::deleteDirectory($tempDir);
        
        $this->isProcessing = false;
        $schoolName = $this->schoolFilter ? School::find($this->schoolFilter)->name : 'Kartu Santri';
        $safeName = preg_replace('/[^A-Za-z0-9_\- ]/', '', $schoolName);
        $this->zipDownloadUrl = route('student-cards.download-file', [
            'filename' => "{$batchId}.zip",
            'name' => "{$safeName}.zip"
        ]);
    }

    public function with(): array
    {
        return [
            'schools' => School::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Desain & Ekspor Kartu</flux:heading>
                <flux:subheading size="lg" class="mb-6">Preview desain kartu dan ekspor untuk pencetakan massal.</flux:subheading>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Controls Column -->
        <div class="lg:col-span-4 space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">Pengaturan Ekspor</flux:heading>
                
                <div class="space-y-4">
                    <flux:select wire:model.live="schoolFilter" label="Pilih Sekolah" placeholder="Pilih sekolah...">
                        @foreach ($schools as $school)
                            <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:checkbox wire:model.live="noBackground" label="Hanya Kartu (Tanpa Background)" />

                    <flux:select wire:model.live="statusFilter" label="Status Kartu" placeholder="Pilih status...">
                        <flux:select.option value="request_print">Menunggu Cetak</flux:select.option>
                        <flux:select.option value="printed">Sudah Dicetak</flux:select.option>
                        <flux:select.option value="generated">Generated</flux:select.option>
                    </flux:select>

                    <flux:radio.group wire:model.live="previewSide" label="Preview Sisi" variant="segmented">
                        <flux:radio value="front" label="Bagian Depan" />
                        <flux:radio value="back" label="Bagian Belakang" />
                    </flux:radio.group>

                    <div class="pt-4 border-t border-zinc-100 dark:border-zinc-700">
                        <flux:text class="mb-4 text-sm text-zinc-500">
                            Unduh file ZIP berisi seluruh kartu santri (Depan & Belakang) dengan kriteria di atas.
                        </flux:text>

                        <flux:button wire:click="initBatchDownload" variant="primary" icon="printer" class="w-full" :disabled="!$schoolFilter || !$previewCardId">
                            Unduh ZIP (Depan & Belakang)
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Preview Column -->
        <div class="lg:col-span-8">
            <div class="w-full h-full" x-data="{ side: @entangle('previewSide') }">
                    @if ($previewCardId)
                        @php
                            $previewCard = StudentCard::find($previewCardId);
                            $qrRenderer = new \BaconQrCode\Renderer\ImageRenderer(
                                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(120, 1),
                                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
                            );
                            $qrWriter = new \BaconQrCode\Writer($qrRenderer);
                            $qrCodeSvg = $qrWriter->writeString($previewCard->card_number);

                            if (!$this->cachedLogo && $previewCard->student->school->logo_path) {
                                $logoPath = $previewCard->student->school->logo_path;
                                $fullPath = storage_path('app/public/' . $logoPath);
                                if (file_exists($fullPath)) {
                                    $type = pathinfo($fullPath, PATHINFO_EXTENSION);
                                    $data = file_get_contents($fullPath);
                                    $this->cachedLogo = 'data:image/' . $type . ';base64,' . base64_encode($data);
                                }
                            }
                        @endphp
                        
                        <div class="flip-card cursor-pointer origin-top-left scale-[0.35] sm:scale-[0.5] md:scale-[0.55] lg:scale-[0.6] xl:scale-[0.7] 2xl:scale-[0.8] transition-transform duration-300"
                             @click="side = (side === 'front' ? 'back' : 'front')"
                             :class="{ 'is-flipped': side === 'back' }">
                            <div class="flip-card-inner">
                                <div class="flip-card-front">
                                    <x-student-card 
                                        :card="$previewCard" 
                                        :logoBase64="$this->cachedLogo" 
                                        :qrCodeSvg="$qrCodeSvg" 
                                        :noBackground="$this->noBackground" 
                                        side="front" 
                                    />
                                </div>
                                <div class="flip-card-back">
                                    <x-student-card 
                                        :card="$previewCard" 
                                        :logoBase64="$this->cachedLogo" 
                                        :qrCodeSvg="$qrCodeSvg" 
                                        :noBackground="$this->noBackground" 
                                        side="back" 
                                    />
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-zinc-400 p-8 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl w-full max-w-md">
                            <flux:icon.photo class="w-16 h-16 mx-auto mb-2 opacity-50" />
                            <p>Tidak ada kartu santri yang sesuai kriteria.</p>
                            <p class="text-sm mt-1">Pilih sekolah lain atau ubah filter status.</p>
                        </div>
                    @endif
                </div>

                @if($previewCardId)
                    <div class="mt-4 text-xs text-zinc-400">
                        * Klik pada kartu untuk membalik (Lihat sisi {{ $previewSide === 'front' ? 'Belakang' : 'Depan' }}).
                    </div>
                @endif
        </div>
    </div>

    <!-- 3D Flip Styles for interactive card preview -->
    <style>
        .flip-card {
            background-color: transparent;
            width: 856px;
            height: 540px;
            perspective: 2000px;
        }

        .flip-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            text-align: center;
            transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
        }

        .flip-card.is-flipped .flip-card-inner {
            transform: rotateY(180deg);
        }

        .flip-card-front, .flip-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            border-radius: 36px;
        }

        .flip-card-back {
            transform: rotateY(180deg);
        }
    </style>

    <!-- Batch Download Modal -->
    <flux:modal name="batch-download-modal" class="min-w-[30rem]" :dismissible="false">
        <div class="space-y-6" x-data="{
            progress: 0,
            total: @entangle('processingTotal'),
            current: 0,
            ids: @entangle('processingIds'),
            isProcessing: @entangle('isProcessing'),
            downloadUrl: @entangle('zipDownloadUrl'),
            status: 'Menyiapkan...',
            
            async startProcess(ids) {
                this.ids = ids;
                this.total = ids.length;
                this.current = 0;
                this.progress = 0;
                this.status = 'Memproses kartu...';
                this.downloadUrl = null;

                const chunkSize = 5;
                for (let i = 0; i < this.ids.length; i += chunkSize) {
                    const chunk = this.ids.slice(i, i + chunkSize);
                    this.current = Math.min(i + chunkSize, this.total);
                    this.status = `Memproses kartu ${this.current} dari ${this.total} (Depan & Belakang)...`;
                    this.progress = Math.round((this.current / this.total) * 100);
                    
                    await @this.processBatchChunk(chunk);
                }

                this.status = 'Finalisasi ZIP (Cetak Massal)...';
                await @this.finalizeBatch();
                this.status = 'Selesai!';
            }
        }" 
        x-on:start-batch-process.window="startProcess($event.detail.ids)">
            
            <div>
                <flux:heading size="lg">Proses Ekspor Kartu</flux:heading>
                <flux:subheading>Sedang merender bagian depan dan belakang kartu santri...</flux:subheading>
            </div>

            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span x-text="status">Menyiapkan...</span>
                    <span x-text="progress + '%'">0%</span>
                </div>
                <!-- Progress Bar -->
                <div class="w-full bg-zinc-200 rounded-full h-2.5 dark:bg-zinc-700">
                    <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300" :style="`width: ${progress}%`"></div>
                </div>
            </div>

                <div class="flex justify-end gap-2 pt-4">
                 <template x-if="downloadUrl">
                    <flux:button x-on:click="window.location.href = downloadUrl" variant="primary" icon="arrow-down-tray">Unduh Hasil ZIP</flux:button>
                 </template>
                 <template x-if="downloadUrl">
                    <flux:button @click="$dispatch('modal-close')" variant="ghost">Tutup</flux:button>
                 </template>
            </div>
        </div>
    </flux:modal>
</div>
