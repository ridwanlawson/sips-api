<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\AllResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Registrasi User Baru
    public function index(Request $request){
        $datas = User::all();
        return new AllResource(true, 'List Data User', $datas);
    }

    public function store(Request $request)
    {
        //define validation rules
        $validator = Validator::make($request->all(), [
            'username'  => 'required',
            'fullname'  => 'required',
            'fcba'      => 'required',
            'afdeling'  => 'nullable',
            'gangcode'  => 'nullable',
            'email'     => 'nullable|email',
            'password'  => 'required|min:8',
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        //create post
        $datas = User::create([
            'username'  => $request->username, 
            'fullname'  => $request->fullname,
            'fcba'      => $request->fcba,
            'afdeling'  => $request->afdeling,
            'gangcode'  => $request->gangcode,
            'email'     => $request->email,
            'password'  => $request->password,
        ]);

        //return response
        return new AllResource(true, 'User berhasil ditambahkan!', $datas);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);
    }

    // Login User
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json(['message' => 'Login successful', 'token' => $token, 'user' => $user], 200);
    }

    // Ambil Profil User
    public function profile(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}
