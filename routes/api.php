<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeteksiController;
use App\Http\Controllers\AirQualityController;
use App\Http\Controllers\CatatanController;
use App\Http\Controllers\KomunitasController;
use App\Http\Controllers\PrediksiDepresiController;
use App\Http\Controllers\DepressionScanController;
use App\Http\Controllers\RekomendasiMakananController;
use App\Http\Controllers\KickCounterController;
use App\Http\Controllers\SkorEpdsController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\WaterIntakeController;
use App\Http\Controllers\PregnancyCalculatorController;
use App\Http\Controllers\PostpartumArticleController;
use App\Http\Controllers\PostpartumController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\IconsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AddProfileController;
use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AdminUserStatusController;
use App\Http\Controllers\RecomendationSportController;
use App\Http\Controllers\PregnancyController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ThreadsController;
use App\Http\Controllers\ThreadViewsController;
use App\Http\Controllers\ThreadLikesController;
use App\Http\Controllers\RedisDebugController;
use App\Http\Controllers\ParameterizedController;
use App\Http\Controllers\ThreadBookmarksController;
use App\Http\Controllers\TipCategoryController;
use App\Http\Controllers\PregnancyTipController;
use App\Http\Controllers\CatatanIbuController;
use App\Http\Controllers\HistoryLogController;
use App\Http\Controllers\DailyFeatureController;

// Bidan Subscription Controllers
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AdminBidanController;
use App\Http\Controllers\BidanDashboardController;
use App\Http\Controllers\UserBidanController;
use App\Http\Controllers\InsentifController;

use Illuminate\Support\Facades\Route;

Route::get('/debug/redis-likes', [RedisDebugController::class, 'likes']);
Route::prefix('parameterized')->group(function () {
    Route::post('/', [ParameterizedController::class, 'store']);      
    Route::put('/{id}', [ParameterizedController::class, 'update']);  
    Route::delete('/{id}', [ParameterizedController::class, 'destroy']); 
});

Route::prefix('bookmarks')->group(function () {
    Route::post('/create', [ThreadBookmarksController::class, 'store']);        
    Route::get('/all', [ThreadBookmarksController::class, 'index']);          
    Route::delete('/{id}', [ThreadBookmarksController::class, 'destroy']);
});


Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('jwt.auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
    
    Route::put('change-password', [AuthController::class, 'change']);
});

Route::post('/profile/create', [AddProfileController::class, 'create']);
Route::post('/profile/update',  [AddProfileController::class, 'update']);
Route::get('/profile', action: [AddProfileController::class, 'show']);


Route::get('/admin/users', [AdminAccountController::class, 'allUser']);
Route::post('/admin/create/account/bidan',  [AdminAccountController::class, 'createBidan']);
Route::post('/admin/create/account/dinkes', [AdminAccountController::class, 'createDinkes']);
Route::post('/admin/users/{userId}/reset-password', [AdminAccountController::class, 'reset']);
Route::get('/bidan/ibu-hamil', [AdminAccountController::class, 'bidanIbuHamil']);

Route::get('/admin/shop/logs', [ShopController::class, 'getShopLogs']);
Route::get('/shop', [ShopController::class, 'getByUser']);
Route::get('/shop/all', [ShopController::class, 'getAll']);
Route::post('/shop/create',  [ShopController::class, 'create']);
Route::post('/shop/update/{id}',  [ShopController::class, 'update']);
Route::post('/shop/delete/{id}',  [ShopController::class, 'delete']);
Route::get('/shop/{id}/reviews', [ShopController::class, 'getReviews']);
Route::post('/shop/{id}/reviews', [ShopController::class, 'upsertReview']);
Route::delete('/shop/{productId}/reviews/{reviewId}', [ShopController::class, 'deleteReview']);

Route::post('/pregnancies/create', [PregnancyController::class, 'create']);
Route::post('/recomendation/sports/create', [RecomendationSportController::class, 'createRecomendation']);
Route::get('/recomendation/sports/get', [
    RecomendationSportController::class,
    'getSportRecommendation'
]);
Route::get('/recomendation/sports/all', [RecomendationSportController::class, 'getAllSportsPublic']);

Route::prefix('recomendation/sport')->group(function () {
    Route::get('/', [RecomendationSportController::class, 'indexSportMeta']);
    Route::get('/{activity}', [RecomendationSportController::class, 'showSportMeta']);
    Route::post('/', [RecomendationSportController::class, 'storeSportMeta']);
    Route::put('/{activity}', [RecomendationSportController::class, 'updateSportMeta']);
    Route::delete('/{activity}', [RecomendationSportController::class, 'deleteSportMeta']);
});

