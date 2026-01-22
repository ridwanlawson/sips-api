<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ApiLog;
use Illuminate\Http\Request;

class ApiLogger
{
    public function handle(Request $request, Closure $next)
    {
        // Process the request
        $response = $next($request);
        
        // Get real IP address
        $ip = $request->header('x-forwarded-for') ?? 
              $request->header('x-real-ip') ?? 
              $request->ip();

        // Log the API call
        try {
            
            $requestData = [
                'query' => $request->query(), // GET parameters
                'body' => $request->except(['password']), // POST/PUT data
                'headers' => $request->headers->all(), // Request headers
            ];

            ApiLog::create([
                'user_id' => auth()->id(),
                'user_email' => auth()->user() ? auth()->user()->email : null,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'request_data' => $requestData,
                'response_code' => $response->status(),
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Log error silently
            \Log::error('API Logger Error: ' . $e->getMessage());
        }

        return $response;
    }
}