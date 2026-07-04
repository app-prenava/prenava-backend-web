@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <h1 class="mt-4">Dashboard Dinas Kesehatan</h1>
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
                    <a class="small text-white stretched-link" href="#">Lihat Detail</a>
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
                    <a class="small text-white stretched-link" href="#">Lihat Detail</a>
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
                    <a class="small text-white stretched-link" href="#">Lihat Detail</a>
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
                    <a class="small text-white stretched-link" href="#">Lihat Detail</a>
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
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-primary h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title">{{ $ibu->name ?? 'Nama tidak tersedia' }}</h5>
                                <span class="badge bg-primary">Ibu Hamil</span>
                            </div>
                            <hr>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Usia</div>
                                <div class="col-md-8">{{ $ibu->usia ?? '-' }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Email</div>
                                <div class="col-md-8">{{ $ibu->email ?? '-' }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Alamat</div>
                                <div class="col-md-8">{{ $ibu->alamat ?? '-' }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">No. HP</div>
                                <div class="col-md-8">{{ $ibu->nomor_telepon ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <a href="{{ url('dinkes/saldo/' . $ibu->id) }}" class="btn btn-sm btn-success">Detail</a>
                                <a href="#weqweqe" class="btn btn-sm btn-success">Riwayat Kesehatan</a>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="alert alert-info">
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
                            <a href="#" class="btn btn-sm btn-warning w-100">Detail Lengkap</a>
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
                                <h5 class="card-title">ID: {{ $depresi->id }}</h5>
                                <span class="badge {{ $depresi->hasil_prediksi ? 'bg-danger' : 'bg-success' }}">
                                    {{ $depresi->hasil_prediksi ? 'Depresi' : 'Tidak Depresi' }}
                                </span>
                            </div>
                            <hr>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Umur</div>
                                <div class="col-md-6">{{ $depresi->umur }} tahun</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Merasa Sedih</div>
                                <div class="col-md-6">{{ $depresi->merasa_sedih }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Mudah Tersinggung</div>
                                <div class="col-md-6">{{ $depresi->mudah_tersinggung }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Masalah Tidur</div>
                                <div class="col-md-6">{{ $depresi->masalah_tidur }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Masalah Fokus</div>
                                <div class="col-md-6">{{ $depresi->masalah_fokus }}</div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="#" class="btn btn-sm btn-success w-100">Detail Lengkap</a>
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
        <div class="card-header">
            <i class="fas fa-baby me-1"></i>
            Prediksi Persalinan Terbaru
        </div>
        <div class="card-body">
            <div class="row">
                @forelse($prediksiJanin->take(6) as $janin)
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-danger h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title">ID: {{ $janin->id }}</h5>
                                <span class="badge bg-info">{{ $janin->metode_persalinan }}</span>
                            </div>
                            <hr>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Usia Ibu</div>
                                <div class="col-md-6">{{ $janin->usia_ibu }} tahun</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Tekanan Darah</div>
                                <div class="col-md-6">{{ $janin->tekanan_darah }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Riwayat Persalinan</div>
                                <div class="col-md-6">{{ $janin->riwayat_persalinan }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Posisi Janin</div>
                                <div class="col-md-6">{{ $janin->posisi_janin }}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6 fw-bold">Kondisi Kesehatan</div>
                                <div class="col-md-6">{{ $janin->kondisi_kesehatan_janin ?? 'Normal' }}</div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="#" class="btn btn-sm btn-danger w-100">Detail Lengkap</a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="alert alert-info">
                        Belum ada data prediksi persalinan.
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Riwayat Anemia -->
    <div class="card mb-4">
        <div class="card-header">
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
</div>
@endsection