<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleLargeUpload
{
    public function handle(Request $request, Closure $next)
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, PUT, DELETE')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        // Set timeout untuk request besar
        set_time_limit(1800);

        // Log request untuk debugging
        if ($request->hasFile('file')) {
            \Log::info('Large upload detected', [
                'size' => $request->file('file')->getSize(),
                'name' => $request->file('file')->getClientOriginalName()
            ]);
        }

        return $next($request);
    }
}