Route::post('/admin/users/{userId}/deactivate', [AdminUserStatusController::class, 'deactivate']);
Route::post('/admin/users/{userId}/activate',   [AdminUserStatusController::class, 'activate']);

Route::post('/banner/create', [BannerController::class, 'create']);
Route::post('/banner/update/{id}', [BannerController::class, 'update']);
Route::delete('/banner/delete/{id}', [BannerController::class, 'delete']);

Route::get('/banner/show/production', [BannerController::class, 'ShowOnProd']);
Route::get('/banner/show/all', [BannerController::class, 'ShowAll']);

// Tips Categories (Public untuk GET, Admin untuk CRUD)
Route::get('/tips/categories', [TipCategoryController::class, 'index']);
Route::middleware(['auth:api'])->group(function () {
    Route::get('/tips/categories/all', [TipCategoryController::class, 'getAll']); // Admin: Get semua kategori termasuk inactive
    Route::post('/tips/categories', [TipCategoryController::class, 'store']);
    Route::put('/tips/categories/{id}', [TipCategoryController::class, 'update']);
    Route::delete('/tips/categories/{id}', [TipCategoryController::class, 'destroy']);
});

// Pregnancy Tips (Public untuk GET, Admin/Bidan untuk CRUD)
Route::get('/tips', [PregnancyTipController::class, 'index']);
Route::get('/tips/{id}', [PregnancyTipController::class, 'show']);
Route::middleware(['auth:api'])->group(function () {
    Route::post('/tips', [PregnancyTipController::class, 'store']);
    Route::put('/tips/{id}', [PregnancyTipController::class, 'update']);
    Route::delete('/tips/{id}', [PregnancyTipController::class, 'destroy']);
});

Route::prefix('threads')->group(function () {
    Route::get('/main', [ThreadsController::class, 'getAll']);
    Route::post('/create', [ThreadsController::class, 'create']);
    Route::put('/update/{id}', [ThreadsController::class, 'update']);
    Route::get('/detail/{id}', [ThreadViewsController::class, 'detail']);
    Route::post('/like/{id}', [ThreadLikesController::class, 'like']);
    Route::get('/views/cache', [ThreadViewsController::class, 'showCache']);
    Route::post('/replies/{id}', [ThreadsController::class, 'reply']);
    Route::delete('/delete/{id}', [ThreadsController::class, 'delete']);
});


