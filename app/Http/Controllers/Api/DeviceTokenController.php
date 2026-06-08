<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token'       => 'required|string',
            'platform'    => 'required|in:android,ios,web',
            'device_name' => 'nullable|string|max:255',
        ]);

        $companyId = $request->header('x-company-id');
        $user = Auth::user();

        DeviceToken::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id'      => $user->id,
                'company_id'   => $companyId,
                'platform'     => $request->platform,
                'device_name'  => $request->device_name,
                'last_used_at' => now(),
            ]
        );

        return ApiResponse::success(null, 'Token registrado correctamente');
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        DeviceToken::where('token', $request->token)
            ->where('user_id', Auth::id())
            ->delete();

        return ApiResponse::success(null, 'Token eliminado correctamente');
    }
}
