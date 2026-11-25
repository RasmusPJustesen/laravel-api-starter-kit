<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\BrowserDevices;
use App\Support\Auth\Credentials;
use App\Support\Auth\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
    private function opaque(string $type, string $id): string
    {
        return $type.':'.hash_hmac('sha256', $id, config('app.key'));
    }

    /** @return list<array{key: int|string, kind: string, device_id?: string|null, data: array<string, mixed>}> */
    private function records(Request $request): array
    {
        $user = $request->user();
        /** @var PersonalAccessToken|\Laravel\Sanctum\TransientToken|null $3 */
        $current = $user->currentAccessToken();
        $items = [];
        $tokens = $user->tokens()->get();

        foreach ($tokens as $token) {
            $minutes = config('sanctum.expiration');
            if (($minutes && $token->created_at->lte(now()->subMinutes($minutes))) || ($token->expires_at && $token->expires_at->isPast())) {
                continue;
            }

            $items[] = ['key' => $token->getKey(), 'device_id' => $token->device_id, 'kind' => 'token', 'data' => [
                'id' => $this->opaque('token', $token->device_id ?? (string) $token->getKey()),
                'name' => $token->name,
                'authentication_type' => 'bearer',
                'client_id' => $token->client_id,
                'ip_address' => $token->ip_address,
                'user_agent' => $token->user_agent,
                'last_active_at' => $token->last_used_at?->toISOString(),
                'is_current' => $current instanceof PersonalAccessToken && $current->is($token),
            ]];
        }

        foreach (BrowserDevices::active(BrowserDevices::table()->where('user_id', $user->getKey()))->get() as $device) {
            $items[] = ['key' => $device->id, 'kind' => 'browser', 'data' => [
                'id' => $this->opaque('browser', $device->id),
                'name' => $device->name,
                'authentication_type' => 'cookie',
                'client_id' => $device->client_id,
                'ip_address' => $device->ip_address,
                'user_agent' => $device->user_agent,
                'last_active_at' => Carbon::parse($device->last_active_at)->toISOString(),
                'is_current' => ! ($current instanceof PersonalAccessToken) && $request->hasSession() && $request->session()->get('browser_device_id') === $device->id,
            ]];
        }

        if (config('session.driver') === 'database') {
            $sessions = Credentials::sessions()->where('user_id', $user->getKey())
                ->whereNotIn('id', BrowserDevices::table()->whereNotNull('session_id')->select('session_id'))->where('last_activity', '>', now()->subMinutes(config('session.lifetime'))->timestamp)->get();

            foreach ($sessions as $session) {
                $items[] = ['key' => $session->id, 'kind' => 'session', 'data' => [
                    'id' => $this->opaque('session', $session->id),
                    'name' => 'Browser',
                    'authentication_type' => 'cookie',
                    'client_id' => null,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_active_at' => Carbon::createFromTimestamp($session->last_activity)->toISOString(),
                    'is_current' => ! ($current instanceof PersonalAccessToken) && $request->hasSession() && $request->session()->getId() === $session->id,
                ]];
            }
        }

        return $items;
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['data' => array_map(fn ($item) => ['type' => 'sessions', 'id' => $item['data']['id'], 'attributes' => array_diff_key($item['data'], ['id' => true]) + UserAgent::describe($item['data']['user_agent'])], $this->records($request))]);
    }

    public function destroy(Request $request, string $session): \Illuminate\Http\Response
    {
        $record = collect($this->records($request))->first(fn ($item) => hash_equals($item['data']['id'], $session));
        abort_unless($record !== null, 404);
        abort_if($record['data']['is_current'], 409, __('Use logout to end the current session.'));

        DB::transaction(function () use ($request, $record): void {
            $user = User::query()->lockForUpdate()->whereKey($request->user()->getKey())->firstOrFail();
            if ($record['kind'] === 'token') {
                $query = $user->tokens();
                if (! empty($record['device_id'])) {
                    $query->where('device_id', $record['device_id']);
                } else {
                    $query->whereKey($record['key']);
                }
                $query->delete();
            } elseif ($record['kind'] === 'browser') {
                $device = BrowserDevices::table()->where('user_id', $request->user()->getKey())->where('id', $record['key']);
                $sessionId = $device->value('session_id');
                $device->delete();
                if ($sessionId && config('session.driver') === 'database') {
                    Credentials::sessions()->where('user_id', $request->user()->getKey())->where('id', $sessionId)->delete();
                }
            } else {
                Credentials::sessions()->where('user_id', $request->user()->getKey())->where('id', $record['key'])->delete();
                $request->user()->forceFill(['remember_token' => Str::random(60)])->save();
            }
        });

        return response()->noContent();
    }

    public function others(Request $request): \Illuminate\Http\Response
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->whereKey($request->user()->getKey())->firstOrFail();
            Credentials::revokeOthers($request, $user);
        });

        return response()->noContent();
    }

    public function update(Request $request, string $session): \Illuminate\Http\Response
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $record = collect($this->records($request))->first(fn ($item) => hash_equals($item['data']['id'], $session));
        abort_unless($record !== null, 404);
        if ($record['kind'] === 'browser') {
            BrowserDevices::table()->where('user_id', $request->user()->getKey())->where('id', $record['key'])->update(['name' => $data['name']]);
        } else {
            abort_unless($record['kind'] === 'token', 422, __('Legacy browser sessions cannot be renamed.'));
            $query = $request->user()->tokens();
            if (! empty($record['device_id'])) {
                $query->where('device_id', $record['device_id']);
            } else {
                $query->whereKey($record['key']);
            }
            $query->update(['name' => $data['name']]);
        }

        return response()->noContent();
    }
}
