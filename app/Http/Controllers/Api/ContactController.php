<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Store a new contact message.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $contact = Contact::create([
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'new'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Your message has been sent successfully',
            'data' => $contact
        ], 201);
    }

    /**
     * Get all contact messages (admin only).
     */
    public function index()
    {
        // Check if user is admin
        if (auth()->user()->role_id !== 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        $messages = Contact::latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $messages
        ]);
    }

    /**
     * Get a specific contact message (admin only).
     */
    public function show($id)
    {
        // Check if user is admin
        if (auth()->user()->role_id !== 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        $message = Contact::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $message
        ]);
    }

    /**
     * Update a contact message (admin only).
     */
    public function update(Request $request, $id)
    {
        // Check if user is admin
        if (auth()->user()->role_id !== 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:in_progress,resolved'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $message = Contact::findOrFail($id);
        $message->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Contact Status updated successfully'
        ]);
        
    }
    /**
     * Delete a contact message (admin only).
     */
    public function destroy($id)
    {
        // Check if user is admin
        if (auth()->user()->role_id !== 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        $message = Contact::findOrFail($id);
        $message->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Message deleted successfully'
        ]);
    }
} 