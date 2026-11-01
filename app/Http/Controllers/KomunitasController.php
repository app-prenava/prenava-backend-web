<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Komunitas;
use App\Models\KomentarKomunitas;
use App\Models\Like;
use App\Models\Saldo;
use App\Models\User;
use App\Models\Pregnancy;
use App\Models\PregnancyCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Facades\JWTAuth;

class KomunitasController extends Controller
{
    private static array $kategoriLabels = [
        'keluhan_gejala' => 'Keluhan & Gejala',
        'nutrisi'        => 'Nutrisi & Gizi',
        'persalinan'     => 'Persiapan Persalinan',
        'mitos_fakta'    => 'Mitos & Fakta Kehamilan',
        'parenting'      => 'Parenting & Pascapersalinan',
        'umum'           => 'Diskusi Umum',
    ];

    public function index(Request $request)
    {
        $page     = (int) $request->query('page', 1);
        $limit    = (int) $request->query('limit', 10);
        $kategori = $request->query('kategori');
        $filter   = $request->query('filter', 'all');
        $search   = $request->query('search');

        // Ambil user yang sedang login (nullable, untuk guest)
        $authUser   = $this->resolveAuthenticatedUser();
        $authUserId = $authUser?->user_id;
        $isBidan    = $authUser?->isBidan() ?? false;

        $query = Komunitas::with(['user.selectedIcon', 'user.bidanProfile']);

        // Filter kategori
        if (!empty($kategori) && $kategori !== 'semua' && $kategori !== 'all') {
            $query->where('kategori', $kategori);
        }

        // Filter status / kepemilikan
        if ($filter === 'my_posts' && $authUserId) {
            $query->where('user_id', $authUserId);
        } elseif ($filter === 'has_bidan_reply') {
            $query->where('has_bidan_reply', true);
        } elseif ($filter === 'needs_reply') {
            $query->where('has_bidan_reply', false);
        }

        // Pencarian judul / deskripsi
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $Komunitas = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Kumpulkan semua post_id di halaman ini
        $postIds = $Komunitas->pluck('post_id');

        // Cek like milik user ini pada post di halaman ini
        $likedPostIds = collect();
        if ($authUserId) {
            $likedPostIds = Like::where('user_id', $authUserId)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->flip();
        }

        $KomunitasWithUser = $Komunitas->map(function ($komunitas) use ($likedPostIds, $authUserId, $isBidan) {
            // Masking nama jika anonim (kecuali yang melihat adalah penulis sendiri atau Bidan yang memeriksa)
            $isOwner = $authUserId && ((string) $komunitas->user_id === (string) $authUserId);
            $maskName = $komunitas->is_anonymous && !$isOwner && !$isBidan;

            $authorName = $maskName ? 'Bunda Anonim' : ($komunitas->user?->name ?? 'Pengguna Prenava');
            $authorImage = $maskName ? null : ($komunitas->user?->selected_icon_data_cache ?? null);

            // Susun gestational context
            $gestationalContext = null;
            if ($komunitas->gestational_age_weeks || $komunitas->trimester) {
                $weeks = $komunitas->gestational_age_weeks;
                $trim  = $komunitas->trimester ?? ($weeks ? ($weeks <= 13 ? 1 : ($weeks <= 27 ? 2 : 3)) : null);
                $parts = [];
                if ($trim)  $parts[] = "Trimester {$trim}";
                if ($weeks) $parts[] = "Mg {$weeks}";

                $gestationalContext = [
                    'weeks'     => $weeks,
                    'trimester' => $trim,
                    'label'     => implode(' • ', $parts),
                ];
            }

            return [
                'id'                  => $komunitas->post_id,
                'judul'               => $komunitas->judul,
                'deskripsi'           => $komunitas->deskripsi,
                'kategori'            => $komunitas->kategori ?? 'umum',
                'kategori_label'      => self::$kategoriLabels[$komunitas->kategori ?? 'umum'] ?? 'Diskusi Umum',
                'is_anonymous'        => (bool) $komunitas->is_anonymous,
                'gestational_context' => $gestationalContext,
                'has_bidan_reply'     => (bool) $komunitas->has_bidan_reply,
                'apresiasi'           => (int) $komunitas->apresiasi,
                'komen'               => (int) $komunitas->komen,
                'gambar'              => $komunitas->gambar,
                'is_liked'            => $likedPostIds->has($komunitas->post_id),
                'created_at'          => $komunitas->created_at,
                'updated_at'          => $komunitas->updated_at,
                'user_id'             => $maskName ? null : $komunitas->user_id,
                'user'                => [
                    'id'            => $maskName ? null : $komunitas->user?->user_id,
                    'name'          => $authorName,
                    'role'          => $komunitas->user?->role ?? 'ibu_hamil',
                    'profile_image' => $authorImage,
                ],
            ];
        });

        return response()->json([
            'Komunitas'  => $KomunitasWithUser,
            'pagination' => [
                'current_page' => $Komunitas->currentPage(),
                'last_page'    => $Komunitas->lastPage(),
                'per_page'     => $Komunitas->perPage(),
                'total'        => $Komunitas->total(),
            ],
        ]);
    }

