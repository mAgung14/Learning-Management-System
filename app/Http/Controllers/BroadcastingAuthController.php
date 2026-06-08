<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BroadcastingAuthController extends Controller
{
    /**
     * Authenticate a user for a private broadcasting channel.
     *
     * Replaces Laravel's default Broadcast::routes() endpoint to ensure
     * a valid JSON response is always returned, which is required by the
     * Laravel Echo + Pusher client when connecting to Reverb.
     */
    public function authorize(Request $request): JsonResponse
    {
        $socketId    = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        if (empty($socketId) || empty($channelName)) {
            return response()->json([
                'message' => 'socket_id and channel_name are required.',
            ], 422);
        }

        try {
            $appKey    = config('broadcasting.connections.reverb.key');
            $appSecret = config('broadcasting.connections.reverb.secret');

            if (empty($appKey) || empty($appSecret)) {
                Log::error('[BroadcastingAuth] REVERB_APP_KEY or REVERB_APP_SECRET is not configured.');

                return response()->json([
                    'message' => 'Broadcasting is not configured properly.',
                ], 500);
            }

            // Pusher/Reverb auth signature: HMAC-SHA256 of "{socket_id}:{channel_name}"
            $stringToSign = "{$socketId}:{$channelName}";
            $signature    = hash_hmac('sha256', $stringToSign, $appSecret);
            $auth         = "{$appKey}:{$signature}";

            return response()->json([
                'auth' => $auth,
            ]);
        } catch (\Throwable $e) {
            Log::error('[BroadcastingAuth] Failed to generate auth signature: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to authenticate broadcasting channel.',
            ], 500);
        }
    }
}
