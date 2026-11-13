<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data dashboard',
            'data' => [
                'user' => $user,
            ],
        ], 200);
    }
}