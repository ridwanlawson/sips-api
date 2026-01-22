<?php

namespace App\Http\Controllers\Api;

use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Employee;
use App\Models\BusinessUnit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\AllResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @group Auth
 * 
 */
class AuthController extends Controller
{
    /**
     * Register User
     * @unauthenticated
     * @bodyParam username string required Username yang unik. Max: 75. Example: johndoe
     * @bodyParam fullname string required Nama lengkap. Max: 100. Example: John Doe
     * @bodyParam email string Email pengguna (opsional). Harus unik dan valid. Example: john@contoh.com
     * @bodyParam phone string Nomor telepon (opsional). 9–20 digit. Example: 08123456789
     * @bodyParam password string required Password minimal 8 karakter. Example: rahasia123
     * @bodyParam fcba string required Kode FCBA. Salah satu dari: MSE, MTE, PTE, MRE, DOM, CNT, HOF, ROF, COF. Example: MSE
     * @bodyParam afdeling string Nama afdeling. Example: AFD-01
     * @bodyParam gangcode string Kode gang. Example: PN011
     * @bodyParam level string Level pengguna. Salah satu dari: MGR, AST, MD1, MDP, KRP, KRT. Example: MGR
     * @bodyParam position string Jabatan. Salah satu dari: EM, ASISTEN, MANDOR1, MD.PANEN, KR.PANEN, KR.TRANS. Example: MANDOR1
     * @bodyParam photo file File gambar JPG/PNG (opsional). Max: 2MB
     * @bodyParam idkaryawan string Kode karyawan dari tabel employee pada SIPS PRODUCTION. Example: 06-930301-241213-0731
     */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'nullable|max:75|unique:users,username|required_without:idkaryawan',
            'fullname' => 'required_without:idkaryawan|nullable|max:100',
            'email' => 'nullable|email|unique:users,email|max:100',
            'phone' => 'nullable|digits_between:9,20',
            'password' => 'nullable|min:8',
            'fcba' => 'required_without:idkaryawan|nullable|max:10',
            'afdeling' => 'nullable|max:20',
            'gangcode' => 'nullable|max:20',
            'level' => 'nullable|max:10',
            'position' => 'nullable|max:50',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'idkaryawan' => 'nullable|exists:sips_production.employee,fccode',
        ]);

        // Inisialisasi variabel path photo (default null jika tidak ada file)
        $photoPath = null;

        // Jika ada file photo yang diunggah
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $photoName = time() . '_' . $photo->getClientOriginalName();
            $photo->move(public_path('file/profile_photos'), $photoName); // Simpan di public/profile_photos
            $photoPath = 'file/profile_photos/' . $photoName; // Path yang disimpan di database
        }

        $photoPath = $photoPath ? asset($photoPath) : null;

        $emp = null;
        if ($request->filled('idkaryawan')) {
            $emp = Employee::select('FCCODE', 'FCNAME', 'GANGCODE', 'SECTIONNAME', 'FCBA')
                ->where('FCCODE', $request->idkaryawan)
                ->whereNull('DATETERMINATE')
                ->first();
            // Cara 2 (alternatif jika tidak pakai Rule::exists di atas):
            if (!$emp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan / sudah terminate.'
                ], 422);
            }
        }

        // Password default jika kosong
        $rawPassword = $request->filled('password') ? $request->password : '12345678';

        // Jika ada employee, override field; jika tidak, pakai dari request
        $finalUsername = $request->filled('username') ? $request->username : ($emp->fccode ?? null);
        $finalFullname = $request->filled('fullname') ? $request->fullname : ($emp->fcname ?? null);
        $finalFcba     = $request->filled('fcba')     ? $request->fcba     : ($emp->fcba ?? null);
        $finalAfdeling = $request->filled('afdeling') ? $request->afdeling : ($emp->sectionname ?? null);
        $finalGangcode = $request->filled('gangcode') ? $request->gangcode : ($emp->gangcode ?? null);

        // 6) SAFETY CHECK: pastikan tetap memenuhi "required_without:idkaryawan"
        if (!$request->filled('idkaryawan')) {
            // Tanpa idkaryawan, field wajib dari request harus ada
            if (!$finalUsername || !$finalFullname || !$finalFcba) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username, fullname, dan fcba wajib jika idkaryawan tidak diisi.'
                ], 422);
            }
        } else {
            // Dengan idkaryawan, pastikan mapping dari employee tidak kosong
            if (!$finalUsername || !$finalFullname || !$finalFcba) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak lengkap (FCCODE/FCNAME/FCBA).'
                ], 422);
            }
        }

        // 7) CEK UNIK USERNAME SETELAH OVERRIDE
        if ($finalUsername && \App\Models\User::where('username', $finalUsername)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Username sudah digunakan: ' . $finalUsername
            ], 422);
        }

        $data = User::create([
            'username' => $finalUsername,
            'fullname' => $finalFullname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($rawPassword),
            'fcba' => $finalFcba,
            'afdeling' => $finalAfdeling,
            'gangcode' => $finalGangcode,
            'level' => $request->level,
            'position' => $request->position,
            'idkaryawan' => $request->idkaryawan,
            'photo' => $photoPath, // Simpan path photo jika ada
        ]);

        return new AllResource(true, 'User registered successfully', $data);
    }

    /**
     * Login User
     * @unauthenticated
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Ambil FCCOMPANYCODE dari BusinessUnit berdasarkan FCBA user
        $businessUnit = BusinessUnit::where('FCCODE', $user->fcba)->first();
        $user->fccompanycode = $businessUnit->fccompanycode ?? null;

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Logout User 
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Ganti Password User 
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        $user = $request->user(); // Mendapatkan data pengguna yang login

        // Verifikasi password lama
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        // Update password baru
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password has been changed successfully.',
        ]);
    }

    /**
     * Memanggil User berdasarkan ID
     * 
     * @urlParam id integer required ID pengguna.
     */
    public function getUser($id)
    {
        // Cari pengguna berdasarkan ID
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'message' => 'User retrieved successfully',
            'data' => $user,
        ]);
    }

    /**
     * Aktif atau Nonaktif Status User berdasarkan id User.
     *
     * @urlParam id integer required ID User.
     */
    public function updateStatus(Request $request, string $id)
    {
        // Validasi input status yang diizinkan
        $validated = $request->validate([
            'status' => 'required|string|in:Y,N',
        ]);

        try {
            // Cari data berdasarkan ID
            $datas = User::findOrFail($id);

            // Update status menggunakan query manual (konsisten dengan update lain)
            DB::update(
                "UPDATE \"SIPSMOBILE\".\"USERS\" \n SET \"STATUS\" = ?, \"UPDATED_BY\" = ?, \"UPDATED_AT\" = SYSDATE\n WHERE \"ID\" = ?",
                [$validated['status'], Auth::user()->username, $id]
            );

            // Ambil kembali data yang sudah diupdate
            $datas = User::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Status Absensi berhasil diperbarui.',
                'data' => $datas,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Absensi tidak ditemukan.',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate status absensi.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
