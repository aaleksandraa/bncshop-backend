<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Rules\HoneypotEmpty;
use App\Rules\TurnstileToken;
use App\Services\Security\TurnstileVerifier;

trait ValidatesBotProtection
{
    /**
     * @return array<string, mixed>
     */
    protected function botProtectionRules(): array
    {
        $turnstileRequired = app(TurnstileVerifier::class)->isEnabled();

        return [
            'turnstile_token' => [
                $turnstileRequired ? 'required' : 'nullable',
                'string',
                new TurnstileToken(),
            ],
            'website' => ['nullable', 'string', new HoneypotEmpty()],
        ];
    }
}
