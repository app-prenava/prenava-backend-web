<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Komunitas;
use App\Models\KomentarKomunitas;
use App\Models\Like;
use App\Models\Saldo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class KomunitasController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);

        $Komunitas = Komunitas::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Transform data to include user information
        $KomunitasWithUser = $Komunitas->map(function ($komunitas) {
            return [
                'id' => $komunitas->post_id,
                'judul' => $komunitas->judul,
                'deskripsi' => $komunitas->deskripsi,
                'apresiasi' => $komunitas->apresiasi,
                'komen' => $komunitas->komen,
                'created_at' => $komunitas->created_at,
                'updated_at' => $komunitas->updated_at,
                'user_id' => $komunitas->user_id,
                'user' => $komunitas->user ? [
                    'id' => $komunitas->user->user_id,
                    'name' => $komunitas->user->name,
                    'profile_image' => $komunitas->user->selected_icon_data_cache ?? null,
                ] : null
            ];
        });

        return response()->json([
            'Komunitas' => $KomunitasWithUser,
            'pagination' => [
                'current_page' => $Komunitas->currentPage(),
                'last_page' => $Komunitas->lastPage(),
                'per_page' => $Komunitas->perPage(),
                'total' => $Komunitas->total(),
            ]
        ]);
    }

    public function indexid($id)
    {
        $komunitas = Komunitas::find($id);

        if (!$komunitas) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan untuk post_id: ' . $id
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'Komunitas' => $komunitas
        ]);
    }
    
     public function indexlatest()
    {
        $Komunitas = Komunitas::latest()->first();

        return response()->json([
            'Komunitas' => $Komunitas
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->resolveAuthenticatedUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $request->validate([
            'judul' => 'required',
            'deskripsi' => 'required',
            'gambar' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:1024',
        ]);

        $gambarPath = null;
        if ($request->hasFile('gambar')) {
            $gambarPath = $request->file('gambar')->store('komunitas', 'public');
            $gambarPath = asset('storage/' . $gambarPath);
        } elseif ($request->gambar && is_string($request->gambar)) {
            $gambarPath = $request->gambar;
        }

        $komunitas = Komunitas::create([
            'user_id' => $user->user_id,
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi,
            'gambar' => $gambarPath,
            'apresiasi' => 0, 
            'komen' => 0, 
        ]);
        
        return response()->json([
            'status' => 'berhasil',
            'message' => 'Post created successfully',
            'data' => $komunitas
        ], 201);
    }

public function addComment(Request $request, $postId)
{
    $user = $this->resolveAuthenticatedUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
            }

    $request->validate([
        'komentar' => 'required|string|max:1000',
    ]);

    // Check if the post exists
    $post = Komunitas::find($postId);
    
    if (!$post) {
        return response()->json([
            'status' => 'error',
            'message' => 'Post not found'
        ], 404);
    }

    // Get current user from token (assume this is handled by auth middleware)
    $user = $this->resolveAuthenticatedUser();
    $userId = $user ? $user->user_id : null;

    // Create the comment
    try {
        $komentarKomunitas = KomentarKomunitas::create([
            'post_id' => $postId, // Use the URL parameter
            'user_id' => $user->user_id, // Simpan user ID dari JWT
            'komentar' => $request->komentar,
        ]);

        // Increment comment count on post
        $post->increment('komen');
        
        return response()->json([
            'status' => 'berhasil',
            'message' => 'Comment added successfully',
            'data' => $komentarKomunitas
        ], 201);
    } catch (\Exception $e) {
        Log::error('Error adding comment: ' . $e->getMessage());
        
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to add comment',
            'debug_info' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    public function getComments($postId)
    {
        // Check if post with this ID exists
        $post = Komunitas::find($postId);
        
        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found'
            ], 404);
        }

        // Ambil semua komentar untuk post ini beserta data user-nya
        $comments = KomentarKomunitas::where('post_id', $postId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'post_id' => $comment->post_id,
                    'user_id' => $comment->user_id,
                    'komentar' => $comment->komentar,
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'user' => $comment->user ? [
                        'id' => $comment->user->user_id,
                        'name' => $comment->user->name,
                        'profile_image' => $comment->user->selected_icon_data_cache ?? null,
                    ] : null,
                ];
            });
        
        return response()->json([
            'status' => 'berhasil',
            'data' => $comments
        ], 200);
    }



    public function addLike(Request $request, $postId)
    {
        DB::beginTransaction();
        try {
            // Check if post exists
            $post = Komunitas::find($postId);
            
            if (!$post) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Post not found'
                ], 404);
            }

            // Get current user from token (assume this is handled by auth middleware)
            $user = $this->resolveAuthenticatedUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Check if user already liked this post
            $existingLike = Like::where('post_id', $postId)
                                ->where('user_id', $user->user_id)
                                ->first();
            
            if ($existingLike) {
                // User already liked, so remove the like (unlike)
                $existingLike->delete();
                
                // Decrease like count
                $post->decrement('apresiasi');
                
                DB::commit();
                
                return response()->json([
                    'status' => 'berhasil',
                    'message' => 'Post unliked successfully',
                    'is_liked' => false,
                    'apresiasi' => $post->apresiasi // Return the updated count
                ], 200);
            } else {
                // User hasn't liked, add like
                Like::create([
                    'post_id' => $postId,
                    'user_id' => $user->user_id,
                ]);
                
                // Increase like count
                $post->increment('apresiasi');
                
            // Refresh post data to get updated apresiasi count
                $post->refresh();
                
                // Check for incentive based on configurable threshold
                $threshold = (int) DB::table('parameterized')
                    ->where('key', 'INCENTIVE_LIKE_THRESHOLD')
                    ->value('value') ?: 99;

                $incentiveMessage = '';
                if ($post->apresiasi >= $threshold) {
                    $incentiveResult = $this->checkAndGiveIncentive($post);
                    if ($incentiveResult['given']) {
                        $incentiveMessage = $incentiveResult['message'];
                    }
                }
                
                DB::commit();
                
                $response = [
                    'status' => 'berhasil',
                    'message' => 'Post liked successfully',
                    'is_liked' => true,
                    'apresiasi' => $post->apresiasi
                ];
                
                // Add incentive message if applicable
                if (!empty($incentiveMessage)) {
                    $response['incentive_message'] = $incentiveMessage;
                }
                
                return response()->json($response, 201);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    private function checkAndGiveIncentive($post)
    {
        try {
            // Get post owner
            $postOwner = User::find($post->user_id);
            if (!$postOwner) {
                return ['given' => false, 'message' => 'Post owner not found'];
            }
            
            // Check if this post has already received incentive
            // Assuming you have a table to track incentives or use a flag in posts table
            // Option 1: Check if there's already an incentive record for this post
            $existingIncentive = Saldo::where('user_id', $postOwner->user_id)
                                    ->where('keterangan', 'LIKE', 'Insentif 99+ likes untuk post ID: ' . $post->post_id)
                                    ->first();
            
            if ($existingIncentive) {
                return ['given' => false, 'message' => 'Incentive already given for this post'];
            }
            
            // Give incentive
            $saldo = new Saldo();
            $saldo->user_id = $postOwner->user_id;
            $saldo->setAttribute('amount', 5000);
            $saldo->type = 'credit';
            $saldo->keterangan = 'Insentif 99+ likes untuk post ID: ' . $post->post_id;
            $saldo->save();

            // Update total saldo pada user
            $postOwner->saldo_total = $postOwner->saldo_total + 5000;
            $postOwner->save();
            
            return [
                'given' => true, 
                'message' => 'Selamat! Pemilik post mendapat insentif Rp 5.000 karena mencapai 99+ likes!'
            ];
            
        } catch (\Exception $e) {
            return ['given' => false, 'message' => 'Error giving incentive: ' . $e->getMessage()];
        }
    }

    public function checkLikeStatus(Request $request, $postId)
    {
        $request->validate([
            'user_id' => 'required',
        ]);

        // Cek apakah user sudah memberikan like pada post ini
        $existingLike = Like::where('post_id', $postId)
                            ->where('user_id', $request->user_id)
                            ->exists();
        
        return response()->json([
            'status' => 'berhasil',
            'is_liked' => $existingLike
        ], 200);
    }

    public function deleteAll()
    {
        Komunitas::truncate(); 
        return response()->json([
            'status' => 'success',
            'message' => 'All data deleted successfully'
        ], 200);
    }

   
    public function deleteById($id)
    {
        $komunitas = Komunitas::find($id);

        if (!$komunitas) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found'
            ], 404);
        }

        $komunitas->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Data deleted successfully'
        ], 200);
    }

    /**
     * Resolve authenticated user from the API guard or raw JWT token.
     */
    private function resolveAuthenticatedUser(): ?User
    {
        $user = Auth::guard('api')->user();
        if ($user) {
            return $user;
        }

        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $th) {
            return null;
        }
    }
}