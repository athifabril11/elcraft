<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retur #{{ $return->return_number }} {{ \App\Mail\ReturnStatusMail::EVENT_LABELS[$event] ?? 'Diperbarui' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#F8F5F2; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:14px; color:#3D2B27; -webkit-font-smoothing:antialiased;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F8F5F2; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:580px;">

                    {{-- Header / Brand --}}
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <div style="display:inline-block; background:#fff; border: 1px solid #EDE8E4; border-radius: 12px; padding: 10px 22px;">
                                <span style="font-size:20px; font-weight:800; color:#C17B6F; letter-spacing:-0.5px;">el Craft</span>
                                <span style="font-size:9px; font-weight:700; color:#C17B6F; text-transform:uppercase; letter-spacing:2px; display:block; margin-top:2px; text-align:center;">Atelier &amp; Aksesoris</span>
                            </div>
                        </td>
                    </tr>

                    {{-- Main Card --}}
                    <tr>
                        <td style="background:#ffffff; border-radius:16px; border:1px solid #EDE8E4; box-shadow: 0 2px 12px rgba(0,0,0,0.04); overflow:hidden;">

                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background:linear-gradient(135deg, #C17B6F 0%, #9E5A4E 100%); height:5px; border-radius:16px 16px 0 0;"></td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:36px 40px;">

                                {{-- Greeting & Event Headline --}}
                                <tr>
                                    <td style="padding-bottom:20px;">
                                        @if($event === 'approved')
                                            <p style="margin:0 0 6px; font-size:22px; font-weight:800; color:#221F1E; letter-spacing:-0.5px;">
                                                Retur Kamu Disetujui! ✅
                                            </p>
                                            <p style="margin:0; font-size:14px; color:#786F6B; line-height:1.6;">
                                                Halo, <strong style="color:#3D2B27;">{{ $return->user?->name ?? 'Pelanggan' }}</strong>!
                                                Kabar baik — pengajuan retur kamu telah kami setujui dan akan segera diproses.
                                            </p>
                                        @elseif($event === 'rejected')
                                            <p style="margin:0 0 6px; font-size:22px; font-weight:800; color:#221F1E; letter-spacing:-0.5px;">
                                                Retur Kamu Belum Disetujui
                                            </p>
                                            <p style="margin:0; font-size:14px; color:#786F6B; line-height:1.6;">
                                                Halo, <strong style="color:#3D2B27;">{{ $return->user?->name ?? 'Pelanggan' }}</strong> —
                                                sayangnya pengajuan retur kamu belum dapat kami proses. Alasan ada di bawah.
                                            </p>
                                        @else
                                            <p style="margin:0 0 6px; font-size:22px; font-weight:800; color:#221F1E; letter-spacing:-0.5px;">
                                                Refund Kamu Selesai! 💸
                                            </p>
                                            <p style="margin:0; font-size:14px; color:#786F6B; line-height:1.6;">
                                                Halo, <strong style="color:#3D2B27;">{{ $return->user?->name ?? 'Pelanggan' }}</strong>!
                                                Pengembalian dana untuk retur kamu telah berhasil diproses.
                                            </p>
                                        @endif
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom:20px;">
                                        <hr style="border:none; border-top:1px solid #EDE8E4; margin:0;">
                                    </td>
                                </tr>

                                {{-- Return Info --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FAF7F5; border-radius:10px; border:1px solid #EDE8E4; overflow:hidden;">
                                            <tr>
                                                <td style="padding:16px 20px;">
                                                    <p style="margin:0 0 12px; font-size:11px; font-weight:700; color:#A39994; text-transform:uppercase; letter-spacing:1.5px;">Info Retur</p>
                                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-bottom:6px; width:45%;">Nomor Retur</td>
                                                            <td style="font-size:12px; color:#221F1E; font-weight:700; padding-bottom:6px; font-family:monospace;">{{ $return->return_number }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-bottom:6px;">Nomor Pesanan</td>
                                                            <td style="font-size:12px; color:#221F1E; font-weight:700; padding-bottom:6px; font-family:monospace;">{{ $return->order?->order_number }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-bottom:6px;">Jenis Pengembalian</td>
                                                            <td style="font-size:12px; color:#221F1E; font-weight:600; padding-bottom:6px;">
                                                                {{ $return->resolution_type === 'tukar_barang' ? 'Tukar Barang' : 'Refund (Pengembalian Dana)' }}
                                                            </td>
                                                        </tr>
                                                        @if($return->resolution_type === 'refund' && $return->refund_amount && $event !== 'rejected')
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-top:4px;">Nominal Refund</td>
                                                            <td style="padding-top:4px;">
                                                                <span style="display:inline-block; background:#C17B6F; color:#fff; font-size:13px; font-weight:800; padding:6px 14px; border-radius:8px; font-family:monospace; letter-spacing:0.5px;">
                                                                    Rp {{ number_format((float) $return->refund_amount, 0, ',', '.') }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        @endif
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Reason / Admin Notes --}}
                                @if($event === 'rejected' && $return->admin_notes)
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FDF0EE; border-radius:10px; border-left:4px solid #C17B6F;">
                                            <tr>
                                                <td style="padding:14px 18px;">
                                                    <p style="margin:0 0 4px; font-size:12px; font-weight:700; color:#9E5A4E;">Alasan Penolakan</p>
                                                    <p style="margin:0; font-size:12px; color:#7A4A40; line-height:1.7;">{{ $return->admin_notes }}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @elseif($event === 'refund_completed')
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F0FDF4; border-radius:10px; border-left:4px solid #16a34a;">
                                            <tr>
                                                <td style="padding:14px 18px;">
                                                    <p style="margin:0 0 4px; font-size:12px; font-weight:700; color:#166534;">Dana Dikembalikan</p>
                                                    <p style="margin:0; font-size:12px; color:#15803d; line-height:1.7;">
                                                        Dana akan masuk ke metode pembayaran asal dalam beberapa hari kerja,
                                                        tergantung kebijakan bank/e-wallet kamu. Jika lewat 7 hari kerja belum masuk,
                                                        balas email ini atau hubungi kami.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endif

                                {{-- CTA --}}
                                <tr>
                                    <td style="padding-bottom:28px; text-align:center;">
                                        <a href="{{ route('orders.returns.show', $return->order?->order_number) }}"
                                           style="display:inline-block; background:linear-gradient(135deg, #C17B6F 0%, #9E5A4E 100%); color:#fff; font-size:13px; font-weight:700; padding:12px 28px; border-radius:10px; text-decoration:none; letter-spacing:0.2px;">
                                            Lihat Detail Retur
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td>
                                        <p style="margin:0; font-size:13px; color:#786F6B; line-height:1.7;">
                                            Terima kasih sudah berbelanja di <strong style="color:#C17B6F;">el Craft</strong>.
                                            Jika ada pertanyaan, balas email ini dan tim kami siap membantu. 💖
                                        </p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:24px; text-align:center;">
                            <p style="margin:0; font-size:11px; color:#A39994; line-height:1.8;">
                                &copy; {{ date('Y') }} el Craft — Atelier &amp; Aksesoris<br>
                                Email ini dikirim otomatis, mohon jangan membalas jika tidak diperlukan.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
