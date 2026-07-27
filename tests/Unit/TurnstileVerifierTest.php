<?php

namespace Tests\Unit;

use App\Services\Security\TurnstileVerifier;
use Tests\TestCase;

class TurnstileVerifierTest extends TestCase
{
    public function test_is_enabled_requires_site_and_secret_keys(): void
    {
        $verifier = app(TurnstileVerifier::class);

        config([
            'turnstile.enabled' => true,
            'turnstile.site_key' => null,
            'turnstile.secret_key' => 'secret',
        ]);
        $this->assertFalse($verifier->isEnabled());

        config([
            'turnstile.enabled' => true,
            'turnstile.site_key' => 'site',
            'turnstile.secret_key' => null,
        ]);
        $this->assertFalse($verifier->isEnabled());

        config([
            'turnstile.enabled' => true,
            'turnstile.site_key' => 'site',
            'turnstile.secret_key' => 'secret',
        ]);
        $this->assertTrue($verifier->isEnabled());

        config(['turnstile.enabled' => false]);
        $this->assertFalse($verifier->isEnabled());
    }
}
