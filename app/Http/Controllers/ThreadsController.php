<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Support\AuthToken;
use Illuminate\Support\Str;

class ThreadsController extends Controller
{
public function getAll(Request $request)
{
    [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan', 'admin']);

    $data = (int) $request->query('data', 30);
    if ($data > 100) $data = 100;

    $page = (int) $request->query('page', 1);
    $offset = ($page - 1) * $data;

    $total = DB::table('threads')
        ->whereNull('parent_id')
        ->where('is_archived', false)
        ->count();

    $threads = DB::table('threads')
        ->leftJoin('users', 'threads.user_id', '=', 'users.user_id')
        ->leftJoin('user_profile', 'threads.user_id', '=', 'user_profile.user_id')
        ->whereNull('threads.parent_id')
        ->where('threads.is_archived', false)
        ->orderByDesc('threads.views')        
        ->orderByDesc('threads.created_at')  
        ->orderByDesc('threads.likes_count')
        ->offset($offset)
        ->limit($data)
        ->select(
            'threads.thread_id',
            'threads.user_id',
            'threads.category',
            'threads.content',
            'threads.views',
            'threads.likes_count',
            'threads.created_at',
            'users.name',
            'user_profile.photo'
        )
        ->get()
        ->map(function ($item) {
            $item->content_preview = Str::limit(strip_tags($item->content), 120);
            // Transform photo URL using PhotoHelper
            if (!empty($item->photo)) {
                $item->photo = \App\Helpers\PhotoHelper::transformPhotoUrl($item->photo, 'public');
            }

            return $item;
        });


    return response()->json([
        'current_page' => $page,
        'per_page' => $data,
        'total' => $total,
        'last_page' => ceil($total / $data),
        'from' => $offset + 1,
        'to' => min($offset + $data, $total),
        'data' => $threads,
    ]);
}


    public function create(Request $request)
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan']);

        $v = Validator::make($request->all(), [
            'content'  => 'required|string',
            'category' => 'nullable|string|max:100',
        ]);

        if ($v->fails()) return response()->json(['status'=>'error','errors'=>$v->errors()],422);

        $thread_id = DB::table('threads')->insertGetId([
            'user_id'     => $uid,
            'parent_id'   => null,
            'category'    => $request->category ?? 'general',
            'content' => $request->input('content'),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Thread berhasil dibuat.',
            'data'    => ['thread_id' => $thread_id]
        ], 201);
    }

    public function reply(Request $request, int $id)
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan']);

        $parent = DB::table('threads')->where('thread_id', $id)->first();
        if (! $parent) return response()->json(['status'=>'error','message'=>'Thread utama tidak ditemukan.'],404);

        $v = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($v->fails()) return response()->json(['status'=>'error','errors'=>$v->errors()],422);

        $reply_id = DB::table('threads')->insertGetId([
            'user_id'     => $uid,
            'parent_id'   => $id,
            'category'    => $parent->category,
            'content' => $request->input('content'),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Reply berhasil ditambahkan.',
            'data'    => ['thread_id' => $reply_id]
        ], 201);
    }

    public function delete(Request $request, int $id)
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan', 'admin']);

        $thread = DB::table('threads')->where('thread_id', $id)->first();
        if (! $thread) return response()->json(['status'=>'error','message'=>'Thread tidak ditemukan.'],404);

        if ($role !== 'admin' && (int)$thread->user_id !== $uid) {
            return response()->json(['status'=>'error','message'=>'Kamu tidak memiliki izin untuk menghapus thread ini.'],403);
        }

        DB::table('threads')->where('thread_id', $id)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Thread berhasil dihapus.'
        ], 200);
    }
}
