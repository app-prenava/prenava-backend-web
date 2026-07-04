@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <h1 class="mt-4">Dashboard Bidan</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>

    <!-- Summary Cards Row -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <h4>{{ $ibuHamil->count() }}</h4>
                Total Daftar Ibu Hamil Sehati
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="{{ route('bidan.dashboard') }}">Lihat Detail</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <h4>{{ $deteksiPenyakit->count() }}</h4>
                Total Deteksi Penyakit
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="{{ route('deteksi.index') }}">Lihat Detail</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <h4>{{ $prediksiDepresi->count() }}</h4>
                Total Prediksi Depresi
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="{{ route('depresi.index') }}">Lihat Detail</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-danger text-white mb-4">
            <div class="card-body">
                <h4>{{ $prediksiJanin->count() }}</h4>
                Total Prediksi Persalinan
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="{{ route('prediksi.index') }}">Lihat Detail</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-info text-white mb-4">
            <div class="card-body">
                <h4>{{ $riwayatAnemia->count() }}</h4>
                Total Riwayat Anemia
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="#">Lihat Detail</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>
    <!-- Daftar Ibu Hamil -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Daftar Ibu Hamil
        </div>
        <div class="card-body">
            <div class="row">
                @forelse($ibuHamil as $ibu)
                <div class="col-xl-6 col-lg-6 mb-4">
                    <div class="card border-left-primary h-100 shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary rounded-circle me-3 d-flex align-items-center justify-content-center"
                                         style="width: 50px; height: 50px;">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                    <div>
                                        <h5 class="card-title mb-0">{{ $ibu->name ?? 'Nama tidak tersedia' }}</h5>
                                        <small class="text-muted">{{ $ibu->usia ?? '-' }} tahun</small>
                                    </div>
                                </div>
                                <span class="badge bg-primary">Ibu Hamil</span>
                            </div>

                            <hr class="my-3">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small">Nama Lengkap</label>
                                    <p class="mb-0 small">{{ $ibu->name ?? 'Nama tidak tersedia' }}</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small">Tanggal Lahir</label>
                                    <p class="mb-0 small">
                                        @if($ibu->tanggal_lahir)
                                            {{ \Carbon\Carbon::parse($ibu->tanggal_lahir)->format('d F Y') }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small">Usia</label>
                                    <p class="mb-0 small">{{ $ibu->usia ?? '-' }} tahun</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small">Nomor Telepon</label>
                                    <p class="mb-0 small">
                                        @if($ibu->nomor_telepon)
                                            <a href="tel:{{ $ibu->nomor_telepon }}" class="text-decoration-none">
                                                <i class="fas fa-phone me-1"></i>{{ $ibu->nomor_telepon }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold text-muted small">Alamat</label>
                                    <p class="mb-0 small">{{ $ibu->alamat ?? '-' }}</p>
                                </div>
                            </div>

                            @if($ibu->usia_kehamilan)
                                <div class="alert alert-info py-2 mb-3">
                                    <i class="fas fa-baby me-2"></i>
                                    <small><strong>Usia Kehamilan:</strong> {{ $ibu->usia_kehamilan }} minggu</small>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('ibu_hamil.detail', $ibu->user_id) }}" class="btn btn-sm btn-primary">Detail</a>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Belum ada data ibu hamil yang terdaftar.
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Deteksi Penyakit -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-heartbeat me-1"></i>
            Deteksi Penyakit Terbaru
        </div>
        <div class="card-body">
            <div class="row">
                @forelse($deteksiPenyakit->take(6) as $deteksi)
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-warning h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title">{{ $deteksi->nama ?? 'Nama tidak tersedia' }}</h5>
                                <span class="badge bg-warning text-dark">Deteksi Penyakit</span>
                            </div>
                            <hr>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Diabetes</div>
                                <div class="col-md-6">
                                    @if($deteksi->diabetes_prediction === 1)
                                        <span class="badge bg-danger">Positif</span>
                                    @elseif($deteksi->diabetes_prediction === 0)
                                        <span class="badge bg-success">Negatif</span>
                                    @else
                                        <span class="badge bg-secondary">Belum dideteksi</span>
                                    @endif
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Hipertensi</div>
                                <div class="col-md-6">
                                    @if($deteksi->hypertension_prediction === 1)
                                        <span class="badge bg-danger">Positif</span>
                                    @elseif($deteksi->hypertension_prediction === 0)
                                        <span class="badge bg-success">Negatif</span>
                                    @else
                                        <span class="badge bg-secondary">Belum dideteksi</span>
                                    @endif
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Kesehatan Maternal</div>
                                <div class="col-md-6">
                                    <span class="badge {{ $deteksi->maternal_health_prediction ? 'bg-info' : 'bg-secondary' }}">
                                        {{ $deteksi->maternal_health_prediction ?? 'Belum dideteksi' }}
                                    </span>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Tekanan Darah</div>
                                <div class="col-md-6">{{ $deteksi->systolic_bp ?? '-' }}/{{ $deteksi->diastolic_bp ?? '-' }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">BMI</div>
                                <div class="col-md-6">{{ $deteksi->bmi ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('deteksi.show', $deteksi->deteksi_id) }}" class="btn btn-sm btn-warning w-100">Detail Lengkap</a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="alert alert-info">
                        Belum ada data deteksi penyakit.
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Prediksi Depresi -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-brain me-1"></i>
            Prediksi Depresi Terbaru
        </div>
        <div class="card-body">
            <div class="row">
                @forelse($prediksiDepresi->take(6) as $depresi)
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-success h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title">{{ $depresi->user->name ?? 'Tidak diketahui' }}</h5>

                                @if($depresi->epds && $depresi->epds->score)
                                    <!-- Badge untuk EPDS jika ada data -->
                                    <span class="badge
                                        @if($depresi->epds->score >= 14) bg-danger
                                        @elseif($depresi->epds->score >= 12) bg-warning
                                        @else bg-success
                                        @endif">
                                        @if($depresi->epds->score >= 13)
                                            Risiko Tinggi Depresi
                                        @elseif($depresi->epds->score >= 10)
                                            Kemungkinan Depresi
                                        @else
                                            Gejala Ringan
                                        @endif
                                        ({{ $depresi->epds->score }})
                                    </span>
                                @else
                                    <!-- Badge untuk prediksi depresi jika tidak ada EPDS -->
                                    <span class="badge {{ $depresi->hasil_prediksi ? 'bg-danger' : 'bg-success' }}">
                                        {{ $depresi->hasil_prediksi ? 'Bergejala Depresi' : 'Tidak Bergejala Depresi' }}
                                    </span>
                                @endif
                            </div>
                            <hr>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Umur</div>
                                <div class="col-md-6">{{ $depresi->user->usia ?? '-' }} tahun</div>
                            </div>

                             <div class="row mb-2">
                                    <div class="col-md-6 fw-bold">Merasa Sedih</div>
                                    <div class="col-md-6">{{ $depresi->merasa_sedih_text }}</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6 fw-bold">Mudah Tersinggung</div>
                                    <div class="col-md-6">{{ $depresi->mudah_tersinggung_text}}</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6 fw-bold">Masalah Tidur</div>
                                    <div class="col-md-6">{{ $depresi->masalah_tidur_text}}</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6 fw-bold">Masalah Fokus</div>
                                    <div class="col-md-6">{{ $depresi->masalah_fokus_text}}</div>
                                </div>
                            @if($depresi->epds && $depresi->epds->score)
                                <!-- Tampilkan info EPDS jika ada -->
                                <div class="row mb-2">
                                    <div class="col-md-6 fw-bold">Skor EPDS</div>
                                    <div class="col-md-6">{{ $depresi->epds->score }} / 30</div>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('depresi.show', $depresi->id) }}" class="btn btn-sm btn-success w-100">Detail Lengkap</a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="alert alert-info">
                        Belum ada data prediksi depresi.
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>

