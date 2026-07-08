<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\DeteksiPenyakit;
use App\Models\PrediksiDepresi;
use App\Models\Prediction;
use App\Models\HealthScanHistory;


class HomeController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('check.role:bidan');
    // }

    public function index()
    {
        // Optimasi: Hanya ambil data yang diperlukan dengan pagination
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->select('user_id', 'name', 'email', 'created_at')
            ->latest()
            ->limit(50)
            ->get();

        // Optimasi: Gunakan cache untuk data yang sering diakses
        $deteksiPenyakit = Cache::remember('deteksi_penyakit_count', 300, function () {
            return DeteksiPenyakit::select('id', 'user_id', 'created_at')
                ->latest()
                ->limit(100)
                ->get();
        });

        $prediksiDepresi = Cache::remember('prediksi_depresi_count', 300, function () {
            return PrediksiDepresi::select('id', 'user_id', 'created_at')
                ->latest()
                ->limit(100)
                ->get();
        });

        $prediksiJanin = Cache::remember('prediksi_janin_count', 300, function () {
            return Prediction::select('id', 'user_id', 'created_at')
                ->latest()
                ->limit(100)
                ->get();
        });

        $riwayatAnemia = Cache::remember('riwayat_anemia_count', 300, function () {
            return HealthScanHistory::where('type', 'anemia')
                ->with('user')
                ->latest()
                ->limit(100)
                ->get();
        });

        return view('bidan.dashboard', [
            'ibuHamil' => $ibuHamil,
            'deteksiPenyakit' => $deteksiPenyakit,
            'prediksiDepresi' => $prediksiDepresi,
            'prediksiJanin' => $prediksiJanin,
            'riwayatAnemia' => $riwayatAnemia
        ]);
    }

       public function index_dinkes()
    {
        // Optimasi: Hanya ambil data yang diperlukan dengan pagination
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->select('user_id', 'name', 'email', 'created_at')
            ->latest()
            ->limit(50)
            ->get();

        // Optimasi: Gunakan cache untuk data yang sering diakses
        $deteksiPenyakit = Cache::remember('deteksi_penyakit_count', 300, function () {
            return DeteksiPenyakit::select('id', 'user_id', 'created_at')
                ->latest()
                ->limit(100)
                ->get();
        });

        $prediksiDepresi = Cache::remember('prediksi_depresi_count', 300, function () {
            return PrediksiDepresi::select('id', 'user_id', 'created_at')
                ->latest()
                ->limit(100)
                ->get();
        });

        $prediksiJanin = Cache::remember('prediksi_janin_count', 300, function () {
            return Prediction::select('id', 'user_id', 'created_at')
                ->latest()
                ->limit(100)
                ->get();
        });

        $riwayatAnemia = Cache::remember('riwayat_anemia_count', 300, function () {
            return HealthScanHistory::where('type', 'anemia')
                ->with('user')
                ->latest()
                ->limit(100)
                ->get();
        });

        return view('dinkes.dashboardd', [
            'ibuHamil' => $ibuHamil,
            'deteksiPenyakit' => $deteksiPenyakit,
            'prediksiDepresi' => $prediksiDepresi,
            'prediksiJanin' => $prediksiJanin,
            'riwayatAnemia' => $riwayatAnemia
        ]);
    }
     public function create()
    {
        return view('bidan.ibu-hamil.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'tanggal_lahir' => 'nullable|date',
            'usia' => 'nullable|integer|min:1|max:100',
            'alamat' => 'nullable|string',
            'nomor_telepon' => 'nullable|string|max:20',
            'pendidikan_terakhir' => 'nullable|string|max:100',
            'pekerjaan' => 'nullable|string|max:100',
            'golongan_darah' => 'nullable|string|max:5',
            'nama_suami' => 'nullable|string|max:255',
            'telepon_suami' => 'nullable|string|max:20',
            'usia_suami' => 'nullable|integer|min:1|max:100',
            'pekerjaan_suami' => 'nullable|string|max:100',
            'usia_kehamilan' => 'nullable|integer|min:0|max:42',
            'saldo_total' => 'nullable|integer|min:0'
        ]);

        $data = $request->all();
        $data['role'] = 'ibu_hamil';
        $data['password'] = bcrypt($request->password);

        User::create($data);

        return redirect()->route('bidan.ibu-hamil.index')
            ->with('success', 'Data ibu hamil berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->findOrFail($id);

        // Ambil data terkait untuk informasi tambahan
        $deteksiPenyakit = DeteksiPenyakit::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $prediksiDepresi = PrediksiDepresi::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $prediksiJanin = Prediction::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $riwayatAnemia = HealthScanHistory::where('user_id', $id)
            ->where('type', 'anemia')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('ibu_hamil.detail', compact(
            'ibuHamil', 
            'deteksiPenyakit', 
            'prediksiDepresi', 
            'prediksiJanin',
            'riwayatAnemia'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->findOrFail($id);

        return view('bidan.ibu-hamil.edit', compact('ibuHamil'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'tanggal_lahir' => 'nullable|date',
            'usia' => 'nullable|integer|min:1|max:100',
            'alamat' => 'nullable|string',
            'nomor_telepon' => 'nullable|string|max:20',
            'pendidikan_terakhir' => 'nullable|string|max:100',
            'pekerjaan' => 'nullable|string|max:100',
            'golongan_darah' => 'nullable|string|max:5',
            'nama_suami' => 'nullable|string|max:255',
            'telepon_suami' => 'nullable|string|max:20',
            'usia_suami' => 'nullable|integer|min:1|max:100',
            'pekerjaan_suami' => 'nullable|string|max:100',
            'usia_kehamilan' => 'nullable|integer|min:0|max:42',
            'saldo_total' => 'nullable|integer|min:0'
        ]);

        $data = $request->except(['password']);
        
        // Update password hanya jika diisi
        if ($request->filled('password')) {
            $request->validate(['password' => 'min:6']);
            $data['password'] = bcrypt($request->password);
        }

        $ibuHamil->update($data);

        return redirect()->route('bidan.ibu-hamil.show', $id)
            ->with('success', 'Data ibu hamil berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->findOrFail($id);

        // Hapus data terkait terlebih dahulu jika diperlukan
        // DeteksiPenyakit::where('user_id', $id)->delete();
        // PrediksiDepresi::where('user_id', $id)->delete();
        // Prediction::where('user_id', $id)->delete();

        $ibuHamil->delete();

        return redirect()->route('bidan.ibu-hamil.index')
            ->with('success', 'Data ibu hamil berhasil dihapus.');
    }

    /**
     * Get ibu hamil data for AJAX requests
     */
    public function getData($id)
    {
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->findOrFail($id);

        return response()->json($ibuHamil);
    }

    /**
     * Search ibu hamil
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%")
                  ->orWhere('nomor_telepon', 'LIKE', "%{$query}%")
                  ->orWhere('alamat', 'LIKE', "%{$query}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return view('bidan.ibu-hamil.index', compact('ibuHamil'));
    }

    /**
     * Get medical history for specific ibu hamil
     */
    public function getMedicalHistory($id)
    {
        $ibuHamil = User::where('role', 'ibu_hamil')
            ->findOrFail($id);

        $deteksiPenyakit = DeteksiPenyakit::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $prediksiDepresi = PrediksiDepresi::where('user_id', $id)
            ->with('epds')
            ->orderBy('created_at', 'desc')
            ->get();

        $prediksiJanin = Prediction::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $riwayatAnemia = HealthScanHistory::where('user_id', $id)
            ->where('type', 'anemia')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('bidan.ibu-hamil.medical-history', compact(
            'ibuHamil',
            'deteksiPenyakit',
            'prediksiDepresi', 
            'prediksiJanin',
            'riwayatAnemia'
        ));
    }

    /**
     * Update pregnancy status
     */
    public function updatePregnancyStatus(Request $request, $id)
    {
        $request->validate([
            'usia_kehamilan' => 'required|integer|min:0|max:42'
        ]);

        $ibuHamil = User::where('role', 'ibu_hamil')
            ->findOrFail($id);

        $ibuHamil->update([
            'usia_kehamilan' => $request->usia_kehamilan
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status kehamilan berhasil diperbarui.'
        ]);
    }
}

