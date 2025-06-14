<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Service::with(['slots' => function($query) {
                $query->select('id', 'service_id', 'user_id')
                    ->with('creator:id,name');
            }])
            ->withCount(['slots as total_paid_members' => function ($query) {
                $query->join('slot_members', 'slots.id', '=', 'slot_members.slot_id')
                    ->where('slot_members.payment_status', 'paid');
            }]);

        // If user is not authenticated, only show services with creators
        if (!auth()->check()) {
            $query->whereHas('slots', function($query) {
                $query->whereNotNull('user_id');
            });
        }

        $services = $query->latest()
            ->get()
            ->map(function ($service) {
                // Get the first slot's creator as the service creator
                $creator = $service->slots->first()?->creator;
                unset($service->slots); // Remove slots from response
                $service->creator = $creator;
                return $service;
            });

        return response()->json([
            'status' => 'success',
            'data' => $services
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'price' => 'required|numeric|min:0',
            'max_members' => 'required|integer|min:1',
            'duration' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle logo upload
        $logoPath = $request->file('logo')->store('services', 'public');

        $service = Service::create([
            'name' => $request->name,
            'description' => $request->description,
            'logo' => $logoPath,
            'price' => $request->price,
            'max_members' => $request->max_members,
            'duration' => $request->duration,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Service created successfully',
            'data' => $service
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $service = Service::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $service
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $service = Service::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'price' => 'required|numeric|min:0',
            'max_members' => 'required|integer|min:1',
            'duration' => 'required|string',
            'is_active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle logo update if new file is uploaded
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($service->logo) {
                Storage::disk('public')->delete($service->logo);
            }
            $logoPath = $request->file('logo')->store('services', 'public');
            $service->logo = $logoPath;
        }

        $service->update([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'max_members' => $request->max_members,
            'duration' => $request->duration,
            'is_active' => $request->is_active
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Service updated successfully',
            'data' => $service
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $service = Service::findOrFail($id);

        // Delete the logo file
        if ($service->logo) {
            Storage::disk('public')->delete($service->logo);
        }

        $service->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Service deleted successfully'
        ]);
    }
}
