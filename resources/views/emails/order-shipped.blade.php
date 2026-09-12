<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan #{{ $order->order_number }} Sedang Dikirim</title>
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
                                    <td style="background:linear-gradient(135deg, #C17B6F 0%, #9E5A4E 100%); height:5px; border-radius:16px 16px 0 0;"></td>
                                </tr>
                            </table>

                            {{-- Card Body --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:36px 40px;">

                                {{-- Greeting --}}
                                <tr>
                                    <td style="padding-bottom:20px;">
                                        <p style="margin:0 0 6px; font-size:22px; font-weight:800; color:#221F1E; letter-spacing:-0.5px;">
                                            Pesanan Kamu Sedang Dikirim! 📦
                                        </p>
                                        <p style="margin:0; font-size:14px; color:#786F6B; line-height:1.6;">
                                            Halo, <strong style="color:#3D2B27;">{{ $order->address->recipient_name ?? $order->user?->name ?? 'Pelanggan' }}</strong>!
                                            Kabar baik — pesananmu sudah dalam perjalanan menuju kamu.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Divider --}}
                                <tr>
                                    <td style="padding-bottom:20px;">
                                        <hr style="border:none; border-top:1px solid #EDE8E4; margin:0;">
                                    </td>
                                </tr>

                                {{-- Order Info --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FAF7F5; border-radius:10px; border:1px solid #EDE8E4; overflow:hidden;">
                                            <tr>
                                                <td style="padding:16px 20px;">
                                                    <p style="margin:0 0 12px; font-size:11px; font-weight:700; color:#A39994; text-transform:uppercase; letter-spacing:1.5px;">Info Pesanan</p>
                                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-bottom:6px; width:40%;">Nomor Pesanan</td>
                                                            <td style="font-size:12px; color:#221F1E; font-weight:700; padding-bottom:6px; font-family:monospace;">{{ $order->order_number }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-bottom:6px;">Kurir</td>
                                                            <td style="font-size:12px; color:#221F1E; font-weight:600; padding-bottom:6px;">{{ $order->formatted_courier }}</td>
                                                        </tr>
                                                        @if($order->shipment?->tracking_number)
                                                        <tr>
                                                            <td style="font-size:12px; color:#786F6B; padding-top:4px; vertical-align:top;">Nomor Resi</td>
                                                            <td style="padding-top:4px;">
                                                                <span style="display:inline-block; background:#C17B6F; color:#fff; font-size:13px; font-weight:800; padding:6px 14px; border-radius:8px; font-family:monospace; letter-spacing:0.5px;">
                                                                    {{ $order->shipment->tracking_number }}
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

                                {{-- Tracking Instruction --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FDF0EE; border-radius:10px; border-left:4px solid #C17B6F;">
                                            <tr>
                                                <td style="padding:14px 18px;">
                                                    <p style="margin:0 0 4px; font-size:12px; font-weight:700; color:#9E5A4E;">Cara Lacak Paketmu</p>
                                                    <p style="margin:0; font-size:12px; color:#7A4A40; line-height:1.7;">
                                                        Simpan nomor resi di atas, lalu kunjungi website resmi kurir
                                                        <strong>{{ strtoupper($order->courier ?? 'ekspedisi') }}</strong>
                                                        untuk melacak posisi paketmu secara real-time.
                                                        Estimasi pengiriman bervariasi sesuai tujuan.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Items Summary --}}
                                @if($order->items && $order->items->count() > 0)
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <p style="margin:0 0 10px; font-size:11px; font-weight:700; color:#A39994; text-transform:uppercase; letter-spacing:1.5px;">Item yang Dikirim</p>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #EDE8E4; border-radius:10px; overflow:hidden;">
                                            @foreach($order->items as $item)
                                            <tr style="background:{{ $loop->even ? '#FAF7F5' : '#fff' }};">
                                                <td style="padding:10px 16px; font-size:12px; color:#3D2B27;">
                                                    {{ $item->product_name }}
                                                    @if($item->variant_name)
                                                        <span style="color:#A39994;"> — {{ $item->variant_name }}</span>
                                                    @endif
                                                </td>
                                                <td style="padding:10px 16px; font-size:12px; color:#786F6B; text-align:right; white-space:nowrap;">
                                                    ×{{ $item->quantity }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                                @endif

                                {{-- CTA --}}
                                <tr>
                                    <td style="padding-bottom:28px; text-align:center;">
                                        <a href="{{ route('orders.show', $order->order_number) }}"
                                           style="display:inline-block; background:linear-gradient(135deg, #C17B6F 0%, #9E5A4E 100%); color:#fff; font-size:13px; font-weight:700; padding:12px 28px; border-radius:10px; text-decoration:none; letter-spacing:0.2px;">
                                            Lihat Detail Pesanan
                                        </a>
                                    </td>
                                </tr>

                                {{-- Closing --}}
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

                    {{-- Footer --}}
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
