<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\ExpenseUser;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'name' => 'required|string',
            'password' => 'required',
        ]);
        $user = ExpenseUser::where('name', $credentials['name'])->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'response' => [
                    'status' => true,
                    'message' => 'Login successful'
                ],
                'data' => $user
            ]);
        } else {
            return response()->json([
                'response' => [
                    'status' => false,
                    'message' => 'Invalid credentials'
                ]
            ]);
        }
    }

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|unique:users,name',
            'password' => 'required|string|min:6',
        ]);

        $user = ExpenseUser::create([
            'name' => $validatedData['name'],
            'password' => Hash::make($validatedData['password']),
        ]);

        return response()->json([
            'response' => [
                'status' => true,
                'message' => 'User registered successfully',
            ],
            'data' => $user,
        ]);
    }
}
