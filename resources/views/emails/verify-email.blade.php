<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Alamat Email Anda - Elcraft</title>
</head>
<body style="margin:0; padding:0; background-color:#F8F5F2; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:14px; color:#3D2B27; -webkit-font-smoothing:antialiased;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F8F5F2; padding: 36px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">

                    {{-- Header / Brand --}}
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <div style="display:inline-block; background:#ffffff; border: 1px solid #EDE8E4; border-radius: 12px; padding: 10px 24px;">
                                <span style="font-size:22px; font-weight:800; color:#C17B6F; letter-spacing:-0.5px;">el Craft</span>
                                <span style="font-size:9px; font-weight:700; color:#C17B6F; text-transform:uppercase; letter-spacing:2px; display:block; margin-top:2px; text-align:center;">Atelier &amp; Aksesoris</span>
                            </div>
                        </td>
                    </tr>

                    {{-- Main Card --}}
                    <tr>
                        <td style="background:#ffffff; border-radius:16px; border:1px solid #EDE8E4; box-shadow: 0 4px 16px rgba(0,0,0,0.04); overflow:hidden;">

                            {{-- Accent Bar --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background:linear-gradient(135deg, #C17B6F 0%, #9E5A4E 100%); height:5px; border-radius:16px 16px 0 0;"></td>
                                </tr>
                            </table>

                            {{-- Card Body --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:36px 36px 32px;">

                                {{-- Icon & Greeting --}}
                                <tr>
                                    <td align="center" style="padding-bottom:20px;">
                                        <div style="display:inline-block; width:56px; height:56px; line-height:56px; border-radius:50%; background:#FAF7F5; border:1px solid #EDE8E4; text-align:center;">
                                            <span style="font-size:24px;">✉️</span>
                                        </div>
                                        <h1 style="margin:16px 0 8px; font-size:22px; font-weight:800; color:#221F1E; letter-spacing:-0.5px;">
                                            Verifikasi Email Anda
                                        </h1>
                                        <p style="margin:0; font-size:14px; color:#786F6B; line-height:1.6;">
                                            Halo, <strong style="color:#3D2B27;">{{ $user->name ?? 'Pelanggan Elcraft' }}</strong>! Terima kasih telah mendaftar di Elcraft.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Divider --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <hr style="border:none; border-top:1px solid #EDE8E4; margin:0;">
                                    </td>
                                </tr>

                                {{-- Description --}}
                                <tr>
                                    <td style="padding-bottom:28px;">
                                        <p style="margin:0 0 14px; font-size:14px; color:#5A4F4B; line-height:1.7;">
                                            Untuk mengaktifkan akun Anda sepenuhnya serta menikmati pengalaman berbelanja karya kerajinan tangan dan aksesoris Elcraft dengan aman, silakan konfirmasi alamat email Anda.
                                        </p>
                                        <p style="margin:0; font-size:14px; color:#5A4F4B; line-height:1.7;">
                                            Cukup klik tombol di bawah ini:
                                        </p>
                                    </td>
                                </tr>

                                {{-- CTA Button --}}
                                <tr>
                                    <td align="center" style="padding-bottom:28px;">
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="background:#C17B6F; border-radius:10px; box-shadow:0 3px 10px rgba(193,123,111,0.25);">
                                                    <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:14px 32px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none; letter-spacing:0.3px; border-radius:10px;">
                                                        Verifikasi Email Saya
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Expiration Notice Box --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FAF7F5; border-radius:10px; border-left:4px solid #C17B6F;">
                                            <tr>
                                                <td style="padding:14px 18px;">
                                                    <p style="margin:0 0 4px; font-size:12px; font-weight:700; color:#9E5A4E;">
                                                        Masa Berlaku Tautan
                                                    </p>
                                                    <p style="margin:0; font-size:12px; color:#786F6B; line-height:1.6;">
                                                        Tautan verifikasi di atas berlaku selama <strong>60 menit</strong>. Jika tautan kedaluwarsa, Anda dapat meminta pengiriman ulang melalui website Elcraft.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Fallback Link --}}
                                <tr>
                                    <td style="padding-top:8px; border-top:1px dashed #EDE8E4;">
                                        <p style="margin:16px 0 8px; font-size:12px; color:#A39994; line-height:1.6;">
                                            Jika tombol di atas tidak dapat diklik, salin dan tempel tautan berikut ke browser web Anda:
                                        </p>
                                        <p style="margin:0; font-size:11px; word-break:break-all; line-height:1.5;">
                                            <a href="{{ $url }}" style="color:#C17B6F; text-decoration:underline;">{{ $url }}</a>
                                        </p>
                                    </td>
                                </tr>

                                {{-- Security Disclaimer --}}
                                <tr>
                                    <td style="padding-top:18px;">
                                        <p style="margin:0; font-size:11.5px; color:#A39994; line-height:1.6;">
                                            Jika Anda tidak pernah membuat akun di Elcraft, silakan abaikan pesan email ini. Tidak ada tindakan lanjutan yang diperlukan.
                                        </p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding-top: 24px;">
                            <p style="margin:0 0 6px; font-size:12px; color:#A39994;">
                                &copy; {{ date('Y') }} Elcraft Atelier &amp; Aksesoris. Seluruh hak cipta dilindungi.
                            </p>
                            <p style="margin:0; font-size:11px; color:#B8ADA8;">
                                Email otomatis dari sistem Elcraft &bull; Mohon untuk tidak membalas email ini secara langsung.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
