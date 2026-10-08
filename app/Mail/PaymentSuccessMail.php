<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * PaymentSuccessMail
 *
 * Dikirim otomatis ke pelanggan ketika pembayaran Midtrans berhasil dikonfirmasi (status: paid).
 * Berisi bukti pembayaran lunas, rincian produk yang dipesan, dan alamat tujuan pengiriman.
 */
class PaymentSuccessMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pembayaran Berhasil! Pesanan #{$this->order->order_number} - el Craft",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-success',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
