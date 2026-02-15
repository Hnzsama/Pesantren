@props(['card', 'logoBase64' => null, 'qrCodeSvg' => null, 'noBackground' => false, 'side' => 'front'])

<div class="card-wrapper {{ $noBackground ? 'no-background' : '' }} side-{{ $side }}">
    <div class="card-container">
        @if(!$noBackground)
            <div class="bg-shape shape-1"></div>
            <div class="bg-shape shape-2"></div>
        @endif
        
        <div class="card-content">
            @if($side === 'front')
                {{-- Front Design --}}
                <div class="header">
                    <div class="brand-left">
                        <div class="brand-logo">
                            <x-app-logo-icon style="width: 70%; height: 70%;" />
                        </div>
                        <div class="brand-text">
                            <span class="subtitle {{ $noBackground ? 'text-zinc-500' : '' }}">Pondok Pesantren</span>
                            <span class="title {{ $noBackground ? 'text-zinc-800' : '' }}">SANDIGI</span>
                        </div>
                    </div>
                    <div class="school-info">
                        <div class="school-text">
                            <span class="subtitle {{ $noBackground ? 'text-zinc-500' : '' }}">Pondok Pesantren</span>
                            <span class="name {{ $noBackground ? 'text-zinc-800' : '' }}">{{ $card->student->school->name }}</span>
                        </div>
                        <div class="brand-logo" style="background: transparent;">
                            @if($logoBase64)
                                <img src="{{ $logoBase64 }}" alt="School Logo">
                            @elseif(!empty($card->student->school->logo_path))
                                @php
                                    $logoPath = $card->student->school->logo_path;
                                    $fullPath = storage_path('app/public/' . $logoPath);
                                    $base64 = '';
                                    if (file_exists($fullPath)) {
                                        $type = pathinfo($fullPath, PATHINFO_EXTENSION);
                                        $data = file_get_contents($fullPath);
                                        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                                    }
                                @endphp
                                @if($base64)
                                    <img src="{{ $base64 }}" alt="School Logo">
                                @else
                                    <img src="{{ asset('storage/' . $logoPath) }}" alt="School Logo" onerror="this.style.display='none'">
                                @endif
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="{{ $noBackground ? 'rgba(0,0,0,0.4)' : 'rgba(255,255,255,0.8)' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                                </svg>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-title {{ $noBackground ? 'text-zinc-400' : '' }}">KARTU SANTRI</div>
                <div class="card-number {{ $noBackground ? 'text-zinc-800' : '' }}">{{ implode(' ', str_split($card->card_number, 4)) }}</div>
                <div class="footer">
                    <div class="qr-placeholder {{ $noBackground ? 'border border-zinc-200' : '' }}">
                        @if($qrCodeSvg)
                            {!! $qrCodeSvg !!}
                        @else
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ $card->card_number }}&format=svg" alt="QR Code">
                        @endif
                    </div>
                    <div class="student-info">
                        <div class="student-name {{ $noBackground ? 'text-zinc-900' : '' }}">{{ $card->student->full_name }}</div>
                        <div class="student-nis {{ $noBackground ? 'text-zinc-500' : '' }}">NIS {{ $card->student->nis }}</div>
                    </div>
                    <div class="valid-thru">
                        <span class="label-valid {{ $noBackground ? 'text-zinc-500' : '' }}">Valid Thru</span>
                        <span class="date-valid {{ $noBackground ? 'text-zinc-800' : '' }}">
                            {{ $card->expires_at ? $card->expires_at->format('m/y') : now()->addYears(3)->format('m/y') }}
                        </span>
                        <div class="flutter-logo-icon">
                             <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="{{ $noBackground ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.3)' }}"/>
                                <path d="M2 17L12 22L22 17" stroke="{{ $noBackground ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.3)' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="{{ $noBackground ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.3)' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                </div>
            @else
                {{-- Back Design --}}
                <div class="back-layout">
                    <div class="back-header">
                        <div class="back-title {{ $noBackground ? 'text-zinc-800' : '' }}">TATA TERTIB & KETENTUAN</div>
                        <div class="back-divider"></div>
                    </div>
                    
                    <div class="back-body">
                        <ul class="rules-list {{ $noBackground ? 'text-zinc-600' : '' }}">
                            <li>Kartu ini adalah identitas resmi santri Pondok Pesantren Sandigi.</li>
                            <li>Penyalahgunaan kartu ini oleh pihak tidak berwenang dapat dikenakan sanksi.</li>
                            <li>Kartu ini wajib dibawa dalam setiap kegiatan resmi pondok pesantren.</li>
                            <li>Jika kartu ini ditemukan, mohon kembalikan ke kantor administrasi pesantren.</li>
                            <li>Informasi lebih lanjut dapat menghubungi nomor layanan pesantren.</li>
                        </ul>
                    </div>

                    <div class="back-footer">
                        <div class="contact-grid {{ $noBackground ? 'text-zinc-500' : '' }}">
                            <div class="contact-item">
                                <span class="contact-label">Telepon:</span>
                                <span class="contact-value">{{ $card->student->school->phone ?? '-' }}</span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">Website:</span>
                                <span class="contact-value">www.sandigi.id</span>
                            </div>
                        </div>
                        <div class="property-of {{ $noBackground ? 'text-zinc-400' : '' }}">
                            PROPERTI RESMI {{ strtoupper($card->student->school->name) }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    
    .card-wrapper * { box-sizing: border-box; margin: 0; padding: 0; }
    .card-container {
        width: 856px; height: 540px; position: relative;
        background: linear-gradient(135deg, #007474 0%, #005252 100%);
        border-radius: 36px; overflow: hidden; color: white; padding: 30px 40px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        font-family: 'Poppins', sans-serif;
    }
    .no-background .card-container {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        color: #333;
    }
    .no-background .card-content {
        color: #333;
    }
    .bg-shape { position: absolute; background-color: rgba(255, 255, 255, 0.05); border-radius: 50%; z-index: 0; }
    .shape-1 { width: 856px; height: 856px; top: -374px; left: 342px; background: rgba(255, 255, 255, 0.1); }
    .shape-2 { width: 770px; height: 770px; top: 101px; left: -300px; background: rgba(255, 255, 255, 0.08); }
    .card-content { position: relative; z-index: 10; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
    
    /* Header Styles */
    .header { display: flex; justify-content: space-between; align-items: center; }
    .brand-left { display: flex; align-items: center; gap: 15px; }
    .brand-logo { 
        width: 70px; height: 70px; 
        display: flex; align-items: center; justify-content: center; 
        background: rgba(255, 255, 255, 0.2); border-radius: 50%; 
        font-size: 10px; 
        overflow: hidden; 
    }
    .no-background .brand-logo { background: rgba(0, 0, 0, 0.05); }
    .brand-logo img { width: 100%; height: 100%; object-fit: contain; }
    .brand-text { display: flex; flex-direction: column; }
    .brand-text .subtitle { font-size: 14px; color: rgba(255, 255, 255, 0.7); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
    .brand-text .title { font-size: 28px; font-weight: 700; letter-spacing: 0.5px; color: white; }
    
    .school-info { display: flex; align-items: center; gap: 15px; text-align: right; }
    .school-text { display: flex; flex-direction: column; align-items: flex-end; }
    .school-text .subtitle { font-size: 14px; color: rgba(255, 255, 255, 0.7); font-weight: 600; text-transform: uppercase; }
    .school-text .name { font-size: 24px; font-weight: 700; color: white; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    /* Body Styles */
    .card-title { text-align: center; font-size: 28px; font-weight: 500; letter-spacing: 8px; color: rgba(255, 255, 255, 0.6); margin-top: 20px; text-transform: uppercase; }
    .card-number { font-family: 'Courier Prime', monospace; font-size: 42px; font-weight: 700; text-align: center; letter-spacing: 4px; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2); margin-bottom: 20px; }
    .no-background .card-number { text-shadow: none; }
    
    /* Footer Styles */
    .footer { display: flex; align-items: flex-end; justify-content: space-between; }
    .qr-placeholder { width: 120px; height: 120px; background-color: white; padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
    .qr-placeholder img, .qr-placeholder svg { width: 100%; height: 100%; }
    .student-info { flex: 1; margin-left: 20px; display: flex; flex-direction: column; justify-content: flex-end; }
    .student-name { font-size: 26px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
    .student-nis { font-size: 18px; color: rgba(255, 255, 255, 0.7); font-weight: 500; }
    .valid-thru { text-align: right; display: flex; flex-direction: column; align-items: flex-end; margin-bottom: 30px; }
    .label-valid { font-size: 12px; color: rgba(255, 255, 255, 0.7); margin-bottom: 2px; }
    .date-valid { font-size: 22px; font-weight: 700; }
    .flutter-logo-icon { color: rgba(255, 255, 255, 0.2); font-size: 40px; margin-top: 5px; }

    /* Back Side Specific Styles */
    .back-layout { height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
    .back-title { font-size: 24px; font-weight: 700; text-align: center; letter-spacing: 2px; }
    .back-divider { height: 4px; width: 100px; background: #00cccc; margin: 15px auto; border-radius: 2px; }
    .rules-list { list-style: none; space-y: 12px; }
    .rules-list li { font-size: 16px; margin-bottom: 12px; position: relative; padding-left: 25px; line-height: 1.4; color: rgba(255,255,255,0.9); }
    .rules-list li::before { content: "•"; position: absolute; left: 0; color: #00cccc; font-weight: bold; }
    .no-background .rules-list li { color: #555; }

    .back-footer { border-top: 1px solid rgba(255,255,255,0.1); pt: 20px; }
    .no-background .back-footer { border-top-color: #eee; }
    .contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
    .contact-item { display: flex; flex-direction: column; }
    .contact-label { font-size: 12px; font-weight: 600; text-transform: uppercase; color: rgba(255,255,255,0.6); }
    .contact-value { font-size: 16px; font-weight: 500; }
    .property-of { font-size: 12px; font-weight: 600; text-align: center; color: rgba(255,255,255,0.4); letter-spacing: 2px; }

    /* Text colors for no background */
    .no-background .text-zinc-500 { color: #71717a; }
    .no-background .text-zinc-800 { color: #27272a; }
    .no-background .text-zinc-900 { color: #18181b; }
    .no-background .text-zinc-400 { color: #a1a1aa; }
    .no-background .text-zinc-600 { color: #52525b; }
</style>

