<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * OrderShippedMail
 *
 * Dikirim ke pelanggan ketika admin menandai pesanan sebagai "dikirim"
 * dan memasukkan nomor resi. Menggunakan pengiriman sinkron (tidak antri)
 * karena QUEUE_CONNECTION=database tanpa worker yang terkonfirmasi aktif.
 */
class OrderShippedMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pesanan #{$this->order->order_number} Sedang Dikirim - el Craft",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-shipped',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
