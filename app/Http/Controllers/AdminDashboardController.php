<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function getStats(Request $request): JsonResponse
    {
        // 1. User metrics
        $userCounts = DB::table('users')
            ->select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        $totalUsers = array_sum($userCounts);
        $totalIbuHamil = $userCounts['ibu_hamil'] ?? 0;
        $totalBidan = $userCounts['bidan'] ?? 0;
        $totalDinkes = $userCounts['dinkes'] ?? 0;

        // 2. Community metrics
        $totalPosts = DB::table('komunitas')->count();
        $totalLikes = DB::table('like')->count();
        $totalComments = DB::table('komentarkomunitas')->count();

        $topPosts = DB::table('komunitas')
            ->leftJoin('users', 'komunitas.user_id', '=', 'users.user_id')
            ->select(
                'komunitas.post_id as id',
                'komunitas.judul',
                'komunitas.deskripsi',
                'komunitas.komen as total_comments',
                'komunitas.apresiasi as total_likes',
                'komunitas.created_at',
                'users.name as author_name'
            )
            ->orderByDesc('komunitas.apresiasi')
            ->limit(5)
            ->get();

        $recentComments = DB::table('komentarkomunitas')
            ->leftJoin('users', 'komentarkomunitas.user_id', '=', 'users.user_id')
            ->leftJoin('komunitas', 'komentarkomunitas.post_id', '=', 'komunitas.post_id')
            ->select(
                'komentarkomunitas.id',
                'komentarkomunitas.komentar',
                'komentarkomunitas.created_at',
                'users.name as user_name',
                'komunitas.judul as post_title'
            )
            ->orderByDesc('komentarkomunitas.created_at')
            ->limit(5)
            ->get();

        // 3. Shop metrics
        $totalProducts = DB::table('shop')->count();
        $totalReviews = DB::table('shop_reviews')->count();
        $avgRating = round(DB::table('shop')->avg('average_rating') ?? 0, 1);

        $categoryDistribution = DB::table('shop')
            ->select('category', DB::raw('count(*) as total'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $ratingDistribution = DB::table('shop_reviews')
            ->select('rating', DB::raw('count(*) as total'))
            ->groupBy('rating')
            ->orderBy('rating', 'asc')
            ->get();

        $topProducts = DB::table('shop')
            ->select(
                'product_id as id',
                'product_name',
                'category',
                'price',
                'average_rating',
                'rating_count',
                'photo'
            )
            ->orderByDesc('average_rating')
            ->orderByDesc('rating_count')
            ->limit(5)
            ->get();

        // 4. Platform Services
        $totalKunjungan = DB::table('catatankunjungan')->count();
        $totalTips = DB::table('pregnancy_tips')->count();
        $totalBanners = DB::table('ad_banner')->count();
        $totalSubscriptions = DB::table('subscription_plans')->count();
        $pendingApplications = DB::table('bidan_applications')->where('status', 'pending')->count();

        // 5. Recent Activity Logs
        $recentLogs = DB::table('activity_logs')
            ->select(
                'id',
                'user_name',
                'user_role',
                'activity_type',
                'activity_label',
                'description',
                'created_at'
            )
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_users'        => $totalUsers,
                    'total_ibu_hamil'    => $totalIbuHamil,
                    'total_bidan'        => $totalBidan,
                    'total_dinkes'       => $totalDinkes,
                    'total_posts'        => $totalPosts,
                    'total_likes'        => $totalLikes,
                    'total_comments'     => $totalComments,
                    'total_products'     => $totalProducts,
                    'total_reviews'      => $totalReviews,
                    'avg_rating'         => $avgRating,
                    'total_kunjungan'    => $totalKunjungan,
                    'total_tips'         => $totalTips,
                    'total_banners'      => $totalBanners,
                    'total_subscriptions'=> $totalSubscriptions,
                    'pending_bidan_applications' => $pendingApplications,
                ],
                'community' => [
                    'top_posts'       => $topPosts,
                    'recent_comments' => $recentComments,
                ],
                'shop' => [
                    'categories'      => $categoryDistribution,
                    'ratings'         => $ratingDistribution,
                    'top_products'    => $topProducts,
                ],
                'recent_logs' => $recentLogs,
            ]
        ]);
    }
}
