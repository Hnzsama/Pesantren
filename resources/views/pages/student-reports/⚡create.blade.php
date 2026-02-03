<?php

use App\Models\StudentReport;
use App\Models\Student;
use App\Models\Semester;
use App\Models\AcademicYear;
use App\Models\School;
use App\Services\ImageCompressionService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public $report_id = null;

    // Image Handling
    public $images = []; // Array of TemporaryUploadedFile
    public $tempImage; // Holder for current cropper upload
    public $existingFiles = []; // Array of URLs for existing files

    public $student_id = '';
    public $academic_year_id = '';
    public $semester_id = '';
    public $school_id = ''; // For Admin context or generally just stored

    // For Cropper
    public $croppingImage = null;

    public function mount($id = null)
    {
        $user = auth()->user();

        if ($user->hasRole('admin') && $user->school_id) {
            $this->school_id = $user->school_id;
        }

        if ($id) {
            $report = StudentReport::with('semester.academicYear')->findOrFail($id);
            $this->report_id = $report->id;
            $this->student_id = $report->student_id;
            $this->semester_id = $report->semester_id;
            $this->school_id = $report->school_id;

            // Load existing files
            $this->existingFiles = $report->files; // Accessor returns array of URLs

            // Pre-fill filters
            $this->academic_year_id = $report->semester->academic_year_id;
        }
    }

    #[Computed]
    public function students()
    {
        if (!$this->school_id)
            return [];
        return Student::where('school_id', $this->school_id)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function academicYears()
    {
        if (!$this->school_id)
            return [];
        return AcademicYear::query()
            ->where('school_id', $this->school_id)
            ->orderBy('name', 'desc')
            ->get();
    }

    #[Computed]
    public function semesters()
    {
        if (!$this->academic_year_id)
            return [];
        return Semester::where('academic_year_id', $this->academic_year_id)
            ->where('school_id', $this->school_id)
            ->orderBy('type')
            ->get();
    }

    #[Computed]
    public function schools()
    {
        return School::all();
    }

    public function updatedSchoolId()
    {
        $this->reset('student_id', 'semester_id');
    }

    public function updatedAcademicYearId()
    {
        $this->reset('semester_id');
    }

    public function updatedTempImage()
    {
        try {
            $this->validate([
                'tempImage' => 'image|max:10240',
            ]);

            // Add to images array
            $this->images[] = $this->tempImage;

            // Reset temp
            $this->reset('tempImage');

        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', heading: 'Gagal Upload', text: $e->getMessage());
            $this->reset('tempImage');
        }
    }

    public function removeImage($index)
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function removeExistingFile($index)
    {
        // For existing files, we just remove from the view list. 
        // In a real app, you might want to track deletions to perform on save, 
        // but for simplicity we'll just remove them from the array that gets re-saved (if we re-save everything)
        // actually, to maintain existing files we need to keep track of their original paths not just URLs.
        // But since we are likely just appending new files or replacing, let's simplify:
        // If the user deletes an existing file, we remove it from the array. 
        // When saving, we need to know which existing files remain.
        // However, `existingFiles` are URLs. We need the raw paths to save back to DB.
        // Let's modify mount to get raw paths too? Or just parse from URL?
        // Or simpler: We can't easily edit existing files list without more complex logic (tracking deletions).
        // Let's assume for now removing existing means "don't include in next save" IF we were re-saving the whole list.
        // BUT, `save` logic usually merges. 

        // Better approach for this "update" scenario:
        // We will store the full list of DESIRED paths. 
        // 1. New uploads -> process and get new paths.
        // 2. Existing files -> we need their original relative paths.

        // Let's change strategy: simpler to just remove from UI array, then in save() we need to reconstruct the file_path array.
        // We need the raw paths.
        unset($this->existingFiles[$index]);
        $this->existingFiles = array_values($this->existingFiles);
    }

    public function save(ImageCompressionService $compressor)
    {
        $this->validate([
            'school_id' => 'required|exists:schools,id',
            'academic_year_id' => 'required',
            'semester_id' => [
                'required',
                'exists:semesters,id',
                Rule::unique('student_reports')->where(function ($query) {
                    return $query->where('student_id', $this->student_id)
                        ->where('semester_id', $this->semester_id);
                })->ignore($this->report_id)
            ],
            'student_id' => 'required|exists:students,id',
        ], [
            'semester_id.unique' => 'Rapor untuk siswa dan semester ini sudah ada.'
        ]);

        // Custom validation for at least one image
        if (empty($this->images) && empty($this->existingFiles)) {
            $this->addError('images', 'Minimal harus ada satu file rapor.');
            return;
        }

        $data = [
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'semester_id' => $this->semester_id,
        ];

        if (!$this->report_id) {
            $data['uploaded_by'] = auth()->id();
        }

        // Process New Images
        $newPaths = [];
        foreach ($this->images as $img) {
            $filename = pathinfo($img->hashName(), PATHINFO_FILENAME);
            $path = $compressor->storeAsWebP($img, 'student-reports', $filename, 80);
            $newPaths[] = $path;
        }

        // Process Existing Images
        // existingFiles contains URLs. We need to extract the relative path or filename.
        // The accessor 'files' returns full route URLs. 
        // We need to re-fetch the model and get raw paths, OR we handle this smarter.
        // Let's fetch original paths if editing.
        $finalPaths = [];

        if ($this->report_id) {
            $report = StudentReport::find($this->report_id);
            $originalPaths = json_decode($report->file_path, true) ?? [];
            if (!is_array($originalPaths))
                $originalPaths = [$report->file_path];

            // If we have existingFiles (URLs) remaining in UI, we try to match them back to original paths.
            // This is tricky if filenames are not unique or obfuscated.
            // Assumption: filename in URL matches filename in storage.
            foreach ($this->existingFiles as $url) {
                // extract filename from route url
                // route: student-reports.file / {filename}
                $parts = explode('/', $url);
                $filename = end($parts);

                // find this filename in originalPaths
                foreach ($originalPaths as $origPath) {
                    if (basename($origPath) === $filename) {
                        $finalPaths[] = $origPath;
                        break;
                    }
                }
            }
        }

        // Merge new paths
        $finalPaths = array_merge($finalPaths, $newPaths);

        $data['file_path'] = json_encode($finalPaths);

        if ($this->report_id) {
            $report = StudentReport::findOrFail($this->report_id);
            $report->update($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Rapor berhasil diperbarui.');
        } else {
            StudentReport::create($data);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Rapor berhasil ditambahkan.');
        }

        $this->redirect(route('student-reports.index'), navigate: true);
    }
}; ?>

<div class="w-full" x-data="{ 
    cropping: false, 
    cropper: null, 
    tempImageUrl: null,
    
    initCropper(file) {
        if (!file) return;
        this.cropping = true;
        
        let reader = new FileReader();
        reader.onload = (e) => {
            this.tempImageUrl = e.target.result;
            
            if(this.cropper) {
                this.cropper.destroy();
            }
            
            setTimeout(() => {
                let image = document.getElementById('image-to-crop');
                this.cropper = new Cropper(image, {
                    aspectRatio: NaN, 
                    viewMode: 1,
                    autoCropArea: 1,
                });
            }, 100);
        };
        reader.readAsDataURL(file);
    },
    
    crop() {
        if(!this.cropper) return;
        
        this.cropper.getCroppedCanvas().toBlob((blob) => {
            if (!blob) {
                alert('Crop failed'); 
                return;
            }
            
            let file = new File([blob], 'cropped_' + Date.now() + '.webp', { type: 'image/webp' });
            
            $wire.upload('tempImage', file, () => {
                this.closeCropper();
            }, (error) => {
                console.error(error);
                alert('Upload gagal: ' + JSON.stringify(error));
                this.closeCropper();
            });
            
        }, 'image/webp', 0.8);
    },
    
    closeCropper() {
        this.cropping = false;
        if(this.cropper) {
            this.cropper.destroy();
            this.cropper = null;
        }
        this.tempImageUrl = null;
        document.getElementById('file-input').value = ''; 
    }
}">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ $report_id ? 'Edit Rapor' : 'Tambah Rapor' }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">Isi data rapor santri.</flux:subheading>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="space-y-6 max-w-3xl">

        <!-- School Selection (If Admin/Not Set) -->
        @if(!auth()->user()->school_id)
            <flux:select variant="listbox" wire:model.live="school_id" label="Sekolah" placeholder="Pilih Sekolah">
                @foreach ($this->schools as $school)
                    <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:select variant="listbox" wire:model.live="academic_year_id" label="Tahun Ajaran"
                placeholder="Pilih Tahun">
                @foreach ($this->academicYears as $year)
                    <flux:select.option value="{{ $year->id }}">{{ $year->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select variant="listbox" wire:model="semester_id" label="Semester" placeholder="Pilih Semester"
                :disabled="!$academic_year_id">
                @foreach ($this->semesters as $semester)
                    <flux:select.option value="{{ $semester->id }}">{{ $semester->type }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:select variant="combobox" wire:model="student_id" label="Santri" placeholder="Pilih Santri">
            @foreach ($this->students as $student)
                <flux:select.option value="{{ $student->id }}">{{ $student->nis }} - {{ $student->full_name }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <!-- Image Upload Section -->
        <div>
            <flux:label>File Rapor (Gambar)</flux:label>

            <div class="flex flex-wrap gap-4 mb-2 mt-2">

                {{-- Existing Files --}}
                @foreach($existingFiles as $index => $url)
                    <div class="relative group w-32 h-32 rounded overflow-hidden border border-zinc-200">
                        <img src="{{ $url }}" class="w-full h-full object-contain bg-zinc-50">
                        <div
                            class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-[10px] text-center p-1 truncate">
                            File {{ $index + 1 }}</div>
                        <button wire:click="removeExistingFile({{ $index }})"
                            class="absolute top-0 right-0 bg-red-500 text-white p-1 rounded-bl opacity-0 group-hover:opacity-100 transition">
                            <flux:icon.x-mark class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach

                {{-- New Uploads --}}
                @foreach($images as $index => $img)
                    <div class="relative group w-32 h-32 rounded overflow-hidden border border-zinc-200">
                        <img src="{{ $img->temporaryUrl() }}" class="w-full h-full object-contain bg-zinc-50">
                        <div
                            class="absolute bottom-0 left-0 right-0 bg-green-600/80 text-white text-[10px] text-center p-1 truncate">
                            Baru</div>
                        <button wire:click="removeImage({{ $index }})"
                            class="absolute top-0 right-0 bg-red-500 text-white p-1 rounded-bl opacity-0 group-hover:opacity-100 transition">
                            <flux:icon.x-mark class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach

                {{-- Upload Button --}}
                <label
                    class="w-32 h-32 flex flex-col items-center justify-center border-2 border-dashed border-zinc-300 rounded hover:bg-zinc-50 cursor-pointer transition text-zinc-400 hover:text-zinc-600">
                    <flux:icon.plus class="w-8 h-8" />
                    <span class="text-xs mt-2 font-medium">Tambah File</span>
                    <input type="file" id="file-input" class="hidden" accept="image/*"
                        @change="initCropper($event.target.files[0])">
                </label>
            </div>

            <flux:error name="images" />
            <div class="text-xs text-zinc-400 mt-1">Format: JPG, PNG. Otomatis crop & kompres ke WebP.</div>
        </div>

        <div class="flex justify-end gap-2 mt-8">
            <flux:button href="{{ route('student-reports.index') }}" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button wire:click="save" variant="primary">Simpan</flux:button>
        </div>
    </div>

    <!-- Cropper Modal -->
    <div x-show="cropping" style="display: none;"
        class="fixed inset-0 z-[50] flex items-center justify-center bg-black/80 p-4">
        <div
            class="bg-white dark:bg-zinc-900 rounded-lg shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Crop Image</h3>
                <button @click="closeCropper()" class="text-zinc-500 hover:text-zinc-700">
                    <flux:icon.x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="p-4 flex-1 overflow-auto bg-black flex justify-center">
                <div class="max-w-full max-h-[60vh]">
                    <img id="image-to-crop" :src="tempImageUrl" class="max-w-full block">
                </div>
            </div>

            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700 flex justify-end gap-2">
                <flux:button @click="closeCropper()" variant="ghost">Cancel</flux:button>
                <flux:button @click="crop()" variant="primary">Crop & Use</flux:button>
            </div>
        </div>
    </div>
</div>