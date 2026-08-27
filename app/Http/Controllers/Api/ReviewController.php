<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Display a listing of visible reviews.
     */
    public function index()
    {
        $reviews = Review::where('is_visible', true)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $reviews
        ]);
    }

    /**
     * Store a newly created review in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $review = Review::create([
            'name' => $request->name,
            'message' => $request->message,
            'is_visible' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Your review has been submitted successfully and is awaiting moderation.',
            'data' => $review
        ], 201);
    }
}