// Semua route terproteksi
Route::group(['middleware' => 'auth:api'], function () {

    //Profil
    Route::get('/user-data', [UserController::class, 'getUserData']);
    Route::post('/isidata', [UserController::class, 'isidata']);
    Route::post('/update-data/{id}', [UserController::class, 'updateData']);
    Route::get('/icons', [IconsController::class, 'index']);
    Route::put('/user/select-icon', [UserController::class, 'updateSelectedIcon']);

    // Air Quality
    Route::get('/kualitasudara', [AirQualityController::class, 'getCityData']);

    // Komunitas
    Route::get('/komunitas', [KomunitasController::class, 'index']);
    Route::get('/komunitas/{id}', [KomunitasController::class, 'indexid']);
    Route::post('/komunitas/add', [KomunitasController::class, 'store']);
    Route::delete('/komunitas/history/deleteAll', [KomunitasController::class, 'deleteAll']);
    Route::delete('/komunitas/history/{id}', [KomunitasController::class, 'deleteById']);
    Route::post('/komunitas/komen/add/{id}', [KomunitasController::class, 'addComment']);
    Route::post('/komunitas/like/add/{id}', [KomunitasController::class, 'addLike']);
    Route::get('/komunitas/komen/{id}', [KomunitasController::class, 'getComments']);

    // Catatan Kunjungan
    Route::get('/catatan/history', [CatatanController::class, 'index']);
    Route::post('/catatan', [CatatanController::class, 'store']);
    Route::delete('/catatan/history/deleteAll', [CatatanController::class, 'deleteAll']);
    Route::delete('/catatan/history/{id}', [CatatanController::class, 'deleteById']);

    // Deteksi Penyakit
    Route::get('/deteksi/history', [DeteksiController::class, 'index']);
    Route::get('/deteksi/latest', [DeteksiController::class, 'indexlatest']);
    Route::post('/deteksi/store', [DeteksiController::class, 'store']);
    Route::delete('/deteksi/history/deleteAll', [DeteksiController::class, 'deleteAll']);
    Route::delete('/deteksi/history/{id}', [DeteksiController::class, 'deleteById']);

    // Postpartum Recovery Tracker
    Route::get('/Recovery', [PostpartumController::class, 'index']);
    Route::get('/Recovery/history', [PostpartumController::class, 'histindex']);

    // Home
    Route::get('/home', [HomeController::class, 'home']);

    // Prediksi Depresi
    Route::get('/prediksidepresi', [PrediksiDepresiController::class, 'index']);
    Route::post('/prediksidepresi/store', [PrediksiDepresiController::class, 'store']);
    Route::get('/prediksidepresi/{id}', [PrediksiDepresiController::class, 'show']);
    Route::delete('/prediksidepresi/delete/{id}', [PrediksiDepresiController::class, 'deletebyID']);

    // EPDS
    Route::post('/epds/store', [SkorEpdsController::class, 'store']);
    Route::get('/epds', [SkorEpdsController::class, 'index']);
    Route::get('/epds/{id}', [SkorEpdsController::class, 'show']);

    // Rekomendasi Makanan
    Route::get('/rekomendasimakanan', [RekomendasiMakananController::class, 'index']);
    Route::get('/rekomendasimakanan/{id}', [RekomendasiMakananController::class, 'show']);

    // Kick Counter
    Route::get('/kick-counter', [KickCounterController::class, 'index']);
    Route::post('/kick-counter/store', [KickCounterController::class, 'store']);

    // Prediksi Metode Persalinan
    Route::get('/predictions', [PredictionController::class, 'index']);
    Route::post('/predictions', [PredictionController::class, 'store']);
    Route::get('/predictions/{id}', [PredictionController::class, 'show']);
    Route::put('/predictions/{id}', [PredictionController::class, 'update']);
    Route::delete('/predictions/{id}', [PredictionController::class, 'destroy']);

    // Water Intake
    Route::post('/water-intake', [WaterIntakeController::class, 'store']);
    Route::get('/water-intake', [WaterIntakeController::class, 'index']);
    Route::get('/water-intake/{id}', [WaterIntakeController::class, 'show']);
    Route::put('/water-intake/{id}', [WaterIntakeController::class, 'update']);
    Route::delete('/water-intake/{id}', [WaterIntakeController::class, 'destroy']);

    // Kalkulator HPL
    Route::post('/pregnancy-calculator/calculate', [PregnancyCalculatorController::class, 'calculate']);
    Route::get('/pregnancy-calculator/my', [PregnancyCalculatorController::class, 'getMyPregnancy']);
    Route::get('pregnancy-calculators', [PregnancyCalculatorController::class, 'index']);
    Route::post('pregnancy-calculators', [PregnancyCalculatorController::class, 'store']);
    Route::get('pregnancy-calculators/{id}', [PregnancyCalculatorController::class, 'show']);
    Route::put('pregnancy-calculators/{id}', [PregnancyCalculatorController::class, 'update']);
    Route::delete('pregnancy-calculators/{id}', [PregnancyCalculatorController::class, 'destroy']);
    Route::post('/pregnancy-calculators/manual', [PregnancyCalculatorController::class, 'storeManual']);


    // PostPartum Article
    Route::get('/postpartum', [PostpartumArticleController::class, 'index']);
    Route::get('/postpartum/{id}', [PostpartumArticleController::class, 'show']);

    // Catatan Ibu (Kunjungan)
    Route::get('/catatan-ibu', [CatatanIbuController::class, 'index']);
    Route::post('/catatan-ibu', [CatatanIbuController::class, 'store']);
    Route::get('/catatan-ibu/{id}', [CatatanIbuController::class, 'show']);
    Route::put('/catatan-ibu/{id}', [CatatanIbuController::class, 'update']);
    Route::delete('/catatan-ibu/{id}', [CatatanIbuController::class, 'destroy']);

    // Protected Data
    Route::get('/protected-data', function () {
        return response()->json(['message' => 'Data protected by JWT']);
    });

    // Shop
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);


    // Depression Face Scan
    Route::post('/depression-scan', [DepressionScanController::class, 'scan']);

    // Anemia Eye Scan
    Route::post('/anemia-scan', [App\Http\Controllers\AnemiaScanController::class, 'scan']);
});


// Welcome
Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to Prenava Backend',
    ], 200);
});

// ============================================
// BIDAN SUBSCRIPTION & APPOINTMENT ROUTES
// ============================================

// Public Routes (No Auth Required)
Route::prefix('public')->group(function () {
    // Subscription Plans
    Route::get('/subscription-plans', [SubscriptionController::class, 'getPlans']);
    
    // Bidan Applications
    Route::post('/bidan-applications', [SubscriptionController::class, 'submitApplication']);
    Route::get('/bidan-applications/status', [SubscriptionController::class, 'checkApplicationStatus']);
});

