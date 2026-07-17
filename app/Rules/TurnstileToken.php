<?php

namespace App\Rules;

use App\Services\Security\TurnstileVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurnstileToken implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $verifier = app(TurnstileVerifier::class);

        if (! $verifier->isEnabled()) {
            return;
        }

        if (! $verifier->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail('Provjera sigurnosti nije uspjela. Pokušajte ponovo.');
        }
    }
}
