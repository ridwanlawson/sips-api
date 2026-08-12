<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllResource;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\User;
use App\Services\StorageService;
use App\Traits\FileCleanupTrait;
use App\Traits\ImageOptimizerTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * @group Auth
 */
class AuthController extends Controller
{
    use FileCleanupTrait;
    use ImageOptimizerTrait;

    /**
     * Register User
     *
     * @unauthenticated
     *
     * @bodyParam username string required Username yang unik. Max: 75. Example: johndoe
     * @bodyParam fullname string required Nama lengkap. Max: 100. Example: John Doe
     * @bodyParam email string Email pengguna (opsional). Harus unik dan valid. Example: john@contoh.com
     * @bodyParam phone string Nomor telepon (opsional). 9–20 digit. Example: 08123456789
     * @bodyParam password string required Password minimal 8 karakter. Example: rahasia123
     * @bodyParam fcba string required Kode FCBA. Salah satu dari: MSE, MTE, PTE, MRE, DOM, CNT, HOF, ROF, COF. Example: MSE
     * @bodyParam afdeling string Nama afdeling. Example: AFD-01
     * @bodyParam gangcode string Kode gang. Example: PN011
     * @bodyParam level string Level pengguna. Salah satu dari: MGR, KSI, AST, MD1, MDP, KRA, KRT, KRP. Example: KRP
     * @bodyParam position string Jabatan. Salah satu dari: EM, KASIE, ASISTEN, MANDOR1, MD.PANEN, KR.AFDELING, KR.TRANS, KR.PANEN. Example: KR.PANEN
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
            'level' => 'nullable|max:10|regex:/^\S+$/',
            'position' => 'nullable|max:50|regex:/^\S+$/',
            'bantu' => 'nullable|max:20',
            'photo' => 'nullable|file|mimes:webp,jpg,jpeg,png|max:2048',
            'idkaryawan' => 'nullable|exists:sips_production.employee,fccode',
        ]);

        // Photo di-handle setelah semua cek unik (agar tidak ada file yatim)
        $photoPath = null;

        $emp = null;
        if ($request->filled('idkaryawan')) {
            $emp = Employee::select(
                'FCCODE',
                'FCNAME',
                'GANGCODE',
                'SECTIONNAME',
                'FCBA',
            )
                ->where('FCCODE', $request->idkaryawan)
                ->whereNull('DATETERMINATE')
                ->first();
            // Cara 2 (alternatif jika tidak pakai Rule::exists di atas):
            if (! $emp) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Data karyawan tidak ditemukan / sudah terminate.',
                    ],
                    422,
                );
            }
        }

        // Password default jika kosong
        $rawPassword = $request->filled('password')
            ? $request->password
            : '12345678';

        // Jika ada employee, override field; jika tidak, pakai dari request
        $finalUsername = $request->filled('username')
            ? $request->username
            : $emp->fccode ?? null;
        $finalFullname = $request->filled('fullname')
            ? strtoupper($request->fullname)
            : ($emp
                ? strtoupper($emp->fcname)
                : null); // ← uppercase
        $finalFcba = $request->filled('fcba')
            ? $request->fcba
            : $emp->fcba ?? null;
        $finalAfdeling = $request->filled('afdeling')
            ? $request->afdeling
            : $emp->sectionname ?? null;
        $finalGangcode = $request->filled('gangcode')
            ? $request->gangcode
            : $emp->gangcode ?? null;
        $finalLevel = $request->filled('level')
            ? strtoupper($request->level)
            : null; // ← uppercase
        $finalPosition = $request->filled('position')
            ? strtoupper($request->position)
            : null; // ← uppercase

        // 6) SAFETY CHECK: pastikan tetap memenuhi "required_without:idkaryawan"
        if (! $request->filled('idkaryawan')) {
            // Tanpa idkaryawan, field wajib dari request harus ada
            if (! $finalUsername || ! $finalFullname || ! $finalFcba) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Username, fullname, dan fcba wajib jika idkaryawan tidak diisi.',
                    ],
                    422,
                );
            }
        } else {
            // Dengan idkaryawan, pastikan mapping dari employee tidak kosong
            if (! $finalUsername || ! $finalFullname || ! $finalFcba) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Data karyawan tidak lengkap (FCCODE/FCNAME/FCBA).',
                    ],
                    422,
                );
            }
        }

        // 7) CEK UNIK USERNAME SETELAH OVERRIDE
        if (
            $finalUsername &&
            User::where('username', $finalUsername)->exists()
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Username sudah digunakan: '.$finalUsername,
                ],
                422,
            );
        }

        $this->uploadedFiles = [];

        try {
            // Upload photo baru dilakukan sekarang (setelah semua cek lolos)
            if ($request->hasFile('photo')) {
                $storage = app(StorageService::class);
                $folderPath = 'file/profile_photos';
                $relativePath = $this->optimizeAndSaveImage(
                    $request->file('photo'),
                    $folderPath,
                );
                $localAbsPath = public_path($relativePath);

                if ($storage->isDevOnline()) {
                    $devUrl = $storage->uploadToDev(
                        $localAbsPath,
                        $relativePath,
                    );
                    if ($devUrl) {
                        $photoPath = $devUrl;
                        @unlink($localAbsPath);
                    } else {
                        $photoPath = asset($relativePath);
                    }
                } else {
                    $photoPath = asset($relativePath);
                }

                $this->trackUploadedFile($photoPath);
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
                'level' => $finalLevel, // ← pakai variable baru
                'position' => $finalPosition, // ← pakai variable baru
                'idkaryawan' => $request->idkaryawan,
                'bantu' => $request->bantu,
                'photo' => $photoPath,
            ]);
        } catch (\Exception $e) {
            $this->cleanupUploadedFiles();

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat menyimpan data.',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }

        return new AllResource(true, 'User registered successfully', $data);
    }

    /**
     * Login User
     *
     * @unauthenticated
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)
            ->where('status', 'Y')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
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
        if (! Hash::check($request->current_password, $user->password)) {
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
     * Lupa Password - Kirim Link Reset
     *
     * @unauthenticated
     *
     * Mengirim email berisi link untuk mereset password apabila email terdaftar.
     * Jawaban selalu sama (terdaftar maupun tidak) demi keamanan.
     *
     * @bodyParam email string required Email pengguna yang terdaftar. Example: john@contoh.com
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Jika email terdaftar, link reset password telah dikirim."
     * }
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:100',
        ]);

        // Selalu kembalikan pesan yang sama agar tidak membocorkan keberadaan akun
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim link reset password.', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
        }

        return new AllResource(
            true,
            'Jika email terdaftar, link reset password telah dikirim.',
            null,
        );
    }

    /**
     * Reset Password
     *
     * @unauthenticated
     *
     * Mengatur password baru menggunakan email dan token dari link reset.
     *
     * @bodyParam email string required Email pengguna. Example: john@contoh.com
     * @bodyParam token string required Token dari link email reset.
     * @bodyParam password string required Password baru, minimal 8 karakter. Example: rahasia123
     * @bodyParam password_confirmation string required Konfirmasi password baru.
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Password berhasil diubah."
     * }
     * @response 400 scenario="invalid-token" {
     *  "success": false,
     *  "message": "Token tidak valid atau sudah kedaluwarsa."
     * }
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:100',
            'token' => 'required|string',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => \Illuminate\Support\Str::random(60),
                ])->save();
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return new AllResource(true, 'Password berhasil diubah.', null);
        }

        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid atau sudah kedaluwarsa.',
        ], 400);
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

        if (! $user) {
            return response()->json(
                [
                    'message' => 'User not found',
                ],
                404,
            );
        }

        return response()->json([
            'message' => 'User retrieved successfully',
            'data' => $user,
        ]);
    }

    /**
     * Update Profile User yang sedang login.
     *
     * Mengubah data profile pengguna yang sedang login.
     * Field yang bisa diubah: fullname, email, phone, afdeling, gangcode, level, position.
     *
     * @bodyParam fullname string optional Nama lengkap. Max: 100. Example: John Doe
     * @bodyParam email string optional Email. Harus unik (kecuali milik sendiri). Example: john@contoh.com
     * @bodyParam phone string optional Nomor telepon. 9–20 digit. Example: 08123456789
     * @bodyParam afdeling string optional Kode afdeling. Example: AFD-04
     * @bodyParam gangcode string optional Kode gang. Example: PN011
     * @bodyParam level string optional Level pengguna. Example: KRP
     * @bodyParam position string optional Jabatan. Example: KR.PANEN
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Profile berhasil diperbarui.",
     *  "data": {...}
     * }
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'fullname' => 'sometimes|nullable|max:100',
            'email' => 'sometimes|nullable|email|max:100|unique:users,email,'.Auth::id(),
            'phone' => 'sometimes|nullable|digits_between:9,20',
            'afdeling' => 'sometimes|nullable|max:20',
            'gangcode' => 'sometimes|nullable|max:20',
            'level' => 'sometimes|nullable|max:10',
            'position' => 'sometimes|nullable|max:50',
        ]);

        try {
            $user = Auth::user();
            $data = [];

            if ($request->filled('fullname')) {
                $data['fullname'] = strtoupper($request->fullname);
            }
            if ($request->filled('email')) {
                $data['email'] = $request->email;
            }
            if ($request->filled('phone')) {
                $data['phone'] = $request->phone;
            }
            if ($request->filled('afdeling')) {
                $data['afdeling'] = strtoupper($request->afdeling);
            }
            if ($request->filled('gangcode')) {
                $data['gangcode'] = strtoupper($request->gangcode);
            }
            if ($request->filled('level')) {
                $data['level'] = strtoupper($request->level);
            }
            if ($request->filled('position')) {
                $data['position'] = strtoupper($request->position);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data yang dikirim untuk diperbarui.',
                ], 422);
            }

            $data['updated_by'] = $user->username;

            $user->update($data);

            return new AllResource(true, 'Profile berhasil diperbarui.', $user->fresh());
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan database.',
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

    /**
     * Update Data User berdasarkan ID (dilakukan oleh user lain / admin).
     *
     * Mengubah data pengguna dengan menargetkan ID tertentu.
     * Field yang bisa diubah: username, fullname, email, phone, fcba, afdeling,
     * gangcode, idkaryawan, level, position, bantu, status, dan password.
     *
     * @urlParam id integer required ID pengguna yang akan diubah.
     *
     * @bodyParam username string optional Username unik. Example: johndoe
     * @bodyParam fullname string optional Nama lengkap. Max: 100. Example: John Doe
     * @bodyParam email string optional Email. Harus unik (kecuali milik sendiri). Example: john@contoh.com
     * @bodyParam phone string optional Nomor telepon. 9–20 digit. Example: 08123456789
     * @bodyParam fcba string optional Kode FCBA. Example: MSE
     * @bodyParam afdeling string optional Kode afdeling. Example: AFD-04
     * @bodyParam gangcode string optional Kode gang. Example: PN011
     * @bodyParam idkaryawan string optional Kode karyawan. Example: 06-930301
     * @bodyParam level string optional Level pengguna. Example: KRP
     * @bodyParam position string optional Jabatan. Example: KR.PANEN
     * @bodyParam bantu string optional Kode bantu. Example: Y
     * @bodyParam status string optional Status. Salah satu dari: Y, N. Example: Y
     * @bodyParam password string optional Password baru. Min: 8. Example: rahasia123
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Profile berhasil diperbarui.",
     *  "data": {...}
     * }
     */
    public function updateUserData(Request $request, $id)
    {
        $request->validate([
            'username' => 'sometimes|nullable|max:75|unique:users,username,'.$id,
            'fullname' => 'sometimes|nullable|max:100',
            'email' => 'sometimes|nullable|email|max:100|unique:users,email,'.$id,
            'phone' => 'sometimes|nullable|digits_between:9,20',
            'fcba' => 'sometimes|nullable|max:10',
            'afdeling' => 'sometimes|nullable|max:20',
            'gangcode' => 'sometimes|nullable|max:20',
            'idkaryawan' => 'sometimes|nullable|max:50',
            'level' => 'sometimes|nullable|max:10',
            'position' => 'sometimes|nullable|max:50',
            'bantu' => 'sometimes|nullable|max:20',
            'status' => 'sometimes|nullable|in:Y,N',
            'password' => 'sometimes|nullable|min:8',
        ]);

        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            $data = [];

            if ($request->filled('username')) {
                $data['username'] = $request->username;
            }
            if ($request->filled('fullname')) {
                $data['fullname'] = strtoupper($request->fullname);
            }
            if ($request->filled('email')) {
                $data['email'] = $request->email;
            }
            if ($request->filled('phone')) {
                $data['phone'] = $request->phone;
            }
            if ($request->filled('fcba')) {
                $data['fcba'] = $request->fcba;
            }
            if ($request->filled('afdeling')) {
                $data['afdeling'] = strtoupper($request->afdeling);
            }
            if ($request->filled('gangcode')) {
                $data['gangcode'] = strtoupper($request->gangcode);
            }
            if ($request->filled('idkaryawan')) {
                $data['idkaryawan'] = $request->idkaryawan;
            }
            if ($request->filled('level')) {
                $data['level'] = strtoupper($request->level);
            }
            if ($request->filled('position')) {
                $data['position'] = strtoupper($request->position);
            }
            if ($request->filled('bantu')) {
                $data['bantu'] = $request->bantu;
            }
            if ($request->filled('status')) {
                $data['status'] = $request->status;
            }
            if ($request->filled('password')) {
                $data['password'] = bcrypt($request->password);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data yang dikirim untuk diperbarui.',
                ], 422);
            }

            $data['updated_by'] = Auth::user()->username;

            $user->update($data);

            return new AllResource(true, 'Profile berhasil diperbarui.', $user->fresh());
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan database.',
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

    /**
     * Ganti Photo Profile User yang sedang login.
     *
     * Upload foto profile baru. Format: jpg, jpeg, png. Max: 2MB.
     *
     * @bodyParam photo file required File gambar JPG/PNG. Max: 2MB
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Photo profile berhasil diperbarui.",
     *  "data": {...}
     * }
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|file|mimes:webp,jpg,jpeg,png|max:2048',
        ]);

        try {
            $this->uploadedFiles = [];

            $user = Auth::user();

            $oldPhoto = $user->photo;

            $storage = app(StorageService::class);
            $folderPath = 'file/profile_photos';
            $relativePath = $this->optimizeAndSaveImage(
                $request->file('photo'),
                $folderPath,
            );
            $localAbsPath = public_path($relativePath);

            $photoPath = null;
            if ($storage->isDevOnline()) {
                $devUrl = $storage->uploadToDev(
                    $localAbsPath,
                    $relativePath,
                );
                if ($devUrl) {
                    $photoPath = $devUrl;
                    @unlink($localAbsPath);
                } else {
                    $photoPath = asset($relativePath);
                }
            } else {
                $photoPath = asset($relativePath);
            }

            $this->trackUploadedFile($photoPath);

            $user->update([
                'photo' => $photoPath,
                'updated_by' => $user->username,
            ]);

            // Hapus foto lama (guarded: hanya jika sudah tidak direferensikan DB)
            if ($oldPhoto) {
                $storage->deleteFile($oldPhoto);
            }

            return new AllResource(true, 'Photo profile berhasil diperbarui.', $user->fresh());
        } catch (QueryException $e) {
            $this->cleanupUploadedFiles();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan database.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            $this->cleanupUploadedFiles();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupload photo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ganti Photo Profile User berdasarkan ID (dilakukan oleh user lain / admin).
     *
     * Upload foto profile baru untuk pengguna target. Format: jpg, jpeg, png. Max: 2MB.
     *
     * @urlParam id integer required ID pengguna yang fotonya akan diubah.
     *
     * @bodyParam photo file required File gambar JPG/PNG. Max: 2MB
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Photo profile berhasil diperbarui.",
     *  "data": {...}
     * }
     */
    public function updateUserPhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|file|mimes:webp,jpg,jpeg,png|max:2048',
        ]);

        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            $this->uploadedFiles = [];

            $oldPhoto = $user->photo;

            $storage = app(StorageService::class);
            $folderPath = 'file/profile_photos';
            $relativePath = $this->optimizeAndSaveImage(
                $request->file('photo'),
                $folderPath,
            );
            $localAbsPath = public_path($relativePath);

            $photoPath = null;
            if ($storage->isDevOnline()) {
                $devUrl = $storage->uploadToDev(
                    $localAbsPath,
                    $relativePath,
                );
                if ($devUrl) {
                    $photoPath = $devUrl;
                    @unlink($localAbsPath);
                } else {
                    $photoPath = asset($relativePath);
                }
            } else {
                $photoPath = asset($relativePath);
            }

            $this->trackUploadedFile($photoPath);

            $user->update([
                'photo' => $photoPath,
                'updated_by' => Auth::user()->username,
            ]);

            // Hapus foto lama (guarded: hanya jika sudah tidak direferensikan DB)
            if ($oldPhoto) {
                $storage->deleteFile($oldPhoto);
            }

            return new AllResource(true, 'Photo profile berhasil diperbarui.', $user->fresh());
        } catch (QueryException $e) {
            $this->cleanupUploadedFiles();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan database.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            $this->cleanupUploadedFiles();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupload photo.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
                [$validated['status'], Auth::user()->username, $id],
            );

            // Ambil kembali data yang sudah diupdate
            $datas = User::findOrFail($id);

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Status Absensi berhasil diperbarui.',
                    'data' => $datas,
                ],
                200,
            );
        } catch (ModelNotFoundException $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Data Absensi tidak ditemukan.',
                ],
                404,
            );
        } catch (QueryException $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat mengupdate status absensi.',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Terjadi kesalahan pada sistem.',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
