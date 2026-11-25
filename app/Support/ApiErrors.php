<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiErrors
{
    public static function render(Throwable $exception, Response $response): Response
    {
        if (! $response instanceof JsonResponse || $response->getStatusCode() < 400) {
            return $response;
        }
        $status = $response->getStatusCode();
        $body = $response->getData(true);
        $codes = [401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND', 406 => 'UNSUPPORTED_API_VERSION', 409 => 'CONFLICT', 419 => 'CSRF_TOKEN_MISMATCH', 422 => 'VALIDATION_FAILED', 429 => 'RATE_LIMITED'];
        $code = $codes[$status] ?? ($status >= 500 ? 'SERVER_ERROR' : 'REQUEST_FAILED');

        // Preserve Laravel's field map for existing clients and expose typed errors separately.
        $body['error_details'] = [];
        if ($exception instanceof ValidationException) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $body['error_details'][] = ['status' => (string) $status, 'code' => $code, 'detail' => $message, 'source' => ['parameter' => $field]];
                }
            }
        } else {
            $body['error_details'][] = ['status' => (string) $status, 'code' => $code, 'detail' => $status >= 500 ? __('Server error.') : ($body['message'] ?? __('Request failed.'))];
        }
        $response->setData($body);

        return $response;
    }
}
