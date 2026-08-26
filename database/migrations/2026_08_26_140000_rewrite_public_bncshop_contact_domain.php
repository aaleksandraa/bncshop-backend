<?php

use App\Models\CmsPage;
use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $contact = SystemSetting::query()->where('key', 'shop_contact')->first();

        if ($contact && is_array($contact->value)) {
            $value = $contact->value;

            foreach (['email', 'email_info'] as $field) {
                if (! empty($value[$field]) && is_string($value[$field])) {
                    $value[$field] = $this->rewriteContact($value[$field]);
                }
            }

            if (! empty($value['website']) && is_string($value['website'])) {
                $value['website'] = $this->rewriteContact($value['website']);
            }

            $contact->value = $value;
            $contact->save();
        }

        CmsPage::query()->each(function (CmsPage $page): void {
            $dirty = false;

            foreach (['body', 'meta_description', 'meta_title', 'title'] as $field) {
                $original = $page->{$field};
                if (! is_string($original) || $original === '') {
                    continue;
                }

                $rewritten = $this->rewriteContact($original);
                if ($rewritten !== $original) {
                    $page->{$field} = $rewritten;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $page->save();
            }
        });
    }

    public function down(): void
    {
        // Irreversible contact copy rewrite.
    }

    private function rewriteContact(string $value): string
    {
        return preg_replace(
            [
                '/@bncshop\.ba/i',
                '/https?:\/\/(?:www\.)?bncshop\.ba/i',
            ],
            [
                '@bnc.ba',
                'https://bnc.ba',
            ],
            $value,
        ) ?? $value;
    }
};
