<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\BrowserDevices;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

#[Group('Authentication')]
class LogoutController extends Controller
{
    #[Endpoint(title: 'Logout', description: 'Revoke the current API token.')]
    public function __invoke(Request $request): Response
    {
        /** @var PersonalAccessToken|\Laravel\Sanctum\TransientToken|null $token */
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            BrowserDevices::logout($request);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
