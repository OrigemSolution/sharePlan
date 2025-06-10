<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Slot;
use App\Models\Service;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $slots = Slot::with(['service', 'members'])
                ->where('user_id', auth()->id())
                ->latest()
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $slots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching slots',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_id' => 'required|exists:services,id',
                'duration' => 'required|integer|min:1',
                'expires_at' => 'required|date|after:today'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if service exists and is active
            $service = Service::findOrFail($request->service_id);
            if (!$service->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected service is not active'
                ], 400);
            }

            $slot = Slot::create([
                'service_id' => $request->service_id,
                'user_id' => auth()->id(),
                'current_members' => 1,
                'duration' => $request->duration,
                'status' => 'open',
                'expires_at' => $request->expires_at
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Slot created successfully',
                'data' => $slot
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $slot = Slot::with(['service', 'members'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $slot
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $slot = Slot::where('user_id', auth()->id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'duration' => 'sometimes|required|integer|min:1',
                'status' => 'sometimes|required|in:open,completed,cancelled',
                'expires_at' => 'sometimes|required|date|after:today'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Only allow updates if slot is not completed
            if ($slot->status === 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot update a completed slot'
                ], 400);
            }

            $slot->update($request->only(['duration', 'status', 'expires_at']));

            return response()->json([
                'status' => 'success',
                'message' => 'Slot updated successfully',
                'data' => $slot
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $slot = Slot::where('user_id', auth()->id())->findOrFail($id);

            // Only allow deletion if slot has no members and is not completed
            if ($slot->current_members > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete slot with members'
                ], 400);
            }

            if ($slot->status === 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete a completed slot'
                ], 400);
            }

            $slot->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Slot deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
