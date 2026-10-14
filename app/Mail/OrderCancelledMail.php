<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * OrderCancelledMail
 *
 * Dikirim ke pelanggan ketika pesanan dibatalkan:
 *   - 'customer_cancel' — pelanggan membatalkan sendiri pesanan pending
 *   - 'payment_failed'  — pembayaran ditolak/kedaluarsa/dibatalkan (webhook Midtrans)
 *   - 'merchant_cancel' — toko membatalkan pesanan paid/diproses dari panel
 *                         admin ($reason = alasan admin; refund diproses toko)
 *
 * Pengiriman sinkron — selalu dibungkus try-catch + Log::error di pemanggil
 * agar kegagalan SMTP tidak merusak alur pembatalan.
 */
class OrderCancelledMail extends Mailable
{
    public const EVENT_REASONS = [
        'deny'   => 'Pembayaran ditolak oleh penyedia pembayaran.',
        'expire' => 'Batas waktu pembayaran habis sebelum pembayaran diselesaikan.',
        'cancel' => 'Pembayaran dibatalkan.',
    ];

    public function __construct(
        public readonly Order $order,
        public readonly string $event,
        public readonly ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            'customer_cancel' => "Pesanan #{$this->order->order_number} Dibatalkan - el Craft",
            'merchant_cancel' => "Pesanan #{$this->order->order_number} Dibatalkan oleh Toko - el Craft",
            default           => "Pembayaran Gagal untuk Pesanan #{$this->order->order_number} - el Craft",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-cancelled',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
