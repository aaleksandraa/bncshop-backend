<?php

namespace Tests\Concerns;

trait InteractsWithStatefulApi
{
    /**
     * @return array<string, string>
     */
    protected function statefulApiHeaders(array $extra = []): array
    {
        return array_merge([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ], $extra);
    }

    protected function withStatefulOrigin(): static
    {
        return $this->withHeaders($this->statefulApiHeaders());
    }

    protected function initStatefulSession(): void
    {
        $this->withHeaders($this->statefulApiHeaders())
            ->get('/sanctum/csrf-cookie');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function postJsonStateful(string $uri, array $data = [], array $headers = [])
    {
        $this->initStatefulSession();

        return $this->withHeaders($this->statefulApiHeaders($headers))
            ->postJson($uri, $data);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function getJsonStateful(string $uri, array $headers = [])
    {
        return $this->withHeaders($this->statefulApiHeaders($headers))
            ->getJson($uri);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function patchJsonStateful(string $uri, array $data = [], array $headers = [])
    {
        $this->initStatefulSession();

        return $this->withHeaders($this->statefulApiHeaders($headers))
            ->patchJson($uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function postMultipartStateful(string $uri, array $data = [], array $headers = [])
    {
        $this->initStatefulSession();

        return $this->withHeaders(array_merge(
            $this->statefulApiHeaders($headers),
            ['Accept' => 'application/json'],
        ))->post($uri, $data);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function deleteJsonStateful(string $uri, array $headers = [])
    {
        $this->initStatefulSession();

        return $this->withHeaders($this->statefulApiHeaders($headers))
            ->deleteJson($uri);
    }
}
