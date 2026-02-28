<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Support\AuthToken;
use App\Helpers\ShopLog;

class ShopController extends Controller
{
    private const STORAGE_PREFIX = 'storage/';
    private const MSG_PHOTO_INVALID = 'File harus berupa foto';
    private const MSG_PRODUCT_NOT_FOUND = 'Produk tidak ditemukan.';

    protected function formatPrice(string $price): string
    {
        $clean = preg_replace('/\D/', '', $price);
        if ($clean === '') {
            return '0';
        }
        return number_format((int)$clean, 0, ',', '.');
    }

    public function getAll(Request $request)
    {
        AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $data = (int) $request->query('data', 30);
        $page = (int) $request->query('page', 1);

        if ($data < 1) {
            $data = 1;
        }
        if ($data > 100) {
            $data = 100;
        }
        if ($page < 1) {
            $page = 1;
        }

        $query = DB::table('shop')->orderByDesc('product_id');

        $total = $query->count();
        $result = $query
            ->offset(($page - 1) * $data)
            ->limit($data)
            ->get()
            ->map(function ($item) {
                $item->photo = url(self::STORAGE_PREFIX . ltrim($item->photo, '/'));
                return $item;
            });

        return response()->json([
            'current_page' => $page,
            'per_page'     => $data,
            'total'        => $total,
            'last_page'    => (int) ceil($total / $data),
            'from'         => ($page - 1) * $data + 1,
            'to'           => ($page - 1) * $data + count($result),
            'data'         => $result
        ]);
    }

    public function getByUser(Request $request)
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $data = (int) $request->query('data', 30);
        $page = (int) $request->query('page', 1);

        if ($data < 1) {
            $data = 1;
        }
        if ($data > 100) {
            $data = 100;
        }
        if ($page < 1) {
            $page = 1;
        }

        $query = DB::table('shop')
            ->where('user_id', $uid)
            ->orderByDesc('product_id');

        $total = $query->count();
        $result = $query
            ->offset(($page - 1) * $data)
            ->limit($data)
            ->get()
            ->map(function ($item) {
                $item->photo = url(self::STORAGE_PREFIX . ltrim($item->photo, '/'));
                return $item;
            });

