<?php

namespace App\Enums;

enum VerificationResult: string
{
    case Verified = 'verified';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case AlreadyVerified = 'already_verified';
    case NoActiveCode = 'no_active_code';
}
