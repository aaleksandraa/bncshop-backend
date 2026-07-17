<?php

namespace App\Mail;

use App\Models\InstallmentInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstallmentInquiryNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public InstallmentInquiry $inquiry,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name', 'BNC Shop');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address($this->inquiry->email, $this->inquiry->full_name)],
            subject: 'Novi upit za kupovinu na rate — '.$this->inquiry->full_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.installment-inquiry',
            with: [
                'inquiry' => $this->inquiry,
                'adminUrl' => rtrim((string) config('app.url'), '/').'/admin/installment-inquiries/'.$this->inquiry->id,
            ],
        );
    }
}
