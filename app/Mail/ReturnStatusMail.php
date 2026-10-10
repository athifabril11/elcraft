<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * ReturnStatusMail
 *
 * Dikirim ke pelanggan ketika admin memproses retur:
 *   - 'approved'         — retur disetujui (refund atau tukar barang)
 *   - 'rejected'         — retur ditolak (admin_notes berisi alasan)
 *   - 'refund_completed' — refund API/manual berhasil dieksekusi
 *
 * Pengiriman sinkron (tidak antri) — selalu dibungkus try-catch + Log::error
 * di pemanggil agar kegagalan SMTP tidak merusak alur admin.
 */
class ReturnStatusMail extends Mailable
{
    public const EVENT_LABELS = [
        'approved'         => 'Disetujui',
        'rejected'         => 'Ditolak',
        'refund_completed' => 'Refund Selesai',
    ];

    public function __construct(
        public readonly ReturnRequest $return,
        public readonly string $event,
    ) {}

    public function envelope(): Envelope
    {
        $label = self::EVENT_LABELS[$this->event] ?? 'Diperbarui';

        return new Envelope(
            subject: "Retur #{$this->return->return_number} {$label} - el Craft",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.return-status',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
