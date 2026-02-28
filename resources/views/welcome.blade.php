@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="text-center mb-5">
                <h1 class="display-4 fw-bold"> Prenava</h1>
                <p class="lead">Platform terpadu untuk pemantauan dan layanan kesehatan ibu hamil</p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-body p-0">
                    <div class="row g-0">
                        <div class="col-md-6">
                            <div class="bg-primary text-white p-5 h-100 d-flex flex-column justify-content-between rounded-start">
                                <div>
                                    <h2 class="fw-bold mb-4">Selamat Datang</h2>
                                    <p>Sistem ini dirancang untuk membantu pemantauan kesehatan ibu hamil dan menyediakan prediksi kesehatan yang akurat.</p>
                                </div>
                                <div>
                                    <p class="mb-0"><i class="fas fa-shield-alt me-2"></i> Aman & Terpercaya</p>
                                    <p class="mb-0"><i class="fas fa-chart-line me-2"></i> Analisis Data Akurat</p>
                                    <p class="mb-0"><i class="fas fa-user-md me-2"></i> Dukungan Bantuan</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-5">
                                <h3 class="fw-bold mb-4">Pilih Jenis Akun</h3>
                                <p class="text-muted mb-4">Silakan pilih jenis akun sesuai dengan peran Anda</p>
                                
                                <div class="d-grid gap-3">
                                    <a href="{{ route('register.bidan.form') }}" class="btn btn-primary btn-lg">
                                        <i class="fas fa-user-nurse me-2"></i> Daftar sebagai Bidan
                                    </a>
                                    
                                    <div class="text-center mt-3">
                                        <p>Sudah memiliki akun? <a href="{{ route('auth.login') }}" class="fw-bold">Masuk di sini</a></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="row mt-5">
        <div class="col-12 text-center mb-4">
            <h2 class="fw-bold">Fitur Utama</h2>
            <p class="text-muted">Berbagai fitur yang dirancang untuk membantu pemantauan kesehatan ibu hamil</p>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-primary bg-gradient text-white rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-heartbeat fa-2x"></i>
                    </div>
                    <h5 class="card-title fw-bold">Deteksi Penyakit</h5>
                    <p class="card-text">Sistem prediksi untuk mendeteksi kemungkinan penyakit seperti diabetes dan hipertensi pada ibu hamil.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-warning bg-gradient text-white rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-brain fa-2x"></i>
                    </div>
                    <h5 class="card-title fw-bold">Prediksi Depresi</h5>
                    <p class="card-text">Analisis dan prediksi kemungkinan depresi pada ibu hamil berdasarkan berbagai faktor risiko.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-danger bg-gradient text-white rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-baby fa-2x"></i>
                    </div>
                    <h5 class="card-title fw-bold">Prediksi Persalinan</h5>
                    <p class="card-text">Prediksi metode persalinan yang optimal berdasarkan kondisi ibu dan janin.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Testimonial/Info Section -->
    <div class="row mt-4 mb-5">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle fa-2x text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold">Dukungan & Bantuan</h5>
                            <p class="mb-0">Jika Anda memerlukan bantuan dalam menggunakan sistem ini, silakan hubungi tim dukungan kami di <a href="mailto:inuriadi73@gmail.com">inuriadi73@gmail.com</a> atau hubungi <strong>0812-2424-9920</strong>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .bg-gradient {
        background: linear-gradient(145deg, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0) 100%);
    }
</style>
@endsection