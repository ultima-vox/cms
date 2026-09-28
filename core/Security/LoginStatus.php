<?php

declare(strict_types=1);

namespace Core\Security;

enum LoginStatus: string
{
    case Success = 'success';
    case InvalidCredentials = 'invalid_credentials';
    case RateLimited = 'rate_limited';
}
