<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AttachJwtFromCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookie('token');

        // Jika token tidak ditemukan di parameter cookie (seperti dalam beberapa client pengujian),
        // coba ekstrak secara manual dari header Cookie
        if (!$token && $request->headers->has('cookie')) {
            $cookieHeader = $request->headers->get('cookie');
            if (preg_match('/token=([^;]+)/', $cookieHeader, $matches)) {
                $token = $matches[1];
            }
        }

        if ($token && !$request->headers->has('Authorization')) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
