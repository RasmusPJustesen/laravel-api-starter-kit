<?php

namespace App\Support;

use App\Enums\ApiErrorCode;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Facades\Route;

final class ApiContract
{
    /**
     * @param  array<string, Type>  $properties
     * @param  list<string>|null  $required
     */
    private static function object(array $properties, ?array $required = null): ObjectType
    {
        $type = new ObjectType;
        foreach ($properties as $key => $property) {
            $type->addProperty($key, $property);
        }

        return $type->setRequired($required ?? array_keys($properties));
    }

    private static function arrayOf(Type $item): ArrayType
    {
        return (new ArrayType)->setItems($item);
    }

    private static function success(Operation $operation, int $status, Schema|Reference|null $schema = null): void
    {
        $operation->responses = array_values(array_filter($operation->responses ?? [], function ($response) use ($status): bool {
            $resolved = $response instanceof Reference ? $response->resolve() : $response;

            return (int) ($resolved->code ?? 0) !== $status;
        }));
        $response = Response::make($status)->setDescription($status === 204 ? 'No content' : 'Successful response');
        if ($schema) {
            $response->setContent('application/json', $schema);
        }
        $operation->addResponse($response);
    }

    public static function transform(OpenApi $api): void
    {
        $components = $api->components;
        $errorDetail = $components->addSchema('ApiErrorDetail', Schema::fromType(self::object([
            'status' => new StringType,
            'code' => (new StringType)->enum(array_column(ApiErrorCode::cases(), 'value')),
            'detail' => new StringType,
            'source' => self::object(['parameter' => new StringType]),
        ], ['status', 'code', 'detail'])));
        $error = $components->addSchema('ApiErrorResponse', Schema::fromType(self::object([
            'message' => new StringType,
            'errors' => (new ObjectType)->additionalProperties(self::arrayOf(new StringType)),
            'error_details' => self::arrayOf($errorDetail),
        ], ['message', 'error_details'])));
        $token = $components->addSchema('AccessTokenResponse', Schema::fromType(self::object(['token' => new StringType])));
        $challenge = $components->addSchema('TwoFactorChallengeResponse', Schema::fromType(self::object([
            'two_factor_required' => (new BooleanType)->const(true),
            'challenge_token' => new StringType,
        ])));
        $sessionAttributes = self::object([
            'name' => new StringType,
            'authentication_type' => (new StringType)->enum(['bearer', 'cookie']),
            'client_id' => (new StringType)->nullable(true),
            'ip_address' => (new StringType)->nullable(true),
            'user_agent' => (new StringType)->nullable(true),
            'device' => (new StringType)->nullable(true),
            'browser' => (new StringType)->nullable(true),
            'platform' => (new StringType)->nullable(true),
            'last_active_at' => (new StringType)->format('date-time')->nullable(true),
            'is_current' => new BooleanType,
        ]);
        $session = $components->addSchema('DeviceSession', Schema::fromType(self::object([
            'type' => (new StringType)->const('sessions'),
            'id' => new StringType,
            'attributes' => $sessionAttributes,
        ])));
        $sessions = $components->addSchema('DeviceSessionsResponse', Schema::fromType(self::object(['data' => self::arrayOf($session)])));

        $mode = config('starter-kit.authentication');
        $bearer = $mode !== 'stateful';
        $cookie = $mode !== 'stateless';
        // Replace the legacy global bearer-only strategy with per-operation alternatives.
        $api->security = [];
        $components->securitySchemes = [];
        if ($bearer) {
            $components->addSecurityScheme('bearerAuth', SecurityScheme::http('bearer')->as('bearerAuth'));
        }
        if ($cookie) {
            $components->addSecurityScheme('cookieAuth', SecurityScheme::apiKey('cookie', config('session.cookie'))->as('cookieAuth'));
            $components->addSecurityScheme('csrfHeader', SecurityScheme::apiKey('header', 'X-XSRF-TOKEN')->as('csrfHeader')->setDescription('Obtain the XSRF-TOKEN cookie from GET /sanctum/csrf-cookie and send its decoded value.'));
        }

        $routes = Route::getRoutes()->getRoutes();
        $errors = [400 => 'Bad request', 401 => 'Unauthenticated', 403 => 'Forbidden or additional verification required', 404 => 'Not found', 406 => 'Unsupported API version', 409 => 'Conflict', 419 => 'CSRF token mismatch', 422 => 'Validation failed', 429 => 'Rate limited', 500 => 'Server error', 503 => 'Service unavailable'];

        foreach ($api->paths as $path) {
            foreach ($path->operations as $operation) {
                $uri = ltrim($path->path, '/');
                $method = strtoupper($operation->method);
                $route = collect($routes)->first(fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true));
                $middleware = $route?->gatherMiddleware() ?? [];
                $protected = in_array('auth:sanctum', $middleware, true);
                $spa = str_starts_with($uri, 'spa/');
                $mutating = ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
                $operation->security = [];
                if ($protected) {
                    if ($bearer) {
                        $operation->addSecurity(new SecurityRequirement(['bearerAuth' => []]));
                    }
                    if ($cookie && $uri !== 'token/refresh') {
                        $requirement = ['cookieAuth' => []];
                        if ($mutating) {
                            $requirement['csrfHeader'] = [];
                        }
                        $operation->addSecurity(new SecurityRequirement($requirement));
                    }
                } elseif ($spa && $cookie && $mutating) {
                    $operation->addSecurity(new SecurityRequirement(['csrfHeader' => []]));
                }

                $operation->addParameters([
                    Parameter::make('X-Client', 'header')->setSchema(Schema::fromType(new StringType))->description('Registered frontend identifier; not an authentication credential.'),
                    Parameter::make('Accept-Language', 'header')->setSchema(Schema::fromType(new StringType))->description('Preferred supported language.'),
                ]);

                // Preserve inferred error statuses, but use one shared response schema.
                $existing = [];
                foreach ($operation->responses ?? [] as $response) {
                    $resolved = $response instanceof Reference ? $response->resolve() : $response;
                    $code = (int) ($resolved->code ?? 0);
                    if ($code >= 400) {
                        $existing[$code] = $resolved;
                    }
                }
                $statuses = array_unique(array_merge(array_keys($existing), [429, 500],
                    $protected ? [401, 403] : [],
                    $mutating ? [422] : [],
                    $cookie && $mutating && ($protected || $spa) ? [419] : [],
                ));
                foreach ($statuses as $status) {
                    $operation->responses = array_values(array_filter($operation->responses ?? [], function ($response) use ($status): bool {
                        $resolved = $response instanceof Reference ? $response->resolve() : $response;

                        return (int) ($resolved->code ?? 0) !== $status;
                    }));
                    $response = Response::make($status)->setDescription($errors[$status] ?? 'Request failed')->setContent('application/json', $error);
                    foreach ($existing[$status]->headers ?? [] as $name => $header) {
                        $response->addHeader($name, $header);
                    }
                    $operation->addResponse($response);
                }

                if ($method === 'POST' && $uri === 'login') {
                    self::success($operation, 200, $token);
                    self::success($operation, 202, $challenge);
                } elseif ($method === 'POST' && $uri === 'spa/login') {
                    self::success($operation, 204);
                    self::success($operation, 202, $challenge);
                } elseif ($method === 'POST' && in_array($uri, ['two-factor-challenge', 'token/refresh'], true)) {
                    self::success($operation, 200, $token);
                } elseif ($method === 'POST' && $uri === 'spa/two-factor-challenge') {
                    self::success($operation, 204);
                } elseif ($method === 'GET' && $uri === 'user/sessions') {
                    self::success($operation, 200, $sessions);
                } elseif ($method === 'GET' && $uri === 'user/two-factor-authentication') {
                    self::success($operation, 200, Schema::fromType(self::object(['enabled' => new BooleanType, 'pending' => new BooleanType])));
                } elseif ($method === 'GET' && $uri === 'user/two-factor-secret-key') {
                    self::success($operation, 200, Schema::fromType(self::object(['secret' => new StringType, 'url' => new StringType])));
                } elseif ($method === 'GET' && $uri === 'user/two-factor-qr-code') {
                    self::success($operation, 200, Schema::fromType(self::object(['svg' => new StringType, 'url' => new StringType])));
                } elseif ($uri === 'user/two-factor-recovery-codes') {
                    self::success($operation, 200, Schema::fromType(self::object(['codes' => self::arrayOf(new StringType)])));
                } elseif ($method === 'GET' && $uri === 'user/confirmed-password') {
                    self::success($operation, 200, Schema::fromType(self::object(['confirmed' => new BooleanType])));
                }
            }
        }

        if ($cookie) {
            $path = Path::make('sanctum/csrf-cookie');
            $operation = Operation::make('get')->summary('Initialize SPA CSRF cookie')->description('Call before a stateful login or mutation, with credentials enabled.');
            $operation->security = [];
            self::success($operation, 204);
            $path->addOperation($operation);
            $api->addPath($path);
        }
    }
}
