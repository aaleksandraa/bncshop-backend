<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithStatefulApi;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithStatefulApi;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app->environment('testing')) {
            $this->withoutMiddleware(ValidateCsrfToken::class);
        }
    }
}
