<?php

namespace App\Services\Marketing;

use App\Models\EmailTemplate;
use App\Models\MarketingContact;
use App\Services\Mail\EmailTemplateRenderer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrevoService
{
    private const BASE_URL = 'https://api.brevo.com/v3';

    public function __construct(
        private readonly BrevoSettings $settings,
        private readonly EmailTemplateRenderer $templateRenderer,
    ) {}

    public function isConfigured(): bool
    {
        return $this->settings->isEnabled();
    }

    public function syncContact(MarketingContact $contact): MarketingContact
    {
        $this->ensureConfigured();

        $settings = $this->settings->all();
        [$firstName, $lastName] = $this->splitName($contact->name);

        $payload = [
            'email' => $contact->email,
            'updateEnabled' => true,
            'attributes' => array_filter([
                'FIRSTNAME' => $firstName,
                'LASTNAME' => $lastName,
                'SMS' => $contact->phone,
                'COMPANY' => $contact->company_name,
                'CUSTOMER_TYPE' => $contact->typeLabel(),
                'ORDERS_COUNT' => $contact->orders_count,
                'ORDERS_TOTAL' => (float) $contact->orders_total,
            ], fn ($value): bool => $value !== null && $value !== ''),
        ];

        if ($listId = $settings['default_list_id'] ?? null) {
            $payload['listIds'] = [(int) $listId];
        }

        $response = $this->client()->post('/contacts', $payload);

        if ($response->failed() && $response->status() !== 400) {
            $response->throw();
        }

        $contact->forceFill([
            'brevo_contact_id' => $contact->email,
            'brevo_synced_at' => now(),
        ])->save();

        return $contact->fresh();
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function sendEmail(MarketingContact $contact, string $subject, string $htmlContent, array $variables = []): void
    {
        $this->ensureConfigured();

        $settings = $this->settings->all();
        $renderedSubject = $this->renderText($subject, $variables, $contact);
        $renderedHtml = $this->renderText($htmlContent, $variables, $contact);

        $this->client()->post('/smtp/email', [
            'sender' => [
                'email' => $settings['sender_email'],
                'name' => $settings['sender_name'] ?? 'BNC Shop',
            ],
            'to' => [[
                'email' => $contact->email,
                'name' => $contact->name ?: $contact->email,
            ]],
            'subject' => $renderedSubject,
            'htmlContent' => $renderedHtml,
        ])->throw();
    }

    public function sendTemplate(MarketingContact $contact, EmailTemplate $template, array $extraVariables = []): void
    {
        if (! $template->is_active) {
            throw new RuntimeException('E-mail šablon nije aktivan.');
        }

        $variables = array_merge($this->defaultVariables($contact), $extraVariables);

        $this->sendEmail(
            $contact,
            (string) $template->subject,
            (string) $template->body_html,
            $variables,
        );
    }

    public function testConnection(): bool
    {
        if (! $this->settings->apiKey()) {
            return false;
        }

        try {
            $this->client()->get('/account')->throw();

            return true;
        } catch (RequestException) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function defaultVariables(MarketingContact $contact): array
    {
        return [
            'customer_name' => $contact->name ?: 'kupče',
            'customer_email' => $contact->email,
            'customer_type' => $contact->typeLabel(),
            'orders_count' => (string) $contact->orders_count,
            'orders_total' => number_format((float) $contact->orders_total, 2, ',', '.').' KM',
        ];
    }

    private function renderText(string $content, array $variables, MarketingContact $contact): string
    {
        return $this->templateRenderer->renderContent(
            $content,
            array_merge($this->defaultVariables($contact), $variables),
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitName(?string $name): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
        ];
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withHeaders([
                'api-key' => (string) $this->settings->apiKey(),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])
            ->timeout(20);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Brevo integracija nije konfigurisana. Unesite API ključ u postavkama.');
        }
    }
}
