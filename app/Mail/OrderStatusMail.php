<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Goes through the mail queue: with QUEUE_CONNECTION=sync (default) it is sent straight
 * away, with database / redis a worker sends it so checkout never waits for the mail server.
 */
class OrderStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $headline) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline.' - '.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-status', text: 'emails.order-status-text');
    }
}
