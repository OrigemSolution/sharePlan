<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponder;
use App\Services\ApiResponseService;
use App\Models\User;
use App\Models\SocialMedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponder;

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'whatsapp_phone' => 'required|string|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',   
            'phone'=> 'nullable|string|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',       
            'email' => 'required|email|unique:users,email',
            'bank' => 'required|string',
            'account_name' => 'required|string',
            'account_no' => 'required|digits:10',
            'password' => 'required|min:6',
            'social_media' => 'required|array|min:3',
            'social_media.*.name' => 'required|string',
            'social_media.*.handle' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation failed', 422);
        }

        try {
            DB::beginTransaction();
    
            // Create user
            $user = User::create([
                'name' => $request->name,
                'whatsapp_phone' => $request->whatsapp_phone,
                'phone' => $request->phone,
                'email' => $request->email,
                'bank' => $request->bank,
                'account_name' => $request->account_name,
                'account_no' => $request->account_no,
                'password' => Hash::make($request->password),
                'status' => 'pending',
                'role_id' => 1
            ]);
    
            // Create social media handles
            foreach ($request->social_media as $social) {
                SocialMedia::create([
                    'user_id' => $user->id,
                    'name' => $social['name'],
                    'handle' => $social['handle']
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            $title = 'Welcome to Share Plan';
            $body = 'Your account is under review';
            $data = [
                'type' => 'single',
                'user_id' => $user->id
            ];

            // $this->pushNotificationService->sendToUser($user, $title, $body, $data);

            DB::commit();

            return $this->success([
                'user' => $user,
                'token' => $token
            ], 'Account has been successfully Created');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 'Registration failed', 500);
        }

    }

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|exists:users,email',
                'password' => 'required',
            ]);

            if($validator->fails())
            {
                return $this->validation($validator->errors()->first(), 'Invalid Email address or password', 422);
            }

            // Look for user
            $user = User::where(['email' => $request->email])->first();

            if($user)
            {
                if (Auth::attempt(['email' => $request->email, 'password' => $request->password])){
                    $user->tokens()->delete(); // Delete old tokens
                    return $this->success(['token' => $user->createToken('auth-token')->plainTextToken], 'Success', 200);
                }else{
                    return $this->error(null, 'Invalid Email address or Password', 401);
                }
            }else{
                return $this->error(null, 'Error logging in, user not found', 404);
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'Login failed', 500);
        }
    }

    public function getUser(Request $request)
    {
        // Eager load the social media handles and role
        $user = User::with(['socialMedia', 'role'])
                ->where('id', $request->user()->id)
                ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Format social media data
        $socialMedia = $user->socialMedia->map(function($item) {
            return [
                'platform' => $item->platform,
                'handle' => $item->handle
            ];
        });

        $responseData = [
            'id' => $user->id,
            'role_id' => $user->role_id,
            'role' => $user->role->name,
            'name' => $user->name,
            'whatsapp_phone' => $user->whatsapp_phone,
            'phone' => $user->phone,
            'email' => $user->email,
            'bank' => $user->bank,
            'account_name' => $user->account_name,
            'account_no' => $user->account_no,
            'status' => $user->status,
            'social_media' => $socialMedia, 
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Authenticated user',
            'data' => [
                'user' => $responseData,
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['status' => true, 'message' => 'Successfully logged out', 200]);
    }

    public function delete(Request $request)
    {
        $request->user()->delete();
        return $this->success(null, 'Account deleted successfully', 200);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);
        
        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation failed', 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error(null, 'The current password is incorrect', 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return $this->success(null, 'Password updated successfully', 200);
    }
    /**
     * Send OTP to user's email for password reset
     */
    public function sendForgotPasswordOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation failed', 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            // Generate 6-digit OTP
            $otp = random_int(100000, 999999);

            $user->update([
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(10),
            ]);

            // Send email using mailable
            Mail::to($user->email)->queue(new ForgotPasswordMail($otp));

            return $this->success(null, 'OTP sent to your email', 200);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'Unable to send OTP', 500);
        }
    }

    /**
     * Verify OTP sent to email and generate reset token
     */
    public function verifyForgotPasswordOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation failed', 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->otp || !$user->otp_expires_at) {
            return $this->error(null, 'OTP not found. Please request a new one.', 404);
        }

        if ($user->otp !== $request->otp) {
            return $this->error(null, 'Invalid OTP', 403);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return $this->error(null, 'OTP has expired', 403);
        }

        // Clear OTP fields
        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        // Generate reset token and cache it for 15 minutes
        $token = Str::random(60);
        Cache::put("password_reset_token:{$user->email}", $token, now()->addMinutes(15));

        return $this->success(['token' => $token], 'OTP verified', 200);
    }

    public function resetPassword(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8|confirmed',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $user = User::where('email', $request->email)->first();

            // Get the cached token
            $cachedToken = Cache::get("password_reset_token:{$user->email}");

            // Validate the token
            if (!$cachedToken || $cachedToken !== $request->token) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid or expired token',
                    'errors' => null
                ], 403);
            }

            // Update the user's password
            $user->update([
                'password' => bcrypt($request->password)
            ]);

            // Clear the cached token
            Cache::forget("password_reset_token:{$user->email}");

            return response()->json([
                'status' => true,
                'message' => 'Password reset successfully',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error resetting password: ' . $e->getMessage(),
                'errors' => null
            ], 500);
        }
    }

}