    public function indexid($id)
    {
        $komunitas = Komunitas::with(['user.selectedIcon', 'user.bidanProfile'])->find($id);

        if (!$komunitas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data tidak ditemukan untuk post_id: ' . $id
            ], 404);
        }

        $authUser   = $this->resolveAuthenticatedUser();
        $authUserId = $authUser?->user_id;
        $isBidan    = $authUser?->isBidan() ?? false;
        $isOwner    = $authUserId && ((string) $komunitas->user_id === (string) $authUserId);
        $maskName   = $komunitas->is_anonymous && !$isOwner && !$isBidan;

        $isLiked = false;
        if ($authUserId) {
            $isLiked = Like::where('user_id', $authUserId)->where('post_id', $komunitas->post_id)->exists();
        }

        $gestationalContext = null;
        if ($komunitas->gestational_age_weeks || $komunitas->trimester) {
            $weeks = $komunitas->gestational_age_weeks;
            $trim  = $komunitas->trimester ?? ($weeks ? ($weeks <= 13 ? 1 : ($weeks <= 27 ? 2 : 3)) : null);
            $parts = [];
            if ($trim)  $parts[] = "Trimester {$trim}";
            if ($weeks) $parts[] = "Mg {$weeks}";

            $gestationalContext = [
                'weeks'     => $weeks,
                'trimester' => $trim,
                'label'     => implode(' • ', $parts),
            ];
        }

        $data = [
            'id'                  => $komunitas->post_id,
            'judul'               => $komunitas->judul,
            'deskripsi'           => $komunitas->deskripsi,
            'kategori'            => $komunitas->kategori ?? 'umum',
            'kategori_label'      => self::$kategoriLabels[$komunitas->kategori ?? 'umum'] ?? 'Diskusi Umum',
            'is_anonymous'        => (bool) $komunitas->is_anonymous,
            'gestational_context' => $gestationalContext,
            'has_bidan_reply'     => (bool) $komunitas->has_bidan_reply,
            'apresiasi'           => (int) $komunitas->apresiasi,
            'komen'               => (int) $komunitas->komen,
            'gambar'              => $komunitas->gambar,
            'is_liked'            => $isLiked,
            'created_at'          => $komunitas->created_at,
            'updated_at'          => $komunitas->updated_at,
            'user'                => [
                'id'            => $maskName ? null : $komunitas->user?->user_id,
                'name'          => $maskName ? 'Bunda Anonim' : ($komunitas->user?->name ?? 'Pengguna Prenava'),
                'role'          => $komunitas->user?->role ?? 'ibu_hamil',
                'profile_image' => $maskName ? null : ($komunitas->user?->selected_icon_data_cache ?? null),
            ],
        ];

        return response()->json([
            'status'    => 'success',
            'Komunitas' => $data,
        ]);
    }

    public function indexlatest()
    {
        $komunitas = Komunitas::latest('created_at')->first();

        return response()->json([
            'Komunitas' => $komunitas
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->resolveAuthenticatedUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'judul'        => 'required|string|max:255',
            'deskripsi'    => 'required|string',
            'kategori'     => 'nullable|string|max:50',
            'is_anonymous' => 'nullable|boolean',
            'gambar'       => 'nullable',
        ]);

        $gambarPath = null;
        if ($request->hasFile('gambar')) {
            $gambarPath = $request->file('gambar')->store('komunitas', 'public');
            $gambarPath = asset('storage/' . $gambarPath);
        } elseif ($request->gambar && is_string($request->gambar)) {
            $gambarPath = $request->gambar;
        }

        // Resolusi usia kehamilan otomatis dari data kehamilan aktif pengguna
        $gestationalWeeks = null;
        $trimester = null;

        $pregnancy = Pregnancy::where('user_id', $user->user_id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$pregnancy) {
            $pregnancy = Pregnancy::where('user_id', $user->user_id)->latest()->first();
        }

        if ($pregnancy) {
            if ($pregnancy->lmp_date) {
                $days = Carbon::parse($pregnancy->lmp_date)->diffInDays(now());
                $gestationalWeeks = (int) max(1, min(42, floor($days / 7)));
            } elseif ($pregnancy->gestational_age_weeks) {
                $gestationalWeeks = (int) $pregnancy->gestational_age_weeks;
            }
        }

        if (!$gestationalWeeks) {
            $calc = PregnancyCalculator::where('user_id', $user->user_id)->latest()->first();
            if ($calc && $calc->hpht) {
                $days = Carbon::parse($calc->hpht)->diffInDays(now());
                $gestationalWeeks = (int) max(1, min(42, floor($days / 7)));
            }
        }

        if ($gestationalWeeks) {
            if ($gestationalWeeks <= 13) {
                $trimester = 1;
            } elseif ($gestationalWeeks <= 27) {
                $trimester = 2;
            } else {
                $trimester = 3;
            }
        }

        $kategori = $request->input('kategori', 'umum');
        if (!array_key_exists($kategori, self::$kategoriLabels)) {
            $kategori = 'umum';
        }

        $komunitas = Komunitas::create([
            'user_id'               => $user->user_id,
            'judul'                 => $request->judul,
            'kategori'              => $kategori,
            'is_anonymous'          => $request->boolean('is_anonymous', false),
            'gestational_age_weeks' => $gestationalWeeks,
            'trimester'             => $trimester,
            'deskripsi'             => $request->deskripsi,
            'gambar'                => $gambarPath,
            'apresiasi'             => 0,
            'komen'                 => 0,
            'has_bidan_reply'       => false,
        ]);

        return response()->json([
            'status'  => 'berhasil',
            'message' => 'Post created successfully',
            'data'    => $komunitas
        ], 201);
    }

    public function addComment(Request $request, $postId)
    {
        $user = $this->resolveAuthenticatedUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'komentar'            => 'required|string|max:2000',
            'suggest_appointment' => 'nullable|boolean',
        ]);

        $post = Komunitas::find($postId);
        if (!$post) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Post not found'
            ], 404);
        }

        $isBidan = $user->isBidan();

        try {
            DB::beginTransaction();

            $komentarKomunitas = KomentarKomunitas::create([
                'post_id'             => $postId,
                'user_id'             => $user->user_id,
                'komentar'            => $request->komentar,
                'is_bidan_reply'      => $isBidan,
                'is_pinned'           => $isBidan, // Jawaban bidan otomatis disematkan di paling atas
                'suggest_appointment' => $isBidan ? $request->boolean('suggest_appointment', false) : false,
                'bidan_user_id'       => $isBidan ? $user->user_id : null,
            ]);

            $post->increment('komen');
            if ($isBidan && !$post->has_bidan_reply) {
                $post->update(['has_bidan_reply' => true]);
            }

            // Jika bidan menjawab dan pemilik postingan bukan bidan itu sendiri, kirimkan notifikasi
            if ($isBidan && (string) $post->user_id !== (string) $user->user_id) {
                \App\Models\UserNotification::create([
                    'user_id' => $post->user_id,
                    'type'    => 'bidan_forum_reply',
                    'title'   => 'Tanggapan Medis Diterima',
                    'message' => "Bidan {$user->name} telah memberikan tanggapan klinis pada pertanyaan: \"{$post->judul}\"",
                    'data'    => [
                        'post_id'             => (int) $post->post_id,
                        'bidan_user_id'       => (int) $user->user_id,
                        'bidan_name'          => $user->name,
                        'suggest_appointment' => $request->boolean('suggest_appointment', false),
                    ],
                    'is_read' => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'status'  => 'berhasil',
                'message' => 'Comment added successfully',
                'data'    => $komentarKomunitas
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding comment: ' . $e->getMessage());

            return response()->json([
                'status'     => 'error',
                'message'    => 'Failed to add comment',
                'debug_info' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getComments($postId)
    {
        $post = Komunitas::find($postId);
        if (!$post) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Post not found'
            ], 404);
        }

        // Urutkan: Tersemat (Pinned) paling atas, lalu Tanggapan Bidan, lalu kronologis ASC
        $comments = KomentarKomunitas::where('post_id', $postId)
            ->with(['user.selectedIcon', 'user.bidanProfile', 'bidan.bidanProfile'])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('is_bidan_reply', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($comment) {
                $authorUser = $comment->user;
                $isBidan = (bool) $comment->is_bidan_reply || ($authorUser?->isBidan() ?? false);

                $bidanProfileData = null;
                if ($isBidan) {
                    $bidanUser = $comment->bidan ?? $authorUser;
                    $bProfile  = $bidanUser?->bidanProfile;

                    $bidanProfileData = [
                        'bidan_id'       => $bidanUser?->user_id,
                        'name'           => $bidanUser?->name,
                        'tempat_praktik' => $bProfile?->tempat_praktik ?? 'Klinik Pratama Prenava',
                        'alamat_praktik' => $bProfile?->alamat_praktik ?? 'Jl. Pelayanan Kesehatan Ibu & Anak',
                        'kota'           => $bProfile?->kota_tempat_praktik ?? 'Kota Bandung',
                        'spesialisasi'   => $bProfile?->spesialisasi ?? 'Kebidanan Umum & ANC',
                        'nomor_sipb'     => 'SIPB/503/012/2024',
                    ];
                }

                return [
                    'id'                  => $comment->id,
                    'post_id'             => $comment->post_id,
                    'user_id'             => $comment->user_id,
                    'komentar'            => $comment->komentar,
                    'is_bidan_reply'      => $isBidan,
                    'is_pinned'           => (bool) $comment->is_pinned,
                    'suggest_appointment' => (bool) $comment->suggest_appointment,
                    'bidan_user_id'       => $comment->bidan_user_id,
                    'bidan_profile'       => $bidanProfileData,
                    'created_at'          => $comment->created_at,
                    'updated_at'          => $comment->updated_at,
                    'author'              => [
                        'id'            => $authorUser?->user_id,
                        'name'          => $authorUser?->name ?? 'Pengguna Prenava',
                        'role'          => $isBidan ? 'bidan' : ($authorUser?->role ?? 'ibu_hamil'),
                        'profile_image' => $authorUser?->selected_icon_data_cache ?? null,
                    ],
                ];
            });

        return response()->json([
            'status' => 'berhasil',
            'data'   => $comments
        ], 200);
    }

    public function addLike(Request $request, $postId)
    {
        DB::beginTransaction();
        try {
            $post = Komunitas::find($postId);
            if (!$post) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Post not found'
                ], 404);
            }

            $user = $this->resolveAuthenticatedUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $existingLike = Like::where('post_id', $postId)
                                ->where('user_id', $user->user_id)
                                ->first();

            if ($existingLike) {
                $existingLike->delete();
                $post->decrement('apresiasi');
                DB::commit();

                return response()->json([
                    'status'    => 'berhasil',
                    'message'   => 'Post unliked successfully',
                    'is_liked'  => false,
                    'apresiasi' => $post->apresiasi
                ], 200);
            } else {
                Like::create([
                    'post_id' => $postId,
                    'user_id' => $user->user_id,
                ]);

                $post->increment('apresiasi');
                $post->refresh();

                DB::commit();

                return response()->json([
                    'status'    => 'berhasil',
                    'message'   => 'Post liked successfully',
                    'is_liked'  => true,
                    'apresiasi' => $post->apresiasi
                ], 201);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkLikeStatus(Request $request, $postId)
    {
        $request->validate([
            'user_id' => 'required',
        ]);

        $existingLike = Like::where('post_id', $postId)
                            ->where('user_id', $request->user_id)
                            ->exists();

        return response()->json([
            'status'   => 'berhasil',
            'is_liked' => $existingLike
        ], 200);
    }

    public function deleteAll()
    {
        Komunitas::truncate();
        return response()->json([
            'status'  => 'success',
            'message' => 'All data deleted successfully'
        ], 200);
    }

    public function deleteById($id)
    {
        $komunitas = Komunitas::find($id);

        if (!$komunitas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data not found'
            ], 404);
        }

        $komunitas->delete();
        return response()->json([
            'status'  => 'success',
            'message' => 'Data deleted successfully'
        ], 200);
    }

    public function deleteComment(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $user = $this->resolveAuthenticatedUser();
            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $userId = (int) $user->user_id;

            $comment = KomentarKomunitas::find($id);

            if (!$comment) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Comment not found'
                ], 404);
            }

            // Izinkan jika pemilik komentar ATAU Bidan ATAU Admin
            if ((int) $comment->user_id !== $userId && !$user->isBidan() && !$user->isAdmin()) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Forbidden'
                ], 403);
            }

            $postId = $comment->post_id;
            $comment->delete();

            $post = Komunitas::find($postId);
            if ($post && $post->komen > 0) {
                $post->decrement('komen');
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Comment deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'     => 'error',
                'message'    => 'Failed to delete comment',
                'debug_info' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Resolve authenticated user from the API guard or raw JWT token.
     */
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