// Admin Routes (Protected: role=admin)
Route::prefix('admin')->middleware(['auth:api'])->group(function () {
    // Subscription Plans Management
    Route::get('/subscription-plans', [AdminBidanController::class, 'getPlans']);
    Route::post('/subscription-plans', [AdminBidanController::class, 'createPlan']);
    Route::put('/subscription-plans/{id}', [AdminBidanController::class, 'updatePlan']);
    
    // Bidan Applications Management
    Route::get('/bidan-applications', [AdminBidanController::class, 'getApplications']);
    Route::get('/bidan-applications/{id}', [AdminBidanController::class, 'getApplicationDetail']);
    Route::patch('/bidan-applications/{id}/approve', [AdminBidanController::class, 'approveApplication']);
    Route::patch('/bidan-applications/{id}/reject', [AdminBidanController::class, 'rejectApplication']);
    
    // Bidan Account Management
    Route::post('/bidans', [AdminBidanController::class, 'createBidanAccount']);
    Route::get('/bidans', [AdminBidanController::class, 'getBidans']);
    Route::patch('/bidans/{id}/status', [AdminBidanController::class, 'updateBidanStatus']);
    
    // Bidan Location Management
    Route::post('/bidans/{id}/location', [AdminBidanController::class, 'setBidanLocation']);
    Route::get('/bidan-locations', [AdminBidanController::class, 'getBidanLocations']);
    Route::put('/bidan-locations/{id}', [AdminBidanController::class, 'updateBidanLocation']);
    Route::patch('/bidan-locations/{id}/toggle-active', [AdminBidanController::class, 'toggleLocationActive']);

    // History Log
    Route::get('/history-log', [HistoryLogController::class, 'index']);
    Route::get('/history-log/summary', [HistoryLogController::class, 'summary']);
    Route::get('/history-log/user/{userId}', [HistoryLogController::class, 'userLogs']);
});

// Bidan Dashboard Routes (Protected: role=bidan)
Route::prefix('bidan')->middleware(['auth:api'])->group(function () {
    // Profile & Subscription
    Route::get('/me', [BidanDashboardController::class, 'me']);
    Route::put('/me', [BidanDashboardController::class, 'updateProfile']);
    
    // Appointments Management
    Route::get('/appointments', [BidanDashboardController::class, 'getAppointments']);
    Route::get('/appointments/{id}', [BidanDashboardController::class, 'getAppointmentDetail']);
    Route::patch('/appointments/{id}/accept', [BidanDashboardController::class, 'acceptAppointment']);
    Route::patch('/appointments/{id}/reject', [BidanDashboardController::class, 'rejectAppointment']);
    Route::patch('/appointments/{id}/complete', [BidanDashboardController::class, 'completeAppointment']);
    Route::patch('/appointments/{id}/reschedule', [BidanDashboardController::class, 'rescheduleAppointment']);
});

// User (Mobile) Routes for Bidan Discovery & Appointments (Protected: role=ibu_hamil)
Route::prefix('user')->middleware(['auth:api'])->group(function () {
    // Bidan Discovery
    Route::get('/bidans/locations', [UserBidanController::class, 'getLocations']);
    Route::get('/bidans/{id}', [UserBidanController::class, 'getBidanDetail']);
    
    // Consent Info
    Route::get('/consent-info', [UserBidanController::class, 'getConsentInfo']);

    // Saldo & Incentives
    Route::get('/saldo', [InsentifController::class, 'getOwnSaldo']);
    Route::get('/saldo/history', [InsentifController::class, 'getOwnHistory']);
    Route::get('/incentives/summary', [InsentifController::class, 'getIncentiveSummary']);

    // Appointments
    Route::post('/appointments', [UserBidanController::class, 'createAppointment']);
    Route::get('/appointments', [UserBidanController::class, 'getAppointments']);
    Route::get('/appointments/stats', [UserBidanController::class, 'getAppointmentStats']);
    Route::get('/appointments/{id}', [UserBidanController::class, 'getAppointmentDetail']);
    Route::patch('/appointments/{id}/cancel', [UserBidanController::class, 'cancelAppointment']);
    Route::patch('/appointments/{id}/reschedule', [UserBidanController::class, 'rescheduleAppointment']);

    // Consultation Types
    Route::get('/consultation-types', [UserBidanController::class, 'getConsultationTypes']);

    // Daily Features
    Route::put('/category', [DailyFeatureController::class, 'updateCategory']);
    Route::get('/daily-progress', [DailyFeatureController::class, 'getProgress']);
    Route::post('/daily-task/complete', [DailyFeatureController::class, 'completeTask']);
});