<!-- Prediksi Janin / Persalinan -->
<div class="card mb-4">
    <div class="card-header bg-white text-dark fw-semibold">
        <i class="fas fa-baby me-1 text-danger"></i>
        Prediksi Persalinan Terbaru
    </div>
    <div class="card-body">
        <div class="row">
            @forelse($prediksiJanin->take(6) as $janin)
                @php
                    $method = strtolower($janin->metode_persalinan);
                    $isCaesar = $method === 'caesar';
                    $textClass = $isCaesar ? 'text-danger' : 'text-primary';
                    $borderClass = $isCaesar ? 'border-danger' : 'border-primary';
                    $buttonClass = $isCaesar ? 'btn-outline-danger' : 'btn-outline-primary';
                @endphp

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card h-100 border border-2 {{ $borderClass }}">
                        <div class="card-body">
                            {{-- Header --}}
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0 fw-semibold">Prediksi #{{ $janin->id }}</h5>
                                <span class="fw-bold small {{ $textClass }}">
                                    {{ ucfirst($janin->metode_persalinan) }}
                                </span>
                            </div>

                            {{-- Informasi Klinis --}}
                            <div class="mb-2 d-flex justify-content-between">
                                <span><i class="fas fa-birthday-cake me-1 text-muted"></i> Usia Ibu</span>
                                <span>{{ $janin->usia_ibu }} tahun</span>
                            </div>
                            <div class="mb-2 d-flex justify-content-between">
                                <span><i class="fas fa-tachometer-alt me-1 text-muted"></i> Tekanan Darah</span>
                                <span>{{ ucfirst($janin->tekanan_darah) }}</span>
                            </div>
                            <div class="mb-2 d-flex justify-content-between">
                                <span><i class="fas fa-history me-1 text-muted"></i> Riwayat Persalinan</span>
                                <span>{{ ucfirst($janin->riwayat_persalinan) }}</span>
                            </div>
                            <div class="mb-2 d-flex justify-content-between">
                                <span><i class="fas fa-baby-carriage me-1 text-muted"></i> Posisi Janin</span>
                                <span>{{ ucfirst($janin->posisi_janin) }}</span>
                            </div>
                            <div class="mb-2 d-flex justify-content-between">
                                <span><i class="fas fa-heartbeat me-1 text-muted"></i> Kondisi Janin</span>
                                <span>{{ ucfirst($janin->kondisi_kesehatan_janin) ?? 'Normal' }}</span>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="{{ route('prediksi.show', $janin->id) }}"
                               class="btn btn-sm {{ $buttonClass }} w-100">
                                <i class="fas fa-eye me-1"></i> Lihat Detail
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info">Belum ada data prediksi persalinan.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Riwayat Anemia -->
<div class="card mb-4">
    <div class="card-header bg-white text-dark fw-semibold">
        <i class="fas fa-tint me-1 text-info"></i>
        Riwayat Anemia Terbaru
    </div>
    <div class="card-body">
        <div class="row">
            @forelse($riwayatAnemia->take(6) as $anemia)
                @php
                    // Pastikan result sudah berupa array karena cast 'array' di model
                    $isAnemia = isset($anemia->result['prediction']) && strtolower($anemia->result['prediction']) === 'anemia';
                    $textClass = $isAnemia ? 'text-danger' : 'text-success';
                    $borderClass = $isAnemia ? 'border-danger' : 'border-success';
                @endphp

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card h-100 border border-2 {{ $borderClass }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0 fw-semibold">{{ $anemia->user->name ?? 'Tidak diketahui' }}</h5>
                                <span class="fw-bold small {{ $textClass }}">
                                    {{ isset($anemia->result['prediction']) ? ucfirst($anemia->result['prediction']) : 'Tidak Diketahui' }}
                                </span>
                            </div>

                            <hr>
                            
                            <div class="mb-2 d-flex justify-content-between">
                                <span><i class="fas fa-birthday-cake me-1 text-muted"></i> Usia Ibu</span>
                                <span>{{ $anemia->user->usia ?? '-' }} tahun</span>
                            </div>
                            
                            <div class="mb-2 d-flex justify-content-between">
                                <span><i class="fas fa-clock me-1 text-muted"></i> Waktu Scan</span>
                                <span>{{ $anemia->created_at->format('d M Y, H:i') }}</span>
                            </div>

                            @if(isset($anemia->result['confidence']))
                            <div class="mb-2 d-flex justify-content-between">
                                <span><i class="fas fa-percent me-1 text-muted"></i> Confidence</span>
                                <span>{{ number_format((float) $anemia->result['confidence'], 2) }}%</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info">Belum ada data riwayat anemia.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>

@push('styles')
<style>
    .card {
        border: none;
        border-radius: 0.75rem;
        transition: transform 0.2s ease-in-out;
    }

    .card:hover {
        transform: translateY(-2px);
    }

    .card-header {
        border-radius: 0.75rem 0.75rem 0 0 !important;
        border-bottom: 2px solid rgba(0,0,0,0.1);
    }

    .form-label {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.5em 0.75em;
    }

    .alert {
        border-radius: 0.5rem;
        border: none;
    }

    .border-left-primary {
        border-left: 4px solid #007bff !important;
    }

    .small {
        font-size: 0.875rem;
    }

    .shadow {
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
    }
</style>
@endpush
@endsection
