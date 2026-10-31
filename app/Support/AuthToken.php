<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

final class AuthToken
{
    public static function payloadOrFail(Request $request)
    {
        if ($request->attributes->has('auth.payload')) {
            return $request->attributes->get('auth.payload');
        }

        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                $authHeader = $request->header('Authorization') ?: $request->server('HTTP_AUTHORIZATION');
                if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
                    $token = $m[1];
                }
            }

            if (!$token) {
                abort(response()->json(['status'=>'error','message'=>'Missing Authorization: Bearer <token> header.'], 401));
            }

            $payload = JWTAuth::setToken($token)->getPayload();
            $request->attributes->set('auth.payload', $payload);
            return $payload;

        } catch (TokenExpiredException $e) {
            abort(response()->json(['status'=>'error','message'=>'Token has expired.'], 401));
        } catch (TokenInvalidException $e) {
            abort(response()->json(['status'=>'error','message'=>'Token is invalid.'], 401));
        } catch (JWTException $e) {
            abort(response()->json(['status'=>'error','message'=>'Unable to parse token.'], 401));
        } catch (\Throwable $e) {
            abort(response()->json(['status'=>'error','message'=>'Invalid or missing token.'], 401));
        }
    }

    public static function uidRoleOrFail(Request $request): array
    {
        if ($request->attributes->has('auth.claims')) {
            return $request->attributes->get('auth.claims');
        }

        $p    = self::payloadOrFail($request);
        $uid  = (int) $p->get('uid');
        $role = strtolower((string) $p->get('role'));

        if (!$uid || !$role) {
            abort(response()->json(['status'=>'error','message'=>'Token missing required claims (uid/role).'], 401));
        }

        $claims = [$uid, $role, $p];
        $request->attributes->set('auth.claims', $claims);
        return $claims;
    }

    public static function ensureActiveAndFreshOrFail(Request $request): array
    {
        if ($request->attributes->has('auth.user_state')) {
            return $request->attributes->get('auth.user_state');
        }

        [$uid, $role, $payload] = self::uidRoleOrFail($request);
        $tv = (int) $payload->get('tv');

        $row = DB::table('users')
            ->where('user_id', $uid)
            ->select('is_active','token_version')
            ->first();

        if (!$row || !$row->is_active || (int)$row->token_version !== $tv) {
            abort(response()->json(['status'=>'error','message'=>'Token revoked or account deactivated.'], 401));
        }

        $triple = [$uid, $role, $payload];
        $request->attributes->set('auth.user_state', $triple);
        return $triple;
    }

    public static function assertRole(Request $r, $must): array
    {
        [$uid, $role, $p] = self::uidRoleOrFail($r);

        $allowed = is_array($must) ? $must : [$must];
        $allowed = array_map('strtolower', $allowed);

        if (!in_array($role, $allowed, true)) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Access denied: invalid role.',
            ], 403));
        }

        return [$uid, $role, $p];
    }

    public static function assertRoleFresh(Request $r, $must): array
    {
        [$uid, $role, $p] = self::ensureActiveAndFreshOrFail($r);

        $allowed = is_array($must) ? $must : [$must];
        $allowed = array_map('strtolower', $allowed);

        if (!in_array($role, $allowed, true)) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Access denied: invalid role.',
            ], 403));
        }

        return [$uid, $role, $p];
    }


}
