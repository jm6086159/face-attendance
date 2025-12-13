<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Employee;
use App\Models\User;

class MobileAuthController extends Controller
{
    /**
     * POST /api/mobile/login
     * Secure mobile login: requires password and either email or emp_code.
     * Validates against Employee records (hashed password).
     */
    public function login(Request $request)
    {
        $data = $request->only(['email', 'emp_code', 'password']);

        $validator = Validator::make($data, [
            'email' => 'nullable|email',
            'emp_code' => 'nullable|string|max:50',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login payload',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $employee = null;
        if (!empty($data['email'])) {
            $employee = Employee::where('email', $data['email'])->first();
        }
        if (!$employee && !empty($data['emp_code'])) {
            $employee = Employee::where('emp_code', $data['emp_code'])->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        }

        if (empty($employee->password) || !Hash::check($data['password'], $employee->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = Str::random(60);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'email' => $employee->email,
                'department' => $employee->department,
                'course' => $employee->course,
                'position' => $employee->position,
                'emp_code' => $employee->emp_code,
            ],
        ]);
    }
}
