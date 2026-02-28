<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Saldo;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\AuthToken;
use DateTime;

class InsentifController extends Controller
{
    /**
     * Ambil daftar ibu hamil beserta saldo mereka
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function getDaftarIbuHamil(Request $request)
    {
        try {
            $query = User::where('role', 'ibu_hamil');
            
            // Filter berdasarkan nama (jika ada)
            if ($request->has('nama') && $request->nama) {
                $query->where('nama', 'like', '%' . $request->nama . '%');
            }
            
            // Filter berdasarkan kelurahan/desa (jika ada)
            if ($request->has('kelurahan') && $request->kelurahan) {
                $query->where('kelurahan', 'like', '%' . $request->kelurahan . '%');
            }

            $ibuHamil = $query->select('id', 'nama', 'nik', 'tanggal_lahir', 'alamat', 'kelurahan', 'kecamatan', 'saldo_total')
                ->orderBy('nama', 'asc')
                ->paginate(15);

            return view('dinkes.daftar_ibu_hamil', [
                'ibuHamil' => $ibuHamil
            ]);

        } catch (\Exception $e) {
            return back()->withErrors([
                'daftar' => 'Terjadi kesalahan saat mengambil daftar ibu hamil: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Tampilkan halaman pengelolaan saldo untuk ibu hamil tertentu
     *
     * @param  int  $userId
     * @return \Illuminate\View\View
     */
    public function Saldo($userId)
    {
        // Validasi manual userId karena tidak lewat $request
        if (!is_numeric($userId) || !User::where('id', $userId)->exists()) {
            abort(404, 'Pengguna tidak ditemukan');
        }

        // Cari user dengan role ibu_hamil dan id sesuai parameter
        $user = User::where('role', 'ibu_hamil')->find($userId);

        if (!$user) {
            abort(404, 'Pengguna tidak ditemukan atau bukan ibu hamil');
        }

        // Tampilkan view dengan data user
        return view('dinkes.dinkessaldo', [
            'ibuHamil' => $user,
        ]);
    }

    /**
     * Ambil laporan saldo untuk ibu hamil tertentu
     *
     * @param  int  $userId
     * @return \Illuminate\View\View
     */
    public function getLaporanSaldo($userId)
    {
        // Validasi manual userId karena tidak lewat $request
        if (!is_numeric($userId) || !User::where('id', $userId)->exists()) {
            abort(404, 'Pengguna tidak ditemukan');
        }

        // Cari user dengan role ibu_hamil dan id sesuai parameter
        $user = User::where('role', 'ibu_hamil')->find($userId);

        if (!$user) {
            abort(404, 'Pengguna tidak ditemukan atau bukan ibu hamil');
        }

        // Ambil riwayat transaksi untuk laporan
        $transaksi = Saldo::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Hitung total transaksi credit dan debit
        $totalCredit = $transaksi->where('type', 'credit')->sum('amount');
        $totalDebit = $transaksi->where('type', 'debit')->sum('amount');

        // Tampilkan view dengan data user dan transaksi
        return view('dinkes.laporan_saldo', [
            'user' => $user,
            'transaksi' => $transaksi,
            'totalCredit' => $totalCredit,
            'totalDebit' => $totalDebit
        ]);
    }

