<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get all users with pagination
     */
    public function index(Request $request)
    {
        // $perPage = $request->get('per_page', 10);
        $users = User::with(['socialMedia', 'role'])
            ->where('role_id', 1)
        ->latest()
            ->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Get a specific user by ID
     */
    public function show($id)
    {
        $user = User::with(['socialMedia', 'role'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
    }

    /**
     * Update user status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,verified,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $user->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User status updated successfully',
            'data' => $user
        ]);
    }
} 