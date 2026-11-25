<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Auth\BrowserDevices;
use App\Support\Auth\LoginChallenge;
use App\Support\Auth\TwoFactor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SpaAuthController extends Controller
{
    public function login(LoginRequest $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
    {
        return DB::transaction(function () use ($request) {
            $user = User::query()->where('email', $request->string('email'))->lockForUpdate()->first();
            $hash = $user?->getAuthPassword() ?? '$2y$12$OoT8jJofGjVA6q9C3/FvNeplQTQg0JAeDakKw2oy4vBvK/eAEksCW';
            if (! Hash::check($request->string('password'), $hash) || ! $user) {
                throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
            }

            if (TwoFactor::enabled($user)) {
                return response()->json(LoginChallenge::issue($user, $request, 'stateful'), 202);
            }

            BrowserDevices::login($request, $user, $request->boolean('remember'), (string) $request->string('device_name', 'Browser'));

            return response()->noContent();
        });
    }

    public function register(RegisterRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = User::create($request->validated());
        event(new Registered($user));
        BrowserDevices::login($request, $user);

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