    public function showTambahSaldoForm($userId)
    {
        $ibuHamil = User::findOrFail($userId);
        return view('dinkes.tambah-saldo', compact('ibuHamil'));
    }
    /**
     * Tambah saldo untuk ibu hamil
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
 public function tambahSaldo(Request $request, $userId)
    {
        // Validasi input
        $validated = $request->validate([
            'jumlah' => 'required|numeric|min:1',
            'keterangan' => 'nullable|string|max:255',
        ]);

        // Cek apakah user adalah ibu hamil
        $user = User::where('id', $userId)
                    ->where('role', 'ibu_hamil')
                    ->firstOrFail();

        DB::beginTransaction();
        try {
            // Buat transaksi saldo baru
            $saldo = new Saldo();
            $saldo->user_id = $userId;
            $saldo->amount = $request->jumlah;
            $saldo->type = 'credit'; // kredit (penambahan)
            $saldo->keterangan = $request->keterangan ?? 'Penambahan saldo dari Dinas Kesehatan';
            $saldo->admin_id = Auth::id(); // ID admin yang memberikan saldo
            $saldo->save();

            // Update total saldo pada user
            $user->saldo_total = $user->saldo_total + $request->jumlah;
            $user->save();

            DB::commit();

            return redirect()->route('dinkes.dinkessaldo', $userId)
                ->with('success', 'Saldo berhasil ditambahkan sebesar Rp ' . number_format($request->jumlah, 0, ',', '.'));

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors([
                'saldo' => 'Terjadi kesalahan saat menambah saldo: ' . $e->getMessage()
            ]);
        } 
    }
    

    /**
     * Ambil riwayat transaksi saldo untuk ibu hamil tertentu
     *
     * @param  int  $userId
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function getRiwayatSaldo($userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Cek apakah user adalah ibu hamil
            if ($user->role != 'ibu_hamil') {
                return back()->withErrors([
                    'role' => 'User bukan merupakan ibu hamil'
                ]);
            }

            // Ambil semua riwayat transaksi
            $riwayat = Saldo::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return view('dinkes.riwayat_saldo', [
                'user' => $user,
                'riwayat' => $riwayat
            ]);

        } catch (\Exception $e) {
            return back()->withErrors([
                'saldo' => 'Terjadi kesalahan saat mengambil riwayat saldo: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Batalkan transaksi saldo (hanya admin dengan level tertentu)
     *
     * @param  int  $transaksiId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function batalkanTransaksi($transaksiId)
    {
        // Cek hak akses (hanya admin dengan level tertentu)
        if (Auth::user()->role !== 'admin' || Auth::user()->admin_level < 2) {
            return back()->withErrors([
                'akses' => 'Tidak memiliki hak akses untuk membatalkan transaksi'
            ]);
        }

        DB::beginTransaction();
        try {
            // Ambil data transaksi
            $transaksi = Saldo::findOrFail($transaksiId);
            
            // Pastikan transaksi belum dibatalkan
            if ($transaksi->status === 'cancelled') {
                return back()->withErrors([
                    'status' => 'Transaksi sudah dibatalkan sebelumnya'
                ]);
            }

            // Cari user terkait
            $user = User::findOrFail($transaksi->user_id);
            
            // Update saldo user
            if ($transaksi->type === 'credit') {
                $user->saldo_total -= $transaksi->amount;
            } else {
                $user->saldo_total += $transaksi->amount;
            }
            $user->save();
            
            // Update status transaksi
            $transaksi->status = 'cancelled';
            $transaksi->cancelled_by = Auth::id();
            $transaksi->cancelled_at = now();
            $transaksi->save();

            DB::commit();
            
            return redirect()->back()->with('success', 'Transaksi berhasil dibatalkan');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'batal' => 'Terjadi kesalahan saat membatalkan transaksi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Laporan transaksi saldo (untuk admin)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function getLaporanTransaksi(Request $request)
    {
        try {
            $query = Saldo::with('user:id,nama,nik');
            
            // Filter berdasarkan rentang tanggal
            if ($request->has('tanggal_mulai') && $request->tanggal_mulai) {
                $query->whereDate('created_at', '>=', $request->tanggal_mulai);
            }
            
            if ($request->has('tanggal_akhir') && $request->tanggal_akhir) {
                $query->whereDate('created_at', '<=', $request->tanggal_akhir);
            }
            
            // Filter berdasarkan tipe transaksi
            if ($request->has('tipe') && $request->tipe) {
                $query->where('type', $request->tipe);
            }
            
            // Filter berdasarkan status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            $transaksi = $query->orderBy('created_at', 'desc')
                ->paginate(20);
                
            // Hitung total
            $total = $query->sum('amount');

            return view('dinkes.laporan_transaksi', [
                'transaksi' => $transaksi,
                'total_nominal' => $total,
                'filters' => $request->all()
            ]);

        } catch (\Exception $e) {
            return back()->withErrors([
                'laporan' => 'Terjadi kesalahan saat mengambil laporan: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get own saldo balance (for ibu_hamil via API)
     */
    public function getOwnSaldo(Request $request)
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $user = User::where('user_id', $uid)->first();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User tidak ditemukan'], 404);
        }

        $totalCredit = Saldo::where('user_id', $uid)
            ->where('type', 'credit')
            ->where('status', 'active')
            ->sum('amount');

        $totalDebit = Saldo::where('user_id', $uid)
            ->where('type', 'debit')
            ->where('status', 'active')
            ->sum('amount');

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_id' => $uid,
                'saldo_total' => $user->saldo_total ?? 0,
                'total_credit' => $totalCredit,
                'total_debit' => $totalDebit,
            ],
        ]);
    }

    /**
     * Get own saldo transaction history (for ibu_hamil via API)
     */
    public function getOwnHistory(Request $request)
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = Saldo::where('user_id', $uid);

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $history = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $history,
        ]);
    }

    /**
     * Get incentive summary (total earned from community activity)
     */
    public function getIncentiveSummary(Request $request)
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $incentives = Saldo::where('user_id', $uid)
            ->where('type', 'credit')
            ->where('status', 'active')
            ->where('keterangan', 'LIKE', 'Insentif%')
            ->orderByDesc('created_at')
            ->get();

        $totalEarned = $incentives->sum('amount');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_earned' => $totalEarned,
                'total_incentives' => $incentives->count(),
                'incentives' => $incentives,
            ],
        ]);
    }
}