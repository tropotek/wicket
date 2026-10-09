<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A visitor's message from /contact, sent to the site's own address. */
class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array{name: string, email: string, phone: ?string, message: string}  $data */
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->data['email'], $this->data['name'])],
            subject: config('app.name').' contact: '.preg_replace('/[\r\n]+/', ' ', $this->data['name']),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact');
    }
}
