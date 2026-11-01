<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Komunitas;
use App\Models\KomentarKomunitas;
use App\Models\UserNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class BidanForumController extends Controller
{
    private static array $kategoriLabels = [
        'keluhan_gejala' => 'Keluhan & Gejala',
        'nutrisi'        => 'Nutrisi & Gizi',
        'persalinan'     => 'Persiapan Persalinan',
        'mitos_fakta'    => 'Mitos & Fakta Kehamilan',
        'parenting'      => 'Parenting & Pascapersalinan',
        'umum'           => 'Diskusi Umum',
    ];

    /**
     * Daftar antrean thread forum untuk dipantau dan dijawab oleh Bidan.
     */
    public function index(Request $request)
    {
        $bidan = $this->resolveAuthenticatedUser();
        if (!$bidan || !$bidan->isBidan()) {
            return response()->json(['message' => 'Hanya bidan terdaftar yang memiliki akses ke portal ini.'], 403);
        }

        $tab      = $request->query('tab', 'needs_reply');
        $kategori = $request->query('kategori');
        $search   = $request->query('search');
        $limit    = (int) $request->query('limit', 15);
        $page     = (int) $request->query('page', 1);

        $query = Komunitas::with(['user.selectedIcon', 'bidanReplies.bidan']);

        // Filter tab
        if ($tab === 'needs_reply') {
            $query->where('has_bidan_reply', false);
        } elseif ($tab === 'validated') {
            $query->where('has_bidan_reply', true);
        }

        // Filter kategori
        if (!empty($kategori) && $kategori !== 'semua' && $kategori !== 'all') {
            $query->where('kategori', $kategori);
        }

        // Filter search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $threads = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Hitung statistik antrean untuk badge web
        $totalNeedsReply = Komunitas::where('has_bidan_reply', false)->count();
        $totalValidated  = Komunitas::where('has_bidan_reply', true)->count();
        $totalThreads    = Komunitas::count();

        $formatted = $threads->map(function ($thread) {
            $gestationalContext = null;
            if ($thread->gestational_age_weeks || $thread->trimester) {
                $weeks = $thread->gestational_age_weeks;
                $trim  = $thread->trimester ?? ($weeks ? ($weeks <= 13 ? 1 : ($weeks <= 27 ? 2 : 3)) : null);
                $parts = [];
                if ($trim)  $parts[] = "Trimester {$trim}";
                if ($weeks) $parts[] = "Minggu ke-{$weeks}";

                $gestationalContext = [
                    'weeks'     => $weeks,
                    'trimester' => $trim,
                    'label'     => implode(' • ', $parts),
                ];
            }

            return [
                'id'                  => $thread->post_id,
                'judul'               => $thread->judul,
                'deskripsi'           => $thread->deskripsi,
                'kategori'            => $thread->kategori ?? 'umum',
                'kategori_label'      => self::$kategoriLabels[$thread->kategori ?? 'umum'] ?? 'Diskusi Umum',
                'is_anonymous'        => (bool) $thread->is_anonymous,
                'gestational_context' => $gestationalContext,
                'has_bidan_reply'     => (bool) $thread->has_bidan_reply,
                'apresiasi'           => (int) $thread->apresiasi,
                'komen'               => (int) $thread->komen,
                'gambar'              => $thread->gambar,
                'created_at'          => $thread->created_at,
                'updated_at'          => $thread->updated_at,
                'patient'             => [
                    'id'            => $thread->user_id,
                    'name'          => $thread->is_anonymous ? "Bunda Anonim ({$thread->user?->name})" : ($thread->user?->name ?? 'Ibu Hamil'),
                    'actual_name'   => $thread->user?->name,
                    'profile_image' => $thread->user?->selected_icon_data_cache ?? null,
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'stats'  => [
                'needs_reply' => $totalNeedsReply,
                'validated'   => $totalValidated,
                'total'       => $totalThreads,
            ],
            'data'   => $formatted,
            'pagination' => [
                'current_page' => $threads->currentPage(),
                'last_page'    => $threads->lastPage(),
                'per_page'     => $threads->perPage(),
                'total'        => $threads->total(),
            ],
        ]);
    }

    /**
     * Detail utas pertanyaan beserta seluruh komentar.
     */
    public function show($postId)
    {
        $bidan = $this->resolveAuthenticatedUser();
        if (!$bidan || !$bidan->isBidan()) {
            return response()->json(['message' => 'Hanya bidan terdaftar yang memiliki akses.'], 403);
        }

        $thread = Komunitas::with(['user.selectedIcon', 'user.bidanProfile'])->find($postId);
        if (!$thread) {
            return response()->json(['status' => 'error', 'message' => 'Thread tidak ditemukan.'], 404);
        }

        $gestationalContext = null;
        if ($thread->gestational_age_weeks || $thread->trimester) {
            $weeks = $thread->gestational_age_weeks;
            $trim  = $thread->trimester ?? ($weeks ? ($weeks <= 13 ? 1 : ($weeks <= 27 ? 2 : 3)) : null);
            $parts = [];
            if ($trim)  $parts[] = "Trimester {$trim}";
            if ($weeks) $parts[] = "Minggu ke-{$weeks}";

            $gestationalContext = [
                'weeks'     => $weeks,
                'trimester' => $trim,
                'label'     => implode(' • ', $parts),
            ];
        }

        $comments = KomentarKomunitas::where('post_id', $postId)
            ->with(['user.selectedIcon', 'user.bidanProfile', 'bidan.bidanProfile'])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('is_bidan_reply', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($c) {
                return [
                    'id'                  => $c->id,
                    'user_id'             => $c->user_id,
                    'author_name'         => $c->user?->name ?? 'Pengguna',
                    'author_role'         => $c->is_bidan_reply ? 'bidan' : ($c->user?->role ?? 'ibu_hamil'),
                    'komentar'            => $c->komentar,
                    'is_bidan_reply'      => (bool) $c->is_bidan_reply,
                    'is_pinned'           => (bool) $c->is_pinned,
                    'suggest_appointment' => (bool) $c->suggest_appointment,
                    'created_at'          => $c->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                  => $thread->post_id,
                'judul'               => $thread->judul,
                'deskripsi'           => $thread->deskripsi,
                'kategori'            => $thread->kategori ?? 'umum',
                'kategori_label'      => self::$kategoriLabels[$thread->kategori ?? 'umum'] ?? 'Diskusi Umum',
                'is_anonymous'        => (bool) $thread->is_anonymous,
                'gestational_context' => $gestationalContext,
                'has_bidan_reply'     => (bool) $thread->has_bidan_reply,
                'patient'             => [
                    'id'          => $thread->user_id,
                    'name'        => $thread->is_anonymous ? "Bunda Anonim ({$thread->user?->name})" : ($thread->user?->name ?? 'Ibu Hamil'),
                    'actual_name' => $thread->user?->name,
                ],
                'created_at'          => $thread->created_at,
                'comments'            => $comments,
            ],
        ]);
    }

    /**
     * Bidan memberikan tanggapan klinis resmi & menyematkannya di puncak komentar.
     */
    public function reply(Request $request, $postId)
    {
        $bidan = $this->resolveAuthenticatedUser();
        if (!$bidan || !$bidan->isBidan()) {
            return response()->json(['message' => 'Hanya bidan terdaftar yang dapat memberikan validasi resmi.'], 403);
        }

        $request->validate([
            'komentar'            => 'required|string|min:5|max:5000',
            'suggest_appointment' => 'nullable|boolean',
        ]);

        $post = Komunitas::find($postId);
        if (!$post) {
            return response()->json(['status' => 'error', 'message' => 'Thread pertanyaan tidak ditemukan.'], 404);
        }

        try {
            DB::beginTransaction();

            $reply = KomentarKomunitas::create([
                'post_id'             => $postId,
                'user_id'             => $bidan->user_id,
                'komentar'            => $request->komentar,
                'is_bidan_reply'      => true,
                'is_pinned'           => true,
                'suggest_appointment' => $request->boolean('suggest_appointment', false),
                'bidan_user_id'       => $bidan->user_id,
            ]);

            $post->increment('komen');
            $post->update(['has_bidan_reply' => true]);

            // Kirim notifikasi in-app ke pemilik postingan
            if ((string) $post->user_id !== (string) $bidan->user_id) {
                UserNotification::create([
                    'user_id' => $post->user_id,
                    'type'    => 'bidan_forum_reply',
                    'title'   => 'Tanggapan Medis Diterima',
                    'message' => "Bidan {$bidan->name} telah memberikan tanggapan klinis pada pertanyaan: \"{$post->judul}\"",
                    'data'    => [
                        'post_id'             => (int) $post->post_id,
                        'bidan_user_id'       => (int) $bidan->user_id,
                        'bidan_name'          => $bidan->name,
                        'suggest_appointment' => $request->boolean('suggest_appointment', false),
                    ],
                    'is_read' => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Tanggapan resmi bidan berhasil disematkan.',
                'data'    => $reply,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bidan reply error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan tanggapan bidan: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolveAuthenticatedUser(): ?User
    {
        try {
            if ($tokenUser = JWTAuth::parseToken()->authenticate()) {
                return $tokenUser;
            }
        } catch (\Throwable $th) {
        }

        return Auth::guard('api')->user();
    }
}
