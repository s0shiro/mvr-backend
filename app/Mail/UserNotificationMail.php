<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $body;
    public $details;

    /**
     * Create a new message instance.
     */
    public function __construct($subject, $body, $details = [])
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->details = $details;
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    public function build()
    {
        return $this->subject($this->subject ?? 'Notification')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.user_notification')
            ->with([
                'subject' => $this->subject,
                'body' => $this->body,
                'details' => $this->details,
            ]);
    }
}
