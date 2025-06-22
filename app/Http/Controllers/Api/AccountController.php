<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function submit(Request $request)
    {
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
            DB::table('accounts')->insert([
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
            return response()->json([
                'response' => [
                    'status' => true,
                    'message' => 'Account details submitted successfully',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'status' => false,
                    'message' => 'Failed to submit account details',
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function accountsByDate($user_id, $date)
    {
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
    }

    public function recentAccounts($user_id)
    {
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
    }

    public function allAccountsByUser($user_id)
    {
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
    }
}
