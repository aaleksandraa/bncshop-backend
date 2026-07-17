<?php

namespace App\Services\Mail;

use App\Models\EmailTemplate;

class EmailTemplateRenderer
{
    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body: string}|null
     */
    public function render(string $slug, array $variables): ?array
    {
        $template = EmailTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return null;
        }

        return [
            'subject' => $this->replaceVariables($template->subject, $variables),
            'body' => $this->replaceVariables($template->body_html, $variables),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function renderContent(string $content, array $variables): string
    {
        return $this->replaceVariables($content, $variables);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function replaceVariables(string $content, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($content, $replacements);
    }
}
