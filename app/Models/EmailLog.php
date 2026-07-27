<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const CHANNEL_LARAVEL = 'laravel';

    public const CHANNEL_BREVO = 'brevo';

    protected $fillable = [
        'channel',
        'status',
        'recipient',
        'subject',
        'mailable_class',
        'template_slug',
        'mailer',
        'queued',
        'context',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'queued' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function mailableLabel(): string
    {
        if (filled($this->template_slug)) {
            return (string) $this->template_slug;
        }

        if (! filled($this->mailable_class)) {
            return '—';
        }

        return class_basename($this->mailable_class);
    }
}
