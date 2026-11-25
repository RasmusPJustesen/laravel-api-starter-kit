<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\PasswordConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordConfirmationController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($data['password'], $request->user()->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => [__('auth.password')]]);
        }

        PasswordConfirmation::confirm($request);

        return response()->noContent();
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['confirmed' => PasswordConfirmation::confirmed($request)]);
    }
}
