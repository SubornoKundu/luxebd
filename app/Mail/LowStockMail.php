<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Product>|\Illuminate\Support\Collection<int, \App\Models\Product>  $products
     */
    public function __construct(public $products) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Low stock alert - '.$this->products->count().' product(s) running out');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.low-stock');
    }
}
