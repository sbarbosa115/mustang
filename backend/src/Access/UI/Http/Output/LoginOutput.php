<?php

namespace App\Access\UI\Http\Output;

use OpenApi\Attributes as OA;

final readonly class LoginOutput
{
    public function __construct(
        /** What the app shows next: the app itself, the MFA code step, or MFA enrollment. */
        #[OA\Property(enum: ['ok', 'mfa', 'enroll'])]
        public string $next,
        public UserOutput $user,
    ) {
    }
}
