<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Map;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @group Master
 * 
 * @subgroup Maps Geo JSON
 * 
 * @subgroupDescription Sub Group API untuk mengelola data Map GeoJSON 
 */
class MapController extends Controller
{
    /**
     * Memanggil data Map GeoJSON dari SIPS Mobile.
     * 
     * API ini digunakan untuk memanggil data Map GeoJSON secara keseluruhan. 
     * Tetapi jika ingin melakukan filter pada data yang dipanggil, 
     * buatlah parameter pada Url berdasarkan _**Query Parameter**_. data ini diurutkan berdasarkan type_map.
     * 
     * @authenticated
     * 
     * @queryParam type_map string Optional. Filter Maps by type (JALAN, BLOK, ANCAK). Example: JALAN
     * 
     * @response {
     *     "success": true,
     *     "message": "Maps retrieved successfully",
     *     "data": [
     *         {
     *             "id": 1,
     *             "geojson": {
     *                 "type": "FeatureCollection",
     *                 "features": []
     *             },
     *             "type_map": "JALAN",
     *             "created_by": 1,
     *             "updated_by": 1,
     *             "created_at": "2026-01-29T14:30:00.000000Z",
     *             "updated_at": "2026-01-29T14:30:00.000000Z",
     *             "creator": {
     *                 "id": 1,
     *                 "name": "John Doe",
     *                 "email": "john@example.com"
     *             },
     *             "updater": {
     *                 "id": 1,
     *                 "name": "John Doe",
     *                 "email": "john@example.com"
     *             }
     *         }
     *     ]
     * }
     * 
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to retrieve maps",
     *     "error": "Database connection failed"
     * }
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Jika filter type_map diberikan, ambil 1 data paling baru
            if (request('type_map')) {
                $map = Map::with(['creator', 'updater'])
                    ->select([
                        'id',
                        'type_map',
                        'geojson',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at'
                    ])
                    ->where('type_map', strtoupper(request('type_map')))
                    ->latest('id')
                    ->first();

                if (!$map) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Maps retrieved successfully',
                        'data' => []
                    ], 200);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Maps retrieved successfully',
                    'data' => [$map]
                ], 200);
            }

            // Jika type_map kosong, ambil data paling baru dari setiap kategori
            $maps = Map::with(['creator', 'updater'])
                ->select([
                    'id',
                    'type_map',
                    'geojson',
                    'created_by',
                    'updated_by',
                    'created_at',
                    'updated_at'
                ])
                ->whereIn('id', function($query) {
                    $query->selectRaw('MAX(id)')
                        ->from('maps')
                        ->groupBy('type_map');
                })
                ->orderBy('type_map')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Maps retrieved successfully',
                'data' => $maps
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve maps',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data Map GeoJSON ke dalam database SIPS Mobile.
     * 
     * @authenticated
     * 
     * @bodyParam geojson file optional The GeoJSON file to upload. Must be a valid JSON file.
     * @bodyParam geojson_data object optional The GeoJSON data structure (alternative to file upload). Example: {"type": "FeatureCollection", "features": []}
     * @bodyParam geojson_data.type string required_with:geojson_data The GeoJSON type. Example: FeatureCollection
     * @bodyParam geojson_data.features array required_with:geojson_data Array of GeoJSON features. Example: []
     * @bodyParam type_map string required Map type identifier. Must be one of: JALAN, BLOK, ANCAK. Example: JALAN
     * 
     * @response {
     *     "success": true,
     *     "message": "Map created successfully",
     *     "data": {
     *         "id": 1,
     *         "geojson": {
     *             "type": "FeatureCollection",
     *             "features": []
     *         },
     *         "type_map": "JALAN",
     *         "created_by": 1,
     *         "updated_by": 1,
     *         "created_at": "2026-01-29T14:30:00.000000Z",
     *         "updated_at": "2026-01-29T14:30:00.000000Z"
     *     }
     * }
     * 
     * @response 422 {
     *     "success": false,
     *     "message": "Validation failed",
     *     "errors": {
     *         "geojson": ["Either geojson file or geojson_data is required."],
     *         "type_map": ["The type_map field is required and must be one of: JALAN, BLOK, ANCAK."]
     *     }
     * }
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'geojson' => 'required_without:geojson_data|file|extensions:json,geojson',
            'geojson_data' => 'required_without:geojson|array',
            'geojson_data.type' => 'required_with:geojson_data|string',
            'geojson_data.features' => 'required_with:geojson_data|array',
            'type_map' => 'required|string|in:JALAN,BLOK,ANCAK',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $geoJsonData = null;

            // Jika upload file
            if ($request->hasFile('geojson')) {
                $content = file_get_contents($request->file('geojson')->getRealPath());
                $decoded = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid JSON format in uploaded file',
                        'error' => json_last_error_msg()
                    ], 422);
                }

                // ✅ SIMPAN SEBAGAI ARRAY, BUKAN STRING ENCODE
                $geoJsonData = $decoded;
            }
            // Jika kirim langsung JSON body
            elseif ($request->has('geojson_data')) {
                $geoJsonData = $request->geojson_data;
            }

            $map = Map::create([
                'geojson' => $geoJsonData, // 🔥 TANPA json_encode
                'type_map' => strtoupper($request->type_map),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $map->load(['creator', 'updater']);

            return response()->json([
                'success' => true,
                'message' => 'Map created successfully',
                'data' => $map
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create map',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Menampilkan data Map Geojson berdasarkan id Map Geojson dari SIPS Mobile.
     * 
     * @authenticated
     * 
     * @urlParam id integer required The ID of the map. Example: 1
     * 
     * @response {
     *     "success": true,
     *     "message": "Map retrieved successfully",
     *     "data": {
     *         "id": 1,
     *         "geojson": {
     *             "type": "FeatureCollection",
     *             "features": []
     *         },
     *         "type_map": "JALAN",
     *         "created_by": 1,
     *         "updated_by": 1,
     *         "created_at": "2026-01-29T14:30:00.000000Z",
     *         "updated_at": "2026-01-29T14:30:00.000000Z",
     *         "creator": {
     *             "id": 1,
     *             "name": "John Doe",
     *             "email": "john@example.com"
     *         },
     *         "updater": {
     *             "id": 1,
     *             "name": "John Doe",
     *             "email": "john@example.com"
     *         }
     *     }
     * }
     * 
     * @response 404 {
     *     "success": false,
     *     "message": "Map not found"
     * }
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $map = Map::with(['creator', 'updater'])
                ->select([
                    'id',
                    'type_map',
                    'geojson',
                    'created_by',
                    'updated_by',
                    'created_at',
                    'updated_at'
                ])
                ->find($id);

            if (!$map) {
                return response()->json([
                    'success' => false,
                    'message' => 'Map not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Map retrieved successfully',
                'data' => $map
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve map',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengubah data Map Geojson berdasarkan id Map Geojson.
     * 
     * @authenticated
     * 
     * @urlParam id integer required The ID of the map to update. Example: 1
     * @bodyParam geojson file optional The GeoJSON file to upload. Must be a valid JSON file.
     * @bodyParam geojson_data object optional The GeoJSON data structure (alternative to file upload). Example: {"type": "FeatureCollection", "features": []}
     * @bodyParam geojson_data.type string required_with:geojson_data The GeoJSON type. Example: FeatureCollection
     * @bodyParam geojson_data.features array required_with:geojson_data Array of GeoJSON features. Example: []
     * @bodyParam type_map string optional Map type identifier. Must be one of: JALAN, BLOK, ANCAK. Example: JALAN
     * 
     * @response {
     *     "success": true,
     *     "message": "Map updated successfully",
     *     "data": {
     *         "id": 1,
     *         "geojson": {
     *             "type": "FeatureCollection",
     *             "features": []
     *         },
     *         "type_map": "JALAN",
     *         "updated_by": 1,
     *         "updated_at": "2026-01-29T15:00:00.000000Z"
     *     }
     * }
     * 
     * @response 404 {
     *     "success": false,
     *     "message": "Map not found"
     * }
     * 
     * @response 422 {
     *     "success": false,
     *     "message": "Validation failed",
     *     "errors": {
     *         "type_map": ["The type_map must be one of: JALAN, BLOK, ANCAK."]
     *     }
     * }
     * 
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'geojson' => 'sometimes|file|extensions:json,geojson',
            'geojson_data' => 'sometimes|array',
            'geojson_data.type' => 'required_with:geojson_data|string',
            'geojson_data.features' => 'required_with:geojson_data|array',
            'type_map' => 'sometimes|string|in:JALAN,BLOK,ANCAK',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $map = Map::find($id);

            if (!$map) {
                return response()->json([
                    'success' => false,
                    'message' => 'Map not found'
                ], 404);
            }

            $updateData = [
                'updated_by' => auth()->id(),
            ];

            // ✅ Jika upload file baru
            if ($request->hasFile('geojson')) {
                $content = file_get_contents($request->file('geojson')->getRealPath());
                $decoded = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid JSON format in uploaded file',
                        'error' => json_last_error_msg()
                    ], 422);
                }

                $updateData['geojson'] = $decoded; // ✅ TANPA json_encode
            } elseif ($request->has('geojson_data')) {
                $updateData['geojson'] = $request->geojson_data; // ✅ TANPA json_encode
            }

            if ($request->has('type_map')) {
                $updateData['type_map'] = strtoupper($request->type_map);
            }

            $map->update($updateData);
            $map->load(['creator', 'updater']);

            return response()->json([
                'success' => true,
                'message' => 'Map updated successfully',
                'data' => $map
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update map',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Menghapus data Map Geojson berdasarkan id Map Geojson.
     * 
     * @authenticated
     * 
     * @urlParam id integer required The ID of the map to delete. Example: 1
     * 
     * @response {
     *     "success": true,
     *     "message": "Map deleted successfully",
     *     "data": {
     *         "id": 1,
     *         "geojson": {
     *             "type": "FeatureCollection",
     *             "features": []
     *         },
     *         "type_map": "JALAN"
     *     }
     * }
     * 
     * @response 404 {
     *     "success": false,
     *     "message": "Map not found"
     * }
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $map = Map::find($id);

            if (!$map) {
                return response()->json([
                    'success' => false,
                    'message' => 'Map not found'
                ], 404);
            }

            $map->delete();

            return response()->json([
                'success' => true,
                'message' => 'Map deleted successfully',
                'data' => $map
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete map',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
