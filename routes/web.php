<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InsentifController;
use App\Http\Controllers\DeteksiDesktopController;
use App\Http\Controllers\PrediksiDepresiDesktopController;
use App\Http\Controllers\IconsController;
use App\Http\Controllers\PredictionDesktopController;
use Illuminate\Support\Facades\Auth;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-tester', function () {
    return view('api-tester');
})->name('api-tester');

// Disable password reset routes karena ForgotPasswordController tidak ada
Auth::routes(['reset' => false, 'verify' => false]);

Route::get('/register/bidan', [RegisterController::class, 'showBidanRegistrationForm'])->name('register.bidan.form');
Route::post('/register/bidan', [RegisterController::class, 'registerBidan'])->name('register.bidan');

Route::get('/register/dinkes', [RegisterController::class, 'showDinkesRegistrationForm'])->name('register.dinkes.form');
Route::post('/register/dinkes', [RegisterController::class, 'registerDinkes'])->name('register.dinkes');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('auth.login');
Route::post('/login', [LoginController::class, 'login'])->name('login');

// Protected Routes for Bidan - Using the check.role middleware
Route::prefix('bidan')->group(function () {
    Route::get('/dashboard', function () {
        return view('bidan.dashboard');
    })->name('bidan.dashboard');

    Route::get('/deteksi', [DeteksiDesktopController::class, 'index'])->name('deteksi.index');
    Route::get('/deteksi/create', [DeteksiDesktopController::class, 'create'])->name('deteksi.create');
    Route::post('/deteksi', [DeteksiDesktopController::class, 'store'])->name('deteksi.store');
    Route::get('/deteksi/{id}', [DeteksiDesktopController::class, 'show'])->name('deteksi.show');
    Route::get('/deteksi/latest', [DeteksiDesktopController::class, 'indexlatest'])->name('deteksi.latest');
    Route::get('/deteksi/delete-all', [DeteksiDesktopController::class, 'deleteAll'])->name('deteksi.deleteAll');
    Route::delete('/deteksi/{id}', [DeteksiDesktopController::class, 'destroy'])->name('deteksi.destroy');

    Route::get('/detail/{id}', [HomeController::class, 'show'])->name('ibu_hamil.detail');

    Route::get('/depresi', [PrediksiDepresiDesktopController::class, 'index'])->name('depresi.index');
    Route::get('/depresi/show/{id}', [PrediksiDepresiDesktopController::class, 'show'])->name('depresi.show');
    Route::delete('/depresi/{id}', [PrediksiDepresiDesktopController::class, 'destroy'])->name('depresi.destroy');

    Route::get('/prediksi', [PredictionDesktopController::class, 'index'])->name('prediksi.index');
    Route::get('/prediksi/form', [PredictionDesktopController::class, 'create'])->name('prediksi.form');
    Route::post('/prediksi', [PredictionDesktopController::class, 'store'])->name('prediksi.store');
    Route::get('/prediksi/{id}', [PredictionDesktopController::class, 'show'])->name('prediksi.show');
    Route::delete('/prediksi/{id}', [PredictionDesktopController::class, 'destroy'])->name('prediksi.delete');
    Route::delete('/prediksi/delete-all', [PredictionDesktopController::class, 'deleteAll'])->name('prediksi.deleteAll');
    Route::get('/prediksi/{id}/print', [PredictionDesktopController::class, 'print'])->name('prediksi.print');

    // Route::get('/dashboard', [HomeController::class, 'index'])->name('bidan.dashboard');
    // Add more bidan routes here
});


Route::get('bidan/dashboard', [HomeController::class, 'index'])->name('bidan.dashboard');


// Protected Routes for Dinkes - Using the check.role middleware
Route::prefix('dinkes')->group(function () {
    // Route::get('/dashboard', function () {
    //     return view('dinkes.dashboard');
    // })->name('dinkes.dashboard');
    Route::get('dashboard', [HomeController::class, 'index_dinkes'])->name('dinkes.dashboardd');

    // Insentif
    Route::get('saldo/{userId}', [InsentifController::class, 'Saldo'])->name('dinkes.dinkessaldo');
    Route::get('saldo/{userId}/riwayat', [InsentifController::class, 'getRiwayatSaldo'])->name('dinkes.riwayat.saldo');
    Route::get('saldo/{userId}/laporan', [InsentifController::class, 'getLaporanSaldo'])->name('dinkes.laporan.saldo');
    Route::get('saldo/{id}/tambah', [InsentifController::class, 'showTambahSaldoForm'])->name('dinkes.form.tambah.saldo');
    Route::post('saldo/{userId}', [InsentifController::class, 'tambahSaldo'])->name('dinkes.tambah.saldo');

    // Add more dinkes routes here
});

    // INSETIF
    // Route::get('saldo/{userId}', [InsentifController::class, 'Saldo']);
    // Route::post('/tambah', [SaldoController::class, 'tambahSaldo'])->middleware('role:dinkes');
    Route::post('saldo/tambah', [InsentifController::class, 'tambahSaldo']);
        // Ambil riwayat saldo ibu hamil tertentu
    Route::get('/riwayat/{userId}', [InsentifController::class, 'getRiwayatSaldo']);
        // Ambil daftar ibu hamil beserta saldo mereka (hanya admin)
    Route::get('/daftar-ibu-hamil', [InsentifController::class, 'getDaftarIbuHamil'])->middleware('role:admin');
        // Batalkan transaksi (hanya admin level 2 ke atas)
    Route::post('/batalkan/{transaksiId}', [InsentifController::class, 'batalkanTransaksi'])->middleware('role:admin');
        // Laporan transaksi saldo (hanya admin)
    Route::get('/laporan', [InsentifController::class, 'getLaporanTransaksi'])->middleware('role:admin');

// Home route (redirect based on role)
Route::get('/home', function() {
    if (Auth::check()) {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role === 'bidan') {
            return redirect()->route('bidan.dashboard');
        } elseif ($user->role === 'dinkes') {
            return redirect()->route('dinkes.dashboard');
        }
    }

    return redirect()->route('login');
})->name('home');
