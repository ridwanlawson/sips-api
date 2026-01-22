<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Illuminate\Http\Request;

/**
 * @hideFromAPIDocumentation
 */
class ApiLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ApiLog::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by method
        if ($request->has('method')) {
            $query->where('method', $request->method);
        }

        // Filter by endpoint
        if ($request->has('endpoint')) {
            $query->where('endpoint', 'LIKE', '%' . $request->endpoint . '%');
        }

        // Order by latest first
        $query->orderBy('created_at', 'desc');

        // Paginate results
        $logs = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'status' => 'success',
            'data' => $logs,
            'message' => 'API logs retrieved successfully'
        ]);
    }

    public function show($id)
    {
        $log = ApiLog::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $log,
            'message' => 'API log detail retrieved successfully'
        ]);
    }
}
