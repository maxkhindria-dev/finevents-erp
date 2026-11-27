<?php

namespace App\Auth;

use RuntimeException;

/** A Google account that may not sign in. The reason goes to the audit log, never to the screen. */
class SignInRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Sign-in refused: {$reason}");
    }
}
