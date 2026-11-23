<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->roles()->sync($validated['roles']);

        return response()->json([
            'success' => true,
            'message' => 'Roles user berhasil diperbarui',
            'data' => [
                'user' => $user->load('roles'),
            ],
        ]);
    }
}
