<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

Route::post('/RegisterNew', function (Request $request) {
    try {
        $validated = $request->validate([
            'name' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        // Check if user already exists
        if (ExpenseUser::where('name', $validated['name'])->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'User already exists'
            ]);
        }

        // Create new user
        $user = ExpenseUser::create([
            'name' => $validated['name'],
            // 'password' => Hash::make($validated['password']),
            'password' => $validated['password'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully',
            'user' => $user
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

//Register Route
Route::post('/register', function (Request $request) {
    // Validate the incoming request data
    $validatedData = $request->validate([
        'name' => 'required|string',
        'password' => 'required|string|min:6',
    ]);

    // Check if the username already exists
    $existingUser = DB::table('users')->where('name', $validatedData['name'])->first();

    if ($existingUser) {
        return response()->json([
            'response' => [
                'status' => false,
                'message' => 'Username already exists',
            ],
        ]);
    }

    // Insert the new user into the database
    $user = DB::table('users')->insertGetId([
        'name' => $validatedData['name'],
        'password' => Hash::make($validatedData['password']), // Hash the password for security
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

Route::post('/submitaccountsdetails', function (Request $request) {
    // Validate the incoming request data
    $validatedData = $request->validate([
        'user_id' => 'required|exists:users,id',
        'account_name' => 'required|string|max:255',
        'bank_name' => 'required|string|max:255',
        'remarks' => 'nullable|string',
        'purpose' => 'required|string|max:255',
        'date_time' => 'required|date',
        'account_type' => 'required|string|max:255',
        'balance' => 'required|numeric|min:0',
    ]);

    try {
        // Insert the account details into the database
        $account = DB::table('accounts')->insert([
            'user_id' => $validatedData['user_id'],
            'account_name' => $validatedData['account_name'],
            'bank_name' => $validatedData['bank_name'],
            'remarks' => $validatedData['remarks'],
            'purpose' => $validatedData['purpose'],
            'date_time' => $validatedData['date_time'],
            'account_type' => $validatedData['account_type'],
            'balance' => $validatedData['balance'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return a success response
        return response()->json([
            'response' => [
                'status' => true,
                'message' => 'Account details submitted successfully',
            ],
        ]);
    } catch (\Exception $e) {
        // Return an error response if the operation fails
        return response()->json([
            'response' => [
                'status' => false,
                'message' => 'Failed to submit account details',
                'error' => $e->getMessage(), // Optional: Include the error message for debugging
            ],
        ], 500);
    }
});

Route::get('/AccountsDetailsByDate/{user_id}/{date}', function ($user_id, $date) {
    // $date should be in 'YYYY-MM-DD' format

    $accounts = DB::table('accounts')
        ->where('user_id', $user_id)
        ->where('date_time', 'like', $date . '%')
        ->get();

    if ($accounts->isEmpty()) {
        return response()->json([
            'response' => [
                'status' => false,
                'message' => 'No data found'
            ],
            'data' => []
        ]);
    }

    return response()->json([
        'response' => [
            'status' => true,
            'message' => 'Data fetched successfully'
        ],
        'data' => $accounts
    ]);
});

Route::get('/RecentAccounts/{user_id}', function ($user_id) {
    $accounts = DB::table('accounts')
        ->where('user_id', $user_id)
        ->orderBy('created_at', 'desc')
        ->limit(15)
        ->get();

    if ($accounts->isEmpty()) {
        return response()->json([
            'response' => [
                'status' => false,
                'message' => 'No data found'
            ],
            'data' => []
        ]);
    }

    return response()->json([
        'response' => [
            'status' => true,
            'message' => 'Recent accounts fetched successfully'
        ],
        'data' => $accounts
    ]);
});

Route::get('/AllAccountsByUser/{user_id}', function ($user_id) {
    $accounts = DB::table('accounts')
        ->where('user_id', $user_id)
        ->orderBy('created_at', 'desc')
        ->get();

    if ($accounts->isEmpty()) {
        return response()->json([
            'response' => [
                'status' => false,
                'message' => 'No data found'
            ],
            'data' => []
        ]);
    }

    return response()->json([
        'response' => [
            'status' => true,
            'message' => 'Accounts fetched successfully'
        ],
        'data' => $accounts
    ]);
});

Route::post('/upload-file', function (Request $request) {
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'file' => 'required|file',
        'category' => 'nullable|string|max:100',
    ]);

    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('uploads', $fileName, 'public');

        // Save file info to DB
        $fileRecord = DB::table('files')->insertGetId([
            'user_id' => $validated['user_id'],
            'fileName' => $fileName,
            'filePath' => $filePath,
            'category' => $validated['category'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'File has been uploaded successfully',
            'file_id' => $fileRecord,
            'file_path' => $filePath
        ]);
    } else {
        return response()->json([
            'status' => false,
            'message' => 'No file uploaded'
        ], 400);
    }
});