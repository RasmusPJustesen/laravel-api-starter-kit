<?php

namespace App\Enums;

enum ApiErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case UnsupportedApiVersion = 'UNSUPPORTED_API_VERSION';
    case Conflict = 'CONFLICT';
    case CsrfTokenMismatch = 'CSRF_TOKEN_MISMATCH';
    case ValidationFailed = 'VALIDATION_FAILED';
    case RateLimited = 'RATE_LIMITED';
    case PasswordConfirmationRequired = 'PASSWORD_CONFIRMATION_REQUIRED';
    case EmailVerificationRequired = 'EMAIL_VERIFICATION_REQUIRED';
    case TwoFactorEnrollmentRequired = 'TWO_FACTOR_ENROLLMENT_REQUIRED';
    case ServerError = 'SERVER_ERROR';
    case RequestFailed = 'REQUEST_FAILED';
}
