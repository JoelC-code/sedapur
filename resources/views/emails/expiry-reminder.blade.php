{{-- Template email pengingat harian (inline style agar aman di klien email). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $items->count() }} bahan di {{ $household['nama'] }} segera kedaluwarsa</title>
</head>
<body style="margin:0;background:#efefed;font-family:'Plus Jakarta Sans',Arial,sans-serif;color:#292d2f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:440px">
                <tr><td style="padding:0 4px 12px;font-size:12px;color:#666">
                    Kotak masuk · 07.00<br>
                    <b style="font-size:15px;color:#292d2f">{{ $items->count() }} bahan di {{ $household['nama'] }} segera kedaluwarsa</b><br>
                    {{ config('app.name') }} · pengingat harian
                </td></tr>
                <tr><td style="background:#fff;border-radius:20px;padding:20px">
                    <p style="margin:0 0 12px;font-size:16px;font-weight:800;color:#4c610d">🍃 {{ config('app.name') }}</p>
                    <p style="margin:0 0 14px;font-size:14px;line-height:1.5">Pagi, Hans! Ada bahan yang perlu dihabiskan hari ini.</p>
                    @foreach ($items as $item)
                        @php [$label] = \App\Support\DemoData::expiryBadge($item['sisa_hari']); @endphp
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#efefed;border-radius:14px;margin-bottom:8px">
                            <tr>
                                <td style="padding:10px 12px;font-size:20px;width:28px">{{ $item['emoji'] }}</td>
                                <td style="padding:10px 0;font-size:14px;font-weight:700">{{ $item['nama'] }}</td>
                                <td align="right" style="padding:10px 12px"><span style="background:#fff0d6;color:#9a5b00;border-radius:999px;padding:3px 9px;font-size:11px;font-weight:700">{{ $label }}</span></td>
                            </tr>
                        </table>
                    @endforeach
                    <p style="margin:12px 0 16px;font-size:13px;color:#666">Ide resep: {{ mb_strtolower($recipe['nama']) }} ({{ $recipe['kalori'] }} kkal/porsi).</p>
                    <a href="{{ route('dashboard') }}" style="display:block;text-align:center;background:#e0702a;color:#fff;text-decoration:none;font-weight:700;font-size:15px;border-radius:999px;padding:15px">Buka {{ config('app.name') }}</a>
                    <p style="margin:14px 0 0;text-align:center;font-size:11px;color:#9a9a9a">Matikan pengingat di Rumah &amp; akun</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
