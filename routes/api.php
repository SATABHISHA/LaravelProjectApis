<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Hello API Route
Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, API!']);
});

use App\Models\ExpenseUser;

Route::get('/users', function () {
    // Fetch all users from the database
    $users = ExpenseUser::all();

    // Return the users as a JSON response

     // Check if there is no data
     if ($users->isEmpty()) {
        return response()->json([
            'response' => [
                'status' => false,
                'message' => 'No data found'
            ],
            'data' => []
        ]);
    }

    // return response()->json($users);
    return response()->json([
        'response' => [
            'status' => true,
            'message' => 'Data fetched successfully'
        ],
        'data' => $users

    ]);
});

Route::post('/login', function(Request $request){
$credentials = $request->validate([
    'name' => 'required|string',
    'password' => 'required',
]);
$user = ExpenseUser::where('name', $credentials['name'])->first();

// if( $user && Hash::check($credentials['password'], $user->password) ){
    if ($user && $credentials['password'] === $user->password) {
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
});

Route::post('/register', function (Request $request) {
    // Validate the incoming request data
    $validatedData = $request->validate([
        'name' => 'required|string|unique:users,name',
        'password' => 'required|string|min:6',
    ]);

    // Insert the new user into the database
    $user = DB::table('User')->insertGetId([
        'name' => $validatedData['name'],
        // 'password' => Hash::make($validatedData['password']), // Hash the password for security
        'password' => $validatedData['password'], // Hash the password for security
    ]);

    // Return a success response
    return response()->json([
        'response' => [
            'status' => true,
            'message' => 'User registered successfully',
        ],
        'data' => [
            'user_id' => $user,
            'name' => $validatedData['name'],
        ],
    ]);
});