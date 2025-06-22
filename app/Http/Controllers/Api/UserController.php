<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExpenseUser;

class UserController extends Controller
{
    public function index()
    {
        $users = ExpenseUser::all();
        if ($users->isEmpty()) {
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
            'data' => $users
        ]);
    }
}
