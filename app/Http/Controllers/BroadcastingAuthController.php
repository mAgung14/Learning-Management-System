<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastingAuthController extends Controller
{
    /**
     * Authenticate a user for a private broadcasting channel.
     *
     * The frontend (Laravel Echo + Reverb) sends a POST to /api/broadcasting/auth
     * with `socket_id` and `channel_name`. We delegate to Laravel's built-in
     * broadcaster which generates the correct HMAC-SHA256 signature using
     * REVERB_APP_SECRET and returns a valid JSON auth payload.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authorize(Request $request)
    {
        $request->validate([
            'socket_id'    => 'required|string',
            'channel_name' => 'required|string',
        ]);

        try {
            // Delegate to the configured broadcaster (reverb/pusher).
            // This calls PusherBroadcaster::auth() which:
            //   1. Verifies the channel authorization via routes/channels.php
            //   2. Generates the HMAC-SHA256 signature with REVERB_APP_SECRET
            //   3. Returns the JSON auth payload expected by the client
            $response = Broadcast::auth($request);

            // Broadcast::auth() may return a Response or a raw array/string.
            // Normalise to always return a JsonResponse so the client never
            // receives an empty body or an HTML error page.
            if ($response instanceof \Illuminate\Http\Response) {
                $content = $response->getContent();
                $decoded = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return response()->json($decoded, $response->getStatusCode());
                }

                // Content was not JSON — wrap it
                return response()->json(['auth' => $content], $response->getStatusCode());
            }

            // Already a JsonResponse or array
            if (is_array($response)) {
                return response()->json($response);
            }

            return $response;

        } catch (\Illuminate\Broadcasting\BroadcastException $e) {
            return response()->json([
                'message' => 'Broadcasting authentication failed: ' . $e->getMessage(),
            ], 403);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return response()->json([
                'message' => 'Access denied to channel.',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Broadcasting auth error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
