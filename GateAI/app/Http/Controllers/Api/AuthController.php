<?php

namespace App\Http\Controllers\Api;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvatarUploadRequest;
use App\Services\Fmy\AuthService;
use App\Models\User;
use App\Support\LogHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'account'  => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->authService->login([
                'account'  => $request->account,
                'password' => $request->password,
                'remember' => $request->boolean('remember', false),
            ]);
        } catch (BusinessException $e) {
            $responseCode = ResponseCode::tryFrom($e->getCode()) ?: ResponseCode::UNAUTHORIZED;
            return Result::error($responseCode, $e->getMessage(), $e->errorData);
        }

        return Result::success('登录成功', [
            'token'      => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['remember'] ? 43200 * 60 : 480 * 60,
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = JWTAuth::user();
        if ($user) {
            $this->authService->logout($user->id);
        }

        return Result::success('已登出');
    }

    public function me(): JsonResponse
    {
        return Result::success('获取用户信息成功', JWTAuth::user());
    }

    public function uploadAvatar(AvatarUploadRequest $request): JsonResponse
    {
        $user = JWTAuth::user();
        $file = $request->file('avatar');

        $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $ossPath = 'avatars/' . date('Ym') . '/' . $filename;

        try {
            Storage::disk('oss')->put($ossPath, file_get_contents($file->getRealPath()));
        } catch (\Exception $e) {
            LogHelper::error('头像上传OSS失败', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            return Result::error(ResponseCode::OSS_UPLOAD_FAILED);
        }

        $user->avatar = $ossPath;
        $user->save();

        return Result::success('头像上传成功', ['avatar' => $user->avatar]);
    }

    public function refresh(): JsonResponse
    {
        $newToken = JWTAuth::refresh(true);
        $user = JWTAuth::user();

        if ($user) {
            $user->login_token = 'Bearer ' . $newToken;
            $user->token_expire_time = now()->addMinutes(JWTAuth::factory()->getTTL());
            $user->save();
        }

        return Result::success('刷新成功', [
            'token'      => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }
}
