<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Headers;

trait LogsMailableIdentity
{
    public function headers(): Headers
    {
        $text = [
            'X-Mailable' => static::class,
        ];

        if (isset($this->templateSlug) && is_string($this->templateSlug) && $this->templateSlug !== '') {
            $text['X-Template-Slug'] = $this->templateSlug;
        }

        return new Headers(text: $text);
    }
}
