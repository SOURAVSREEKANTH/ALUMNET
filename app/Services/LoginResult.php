<?php

declare(strict_types=1);

namespace AlumniConnect\Services;

enum LoginResult: string
{
    case Authenticated = 'authenticated';
    case InvalidCredentials = 'invalid_credentials';
    case Throttled = 'throttled';
}
