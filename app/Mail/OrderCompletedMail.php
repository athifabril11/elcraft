<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * OrderCompletedMail
 *
 * Dikirim ke pelanggan ketika pesanan telah diselesaikan (status: selesai),
 * baik dikonfirmasi manual oleh pelanggan maupun diselesaikan otomatis oleh sistem.
 * Menggunakan pengiriman sinkron (tidak antri) konsisten dengan mailable lainnya.
 */
class OrderCompletedMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pesanan #{$this->order->order_number} Selesai - el Craft",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-completed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
