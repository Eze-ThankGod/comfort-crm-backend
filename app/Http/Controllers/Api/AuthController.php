<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

        $user = Auth::guard('api')->user();

        if (! $user->is_active) {
            Auth::guard('api')->logout();
            return $this->error('Your account has been deactivated.', 403);
        }

        return $this->respondWithToken($token);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            // refresh() invalidates the old token and returns a new one.
            // We must then set the NEW token on the guard before any user() call,
            // otherwise the guard will try to re-authenticate with the now-blacklisted old token.
            $newToken = JWTAuth::parseToken()->refresh();
            Auth::guard('api')->setToken($newToken);

            return $this->respondWithToken($newToken);
        } catch (TokenBlacklistedException $e) {
            return $this->error('Token has already been used. Please login again.', 401);
        } catch (TokenExpiredException $e) {
            return $this->error('Refresh window expired. Please login again.', 401);
        } catch (JWTException $e) {
            return $this->error('Invalid token: ' . $e->getMessage(), 401);
        } catch (\Throwable $e) {
            return $this->error('Token refresh failed: ' . $e->getMessage(), 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('api')->logout();
        return response()->json(['status' => 'success', 'message' => 'Successfully logged out']);
    }

    private function respondWithToken(string $token): JsonResponse
    {
        // Always resolve user from the explicit token to avoid stale guard state.
        $user = Auth::guard('api')->setToken($token)->user();

        return response()->json([
            'status'       => 'success',
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user'         => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'role'   => $user->role,
                'phone'  => $user->phone,
                'avatar' => $user->avatar,
            ],
        ]);
    }
}
