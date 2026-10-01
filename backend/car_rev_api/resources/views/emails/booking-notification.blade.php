@php
    $wording = config('notification.body');
    $fields = $wording['fields'];
    $empty = $wording['empty_value'];

    $greeting = str_replace(
        ':name',
        $booking->managerName ?? $booking->managerCode ?? $wording['greeting_fallback'],
        $wording['greeting'],
    );

    $rows = [
        $fields['doc_id'] => $booking->docId,
        $fields['requester'] => trim(($booking->requesterName ?? '').' ('.($booking->requesterCode ?? $empty).')'),
        $fields['department'] => $booking->department,
        $fields['plant'] => $booking->plant,
        $fields['tel'] => $booking->tel,
        $fields['used_at'] => trim(($booking->usedDate ?? '').' '.($booking->usedTime ?? '')),
        $fields['back_at'] => trim(($booking->backDate ?? '').' '.($booking->backTime ?? '')),
        $fields['destination'] => trim(($booking->destination ?? '').' '.($booking->province ?? '')),
        $fields['purpose'] => $booking->purpose,
        $fields['car_license'] => $booking->carLicense,
    ];
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>{{ $booking->docId }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:'Segoe UI',Tahoma,sans-serif;color:#1f2933;">
    {{-- Outlook desktop renders with Word: it ignores max-width, padding on <div>/<a> and
         inline-block, so the layout and the button are built from tables instead. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f5f7" style="background-color:#f4f5f7;">
    <tr>
    <td align="center" style="padding:24px;">
    <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:640px;background-color:#ffffff;border-radius:8px;">
    <tr>
    <td style="padding:24px;font-family:'Segoe UI',Tahoma,sans-serif;color:#1f2933;">
        <p style="margin:0 0 16px;font-size:16px;">{{ $greeting }}</p>

        <p style="margin:0 0 20px;font-size:15px;">{{ $wording['headlines'][$eventCode] ?? $eventCode }}</p>

        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
            @foreach ($rows as $label => $value)
                <tr>
                    <td style="padding:8px 12px;border-bottom:1px solid #e4e7eb;width:35%;color:#616e7c;">{{ $label }}</td>
                    <td style="padding:8px 12px;border-bottom:1px solid #e4e7eb;">{{ filled($value) ? $value : $empty }}</td>
                </tr>
            @endforeach
        </table>

        <p style="margin:20px 0 0;font-size:14px;">{{ $wording['closing'] }}</p>

        @if ($approvalUrl)
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0 0;">
                <tr>
                    <td bgcolor="#1f6feb" style="padding:10px 20px;background-color:#1f6feb;border-radius:6px;">
                        <a href="{{ $approvalUrl }}" style="color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;font-family:'Segoe UI',Tahoma,sans-serif;">{{ $wording['cta'] }}</a>
                    </td>
                </tr>
            </table>
        @endif

        <p style="margin:24px 0 0;font-size:12px;color:#7b8794;">{{ $wording['footer'] }}</p>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
</body>
</html>
