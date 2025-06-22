<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;

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

        $account = Account::create($validatedData);

        return response()->json([
            'response' => [
                'status' => true,
                'message' => 'Account details submitted successfully',
            ],
            'data' => $account,
        ]);
    }

    public function accountsByDate($user_id, $date)
    {
        $accounts = Account::where('user_id', $user_id)
            ->whereDate('date_time', $date)
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
        $accounts = Account::where('user_id', $user_id)
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
        $accounts = Account::where('user_id', $user_id)
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