        return response()->json([
            'current_page' => $page,
            'per_page'     => $data,
            'total'        => $total,
            'last_page'    => (int) ceil($total / $data),
            'from'         => ($page - 1) * $data + 1,
            'to'           => ($page - 1) * $data + count($result),
            'data'         => $result,
        ]);
    }


    public function create(Request $request): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $messages = [
            'photo.image' => self::MSG_PHOTO_INVALID,
            'photo.mimes' => self::MSG_PHOTO_INVALID,
            'photo.max'   => 'Ukuran file melebihi batas upload, pastikan file dibawah 500KB',
            'url.url'     => 'Data URL belum benar, input dengan format lengkap',
        ];

        $v = Validator::make($request->all(), [
            'product_name' => ['required', 'string', 'max:255'],
            'price'        => ['required', 'string', 'max:50'],
            'url'          => ['required', 'url', 'max:2048'],
            'photo'        => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ], $messages);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $path = $request->file('photo')->store('shop', 'public');
        $priceFormatted = $this->formatPrice($request->price);

        $product_id = DB::table('shop')->insertGetId([
            'user_id'      => $uid,
            'product_name' => $request->product_name,
            'price'        => $priceFormatted,
            'url'          => $request->url,
            'photo'        => $path,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $photoUrl = $path ? asset(self::STORAGE_PREFIX . $path) : null;

        ShopLog::record('create', $uid, [
            'product_id'   => $product_id,
            'product_name' => $request->product_name,
            'price'        => $priceFormatted,
            'url'          => $request->url,
            'photo'        => $photoUrl,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil ditambahkan.',
            'data'    => [
                'product_id'   => $product_id,
                'product_name' => $request->product_name,
                'price'        => $priceFormatted,
                'url'          => $request->url,
                'photo'        => $photoUrl,
            ],
        ], 201);
    }


    public function update(Request $request, int $id): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $row = DB::table('shop')->where('product_id', $id)->where('user_id', $uid)->first();
        if (! $row) {
            return response()->json(['status'=>'error','message'=>'Produk tidak ditemukan atau bukan milik kamu.'], 404);
        }

        $messages = [
            'photo.image' => self::MSG_PHOTO_INVALID,
            'photo.mimes' => self::MSG_PHOTO_INVALID,
            'photo.max'   => 'Ukuran file melebihi batas upload, pastikan file dibawah 500KB',
            'url.url'     => 'Data URL belum benar, input dengan format lengkap',
        ];

        $v = Validator::make($request->all(), [
            'product_name' => ['sometimes', 'required', 'string', 'max:255'],
            'price'        => ['sometimes', 'required', 'string', 'max:50'],
            'url'          => ['sometimes', 'required', 'url', 'max:2048'],
            'photo'        => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ], $messages);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $update = [];
        if ($request->has('product_name')) {
            $update['product_name'] = $request->product_name;
        }
        if ($request->has('price')) {
            $update['price'] = $this->formatPrice($request->price);
        }
        if ($request->has('url')) {
            $update['url'] = $request->url;
        }

        if ($request->hasFile('photo')) {
            $newPath = $request->file('photo')->store('shop', 'public');
            $update['photo'] = $newPath;

            if (!empty($row->photo) && Storage::disk('public')->exists($row->photo)) {
                Storage::disk('public')->delete($row->photo);
            }
        }

        $update['updated_at'] = now();

        DB::table('shop')->where('product_id', $id)->update($update);

        $merged = array_merge((array) $row, $update);
        $merged['photo'] = isset($update['photo'])
            ? asset(self::STORAGE_PREFIX . $update['photo'])
            : asset(self::STORAGE_PREFIX . $row->photo);

        ShopLog::record('update', $uid, $merged);


        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil diperbarui.',
        ], 200);
    }


    public function delete(Request $request, int $id): JsonResponse
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'admin']);

        $row = DB::table('shop')->where('product_id', $id)->first();
        if (! $row) {
            return response()->json(['status'=>'error','message'=> self::MSG_PRODUCT_NOT_FOUND], 404);
        }

        if ($role === 'ibu_hamil' && (int) $row->user_id !== $uid) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: kamu tidak memiliki izin untuk menghapus produk ini.',
            ], 403);
        }

        if (!empty($row->photo) && Storage::disk('public')->exists($row->photo)) {
            Storage::disk('public')->delete($row->photo);
        }

        DB::table('shop')->where('product_id', $id)->delete();

        ShopLog::record(
            $role === 'admin' ? 'admin_delete' : 'delete',
            $uid,
            (array) $row,
            $role === 'admin'
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    public function getShopLogs(Request $request)
    {
        AuthToken::assertRoleFresh($request, 'admin');

        $data = (int) $request->query('data', 50);
        if ($data > 100) {
            $data = 100;
        }

        $page = (int) $request->query('page', 1);
        $offset = ($page - 1) * $data;

        $total = DB::table('shop_logs')->count();

        $logs = DB::table('shop_logs')
            ->orderByDesc('shop_logs_id')
            ->offset($offset)
            ->limit($data)
            ->get()
            ->map(function ($log) {
                $log->data_snapshot = $log->data_snapshot
                    ? json_decode($log->data_snapshot, true)
                    : null;

                $log->can_delete = !in_array($log->action, ['delete', 'admin_delete']);
                return $log;
            });

        return response()->json([
            'current_page' => $page,
            'per_page'     => $data,
            'total'        => $total,
            'last_page'    => ceil($total / $data),
            'from'         => $offset + 1,
            'to'           => $offset + count($logs),
            'data'         => $logs,
        ]);
    }

    public function getReviews(Request $request, int $id): JsonResponse
    {
        $product = DB::table('shop')->where('product_id', $id)->first();
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => self::MSG_PRODUCT_NOT_FOUND], 404);
        }

        $reviews = DB::table('shop_reviews')
            ->leftJoin('users', 'shop_reviews.user_id', '=', 'users.user_id')
            ->where('shop_reviews.product_id', $id)
            ->select(
                'shop_reviews.review_id',
                'shop_reviews.user_id',
                'users.name as user_name',
                'shop_reviews.rating',
                'shop_reviews.review_text',
                'shop_reviews.created_at',
                'shop_reviews.updated_at'
            )
            ->orderByDesc('shop_reviews.created_at')
            ->get();

        $avgRating = DB::table('shop_reviews')
            ->where('product_id', $id)
            ->avg('rating');

        return response()->json([
            'status' => 'success',
            'data' => [
                'product_id' => $id,
                'average_rating' => round($avgRating ?? 0, 1),
                'total_reviews' => count($reviews),
                'reviews' => $reviews,
            ],
        ]);
    }

    public function upsertReview(Request $request, int $id): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $product = DB::table('shop')->where('product_id', $id)->first();
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => self::MSG_PRODUCT_NOT_FOUND], 404);
        }

        $v = Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $existing = DB::table('shop_reviews')
            ->where('product_id', $id)
            ->where('user_id', $uid)
            ->first();

        if ($existing) {
            DB::table('shop_reviews')
                ->where('review_id', $existing->review_id)
                ->update([
                    'rating' => $request->rating,
                    'review_text' => $request->review_text,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Review berhasil diperbarui.',
            ]);
        }

        $reviewId = DB::table('shop_reviews')->insertGetId([
            'product_id' => $id,
            'user_id' => $uid,
            'rating' => $request->rating,
            'review_text' => $request->review_text,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Review berhasil ditambahkan.',
            'data' => ['review_id' => $reviewId],
        ], 201);
    }

    public function deleteReview(Request $request, int $productId, int $reviewId): JsonResponse
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'admin']);

        $review = DB::table('shop_reviews')
            ->where('review_id', $reviewId)
            ->where('product_id', $productId)
            ->first();

        if (!$review) {
            return response()->json(['status' => 'error', 'message' => 'Review tidak ditemukan.'], 404);
        }

        if ($role !== 'admin' && (int) $review->user_id !== $uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kamu tidak memiliki izin untuk menghapus review ini.',
            ], 403);
        }

        DB::table('shop_reviews')->where('review_id', $reviewId)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Review berhasil dihapus.',
        ]);
    }
}