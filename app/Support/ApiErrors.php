<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiErrors
{
    public static function response(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'error_details' => [
            ['status' => (string) $status, 'code' => $code, 'detail' => $message],
        ]], $status);
    }

    public static function render(Throwable $exception, Response $response): Response
    {
        if (! $response instanceof JsonResponse || $response->getStatusCode() < 400) {
            return $response;
        }
        $status = $response->getStatusCode();
        $original = $response->getData(true);
        $message = $status >= 500 ? __('Server error.') : ($original['message'] ?? __('Request failed.'));
        $body = ['message' => (string) $message];
        if ($exception instanceof ValidationException) {
            $body['errors'] = $exception->errors();
        }

        $codes = [401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND', 406 => 'UNSUPPORTED_API_VERSION', 409 => 'CONFLICT', 419 => 'CSRF_TOKEN_MISMATCH', 422 => 'VALIDATION_FAILED', 429 => 'RATE_LIMITED'];
        $code = $response->headers->get('X-API-Error-Code') ?? ($codes[$status] ?? ($status >= 500 ? 'SERVER_ERROR' : 'REQUEST_FAILED'));
        $response->headers->remove('X-API-Error-Code');

        // Preserve Laravel's field map for existing clients and expose typed errors separately.
        $body['error_details'] = [];
        if ($exception instanceof ValidationException) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $body['error_details'][] = ['status' => (string) $status, 'code' => $code, 'detail' => $message, 'source' => ['parameter' => $field]];
                }
            }
        } else {
            $body['error_details'][] = ['status' => (string) $status, 'code' => $code, 'detail' => $status >= 500 ? __('Server error.') : $body['message']];
        }
        $response->setData($body);

        return $response;
    }
}
