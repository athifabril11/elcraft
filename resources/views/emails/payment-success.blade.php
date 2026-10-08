<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Berhasil - Pesanan #{{ $order->order_number }}</title>
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

                            {{-- Accent Bar --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background:linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%); height:5px; border-radius:16px 16px 0 0;"></td>
                                </tr>
                            </table>

                            {{-- Card Body --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:36px 40px;">

                                {{-- Success Greeting --}}
                                <tr>
                                    <td style="padding-bottom:20px;">
                                        <div style="display:inline-block; background:#E8F5E9; color:#2E7D32; font-size:11px; font-weight:800; padding:5px 12px; border-radius:20px; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">
                                            ✓ Pembayaran Terkonfirmasi
                                        </div>
                                        <p style="margin:0 0 6px; font-size:22px; font-weight:800; color:#221F1E; letter-spacing:-0.5px;">
                                            Terima Kasih atas Pembayaranmu! ✨
                                        </p>
                                        <p style="margin:0; font-size:14px; color:#786F6B; line-height:1.6;">
                                            Halo, <strong style="color:#3D2B27;">{{ $order->address->recipient_name ?? $order->user?->name ?? 'Pelanggan Setia' }}</strong>!
                                            Pembayaran untuk pesananmu telah kami terima. Kami segera menyiapkan produk pilihanmu dengan penuh ketelitian.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Divider --}}
                                <tr>
                                    <td style="padding-bottom:20px;">
                                        <hr style="border:none; border-top:1px solid #EDE8E4; margin:0;">
                                    </td>
                                </tr>

                                {{-- Ringkasan Transaksi --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FAF7F5; border-radius:10px; border:1px solid #EDE8E4; overflow:hidden;">
                                            <tr>
                                                <td style="padding:16px 20px;">
                                                    <p style="margin:0 0 12px; font-size:11px; font-weight:700; color:#A39994; text-transform:uppercase; letter-spacing:1.5px;">Rincian Transaksi</p>
                                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-bottom:6px; width:40%;">Nomor Pesanan</td>
                                                            <td style="font-size:12px; color:#221F1E; font-weight:700; padding-bottom:6px; font-family:monospace;">{{ $order->order_number }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-bottom:6px;">Metode Pembayaran</td>
                                                            <td style="font-size:12px; color:#221F1E; font-weight:600; padding-bottom:6px; text-transform:uppercase;">
                                                                {{ $order->payment?->payment_type ?? $order->payment?->payment_method ?? 'Midtrans' }}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-bottom:6px;">Status Pesanan</td>
                                                            <td style="font-size:12px; color:#2E7D32; font-weight:700; padding-bottom:6px;">
                                                                Lunas (Sedang Diproses)
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B;">Waktu Pembayaran</td>
                                                            <td style="font-size:12px; color:#221F1E; font-weight:600;">
                                                                {{ $order->payment?->paid_at ? \Carbon\Carbon::parse($order->payment->paid_at)->translatedFormat('d F Y, H:i') : now()->translatedFormat('d F Y, H:i') }} WIB
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Daftar Item Produk --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <p style="margin:0 0 10px; font-size:11px; font-weight:700; color:#A39994; text-transform:uppercase; letter-spacing:1.5px;">Produk yang Dipesan</p>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                            @foreach($order->items as $item)
                                                <tr>
                                                    <td style="padding:10px 0; border-bottom:1px solid #F0ECE8; vertical-align:top;">
                                                        <div style="font-size:13px; font-weight:700; color:#221F1E;">
                                                            {{ $item->product_name }}
                                                        </div>
                                                        @if($item->variant_name)
                                                            <div style="font-size:11px; color:#C17B6F; font-weight:600; margin-top:2px;">
                                                                Varian: {{ $item->variant_name }}
                                                            </div>
                                                        @endif
                                                        <div style="font-size:11px; color:#A39994; margin-top:2px;">
                                                            {{ $item->quantity }}x @rupiah($item->price)
                                                        </div>
                                                    </td>
                                                    <td align="right" style="padding:10px 0; border-bottom:1px solid #F0ECE8; font-size:13px; font-weight:700; color:#221F1E; vertical-align:top; white-space:nowrap;">
                                                        @rupiah($item->subtotal)
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>

                                {{-- Total Biaya --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FAF7F5; border-radius:10px; padding:16px 20px;">
                                            <tr>
                                                <td style="font-size:12px; color:#786F6B; padding-bottom:6px;">Subtotal Produk</td>
                                                <td align="right" style="font-size:12px; color:#221F1E; font-weight:600; padding-bottom:6px;">@rupiah($order->subtotal)</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:12px; color:#786F6B; padding-bottom:6px;">Ongkos Kirim ({{ $order->formatted_courier }})</td>
                                                <td align="right" style="font-size:12px; color:#221F1E; font-weight:600; padding-bottom:6px;">@rupiah($order->shipping_cost)</td>
                                            </tr>
                                            @if($order->voucher_discount > 0)
                                                <tr>
                                                    <td style="font-size:12px; color:#C17B6F; font-weight:600; padding-bottom:6px;">Diskon Voucher</td>
                                                    <td align="right" style="font-size:12px; color:#C17B6F; font-weight:600; padding-bottom:6px;">-@rupiah($order->voucher_discount)</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td colspan="2" style="padding-top:8px; border-top:1px solid #EDE8E4;"></td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:14px; font-weight:800; color:#221F1E;">Total Pembayaran</td>
                                                <td align="right" style="font-size:16px; font-weight:800; color:#C17B6F;">@rupiah($order->total_amount)</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Alamat Pengiriman --}}
                                @if($order->address)
                                <tr>
                                    <td style="padding-bottom:28px;">
                                        <p style="margin:0 0 8px; font-size:11px; font-weight:700; color:#A39994; text-transform:uppercase; letter-spacing:1.5px;">Alamat Pengiriman</p>
                                        <div style="font-size:12px; color:#786F6B; line-height:1.6; background:#fff; border:1px solid #EDE8E4; border-radius:10px; padding:14px 18px;">
                                            <strong style="color:#221F1E;">{{ $order->address->recipient_name }}</strong> ({{ $order->address->phone }})<br>
                                            {{ $order->address->full_address }}<br>
                                            {{ $order->address->city }}, {{ $order->address->postal_code }}
                                        </div>
                                    </td>
                                </tr>
                                @endif

                                {{-- CTA Button --}}
                                <tr>
                                    <td align="center" style="padding-bottom:12px;">
                                        <a href="{{ config('app.url') }}/orders/{{ $order->order_number }}"
                                           style="display:inline-block; background:#C17B6F; color:#ffffff; font-size:13px; font-weight:700; text-decoration:none; padding:14px 32px; border-radius:10px; letter-spacing:0.5px; text-transform:uppercase;">
                                            Lihat Status Pesanan
                                        </a>
                                    </td>
                                </tr>

                            </table>

                            {{-- Footer Note --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FAF7F5; border-top:1px solid #EDE8E4; padding:20px 40px;">
                                <tr>
                                    <td align="center">
                                        <p style="margin:0; font-size:11px; color:#A39994; line-height:1.7;">
                                            Email ini dikirim otomatis sebagai bukti konfirmasi pembayaran yang sah.<br>
                                            Jika Anda membutuhkan bantuan, hubungi kami melalui layanan pelanggan <strong>el Craft</strong>.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- Footer Copyright --}}
                    <tr>
                        <td align="center" style="padding-top:20px;">
                            <p style="margin:0; font-size:11px; color:#B5ACA7;">
                                &copy; {{ date('Y') }} el Craft. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
