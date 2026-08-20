<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Reset Password - SIPS Mobile</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f5ee; word-spacing:normal;">
    <span style="display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; mso-hide:all;">Permintaan reset password akun SIPS Mobile Anda - klik tombol untuk membuat password baru.</span>
    <center style="width:100%; background-color:#f3f5ee;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f5ee; margin:0 auto;">
            <tr>
                <td align="center" style="padding:32px 16px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e3e8dc;">

                        {{-- HEADER --}}
                        <tr>
                            <td style="background:linear-gradient(135deg,#0a693f 0%,#0f7d4c 100%); padding:32px 40px; text-align:center;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
                                    <tr>
                                        <td align="center" valign="middle" style="padding-bottom:12px;">
                                            <img src="{{ rtrim((string) (env('PASSWORD_RESET_URL') ?: config('app.url')), '/') . '/logo.svg' }}" alt="PT. Sentosa Kalimantan Jaya" width="64" height="64" style="display:block; width:64px; height:64px; border:0; max-width:100%;" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td align="center" style="color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:24px; font-weight:bold; letter-spacing:1px;">SIPS MOBILE</td>
                                    </tr>
                                    <tr>
                                        <td align="center" style="color:#d8f0e3; font-family:Arial,Helvetica,sans-serif; font-size:13px; padding-top:4px;">PT. Sentosa Kalimantan Jaya (SKJ)</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        {{-- BODY --}}
                        <tr>
                            <td style="padding:36px 40px; font-family:Arial,Helvetica,sans-serif; color:#333333;">
                                <p style="margin:0 0 16px 0; font-size:16px; color:#333333;">Halo {{ $user->fullname ?? ($user->username ?? 'Pengguna') }},</p>
                                <p style="margin:0 0 20px 0; font-size:14px; line-height:1.6; color:#555555;">
                                    Kami menerima permintaan untuk <strong>mereset password</strong> akun SIPS Mobile Anda.
                                    Klik tombol di bawah ini untuk membuat password baru. Jika bukan Anda yang melakukan permintaan ini,
                                    Anda tidak perlu melakukan apa-apa dan password Anda akan tetap aman.
                                </p>

                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:24px auto;">
                                    <tr>
                                        <td align="center" style="border-radius:8px;">
                                            <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:14px 36px; background-color:#ca8b2a; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:bold; text-decoration:none; border-radius:8px;">Reset Password</a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:24px 0 16px 0; font-size:13px; line-height:1.6; color:#555555;">
                                    Atau, jika tombol di atas tidak berfungsi, salin dan buka link berikut di browser Anda:
                                </p>
                                <p style="margin:0 0 20px 0;">
                                    <a href="{{ $url }}" style="font-size:12px; color:#0a693f; word-break:break-all;">{{ $url }}</a>
                                </p>

                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f7f9f3; border-left:4px solid #8dc651; border-radius:4px; margin:16px 0;">
                                    <tr>
                                        <td style="padding:12px 16px; font-size:12px; line-height:1.6; color:#666666;">
                                            Link ini berlaku selama <strong>{{ $expires }} menit</strong>.
                                            Jika Anda tidak meminta reset password, abaikan email ini — password Anda tidak akan berubah.
                                        </td>
                                    </tr>
                                </table>

                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 0 0;">
                                    <tr>
                                        <td style="font-size:13px; line-height:1.6; color:#555555;">
                                            Butuh bantuan? Hubungi IT Helpdesk melalui email
                                            <a href="mailto:it.helpdesk@skj.co.id" style="color:#0a693f;">it.helpdesk@skj.co.id</a>.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        {{-- FOOTER --}}
                        <tr>
                            <td style="background-color:#0a693f; padding:24px 40px; text-align:center;">
                                <p style="margin:0 0 6px 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#eaf6ef;">&copy; {{ date('Y') }} PT. Sentosa Kalimantan Jaya. Seluruh hak cipta dilindungi.</p>
                                <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#bcd9c8;">Butuh bantuan? Hubungi IT Helpdesk: <a href="mailto:it.helpdesk@skj.co.id" style="color:#ca8b2a; text-decoration:none;">it.helpdesk@skj.co.id</a></p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
