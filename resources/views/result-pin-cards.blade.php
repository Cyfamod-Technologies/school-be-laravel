<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Result Scratch Cards</title>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            margin: 0;
            padding: 12px;
            background: #f1f5f9;
            color: #0f172a;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .summary {
            background: #fff;
            padding: 16px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            margin-bottom: 24px;
        }
        .card-page {
            page-break-after: always;
            margin-bottom: 8px;
            padding: 0;
        }
        .card-page:last-child {
            page-break-after: auto;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(6, 1fr);
            gap: 4px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .card {
            position: relative;
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            color: #fff;
            padding: 8px;
            border-radius: 5px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.35);
            overflow: hidden;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }
        .card::after {
            content: "";
            position: absolute;
            inset: 0;
            border: 2px dashed rgba(255, 255, 255, 0.25);
            border-radius: 5px;
            pointer-events: none;
        }
        .card-logo {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        .card-logo img {
            width: 32px;
            height: 32px;
            object-fit: contain;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.15);
            padding: 2px;
            flex-shrink: 0;
        }
        .card-logo strong {
            font-size: 10px;
            line-height: 1.15;
        }
        .card-details {
            margin-bottom: 4px;
            font-size: 11px;
            line-height: 1.25;
        }
        .card-details div {
            margin-bottom: 1px;
        }
        .pin-code {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            background: rgba(15, 23, 42, 0.4);
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            margin-bottom: 4px;
        }
        .expiry {
            font-size: 10px;
            opacity: 0.9;
            margin-bottom: 2px;
        }
        .portal-link {
            font-size: 9px;
            color: rgba(255, 255, 255, 0.9);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .portal-link a {
            color: #fff;
            text-decoration: none;
        }
        .footer {
            text-align: center;
            margin-top: 32px;
            font-size: 13px;
            color: #475569;
        }
        @media print {
            * { orphans: 1; widows: 1; }
            body { background: #fff; padding: 0; margin: 0; }
            .header, .summary, .footer { display: none; }
            .card-page { page-break-after: always; page-break-inside: avoid; margin: 0; padding: 2mm; break-inside: avoid; box-sizing: border-box; height: calc(297mm - 8mm); }
            .card-page:last-child { page-break-after: auto; }
            .cards { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(6, 1fr); padding: 0; gap: 3px; page-break-inside: avoid; break-inside: avoid; height: 100%; }
            .card { box-shadow: none; background: linear-gradient(135deg, #0f172a, #1d4ed8) !important; page-break-inside: avoid; break-inside: avoid; }
            @page { size: A4; margin: 4mm; }
        }
    </style>
</head>
<body>
<div class="header">
    <div>
        <h1>Result Scratch Cards</h1>
        <div>Session: {{ $sessionName ?? 'N/A' }} | Term: {{ $termName ?? 'N/A' }}</div>
    </div>
    <button type="button" onclick="window.print()" style="padding:10px 16px;border:none;border-radius:8px;background:#0f172a;color:#fff;cursor:pointer;">Print / Export</button>
</div>
<div class="summary">
    <strong>Generated:</strong> {{ $generatedAt }}<br>
    <strong>Total Cards:</strong> {{ count($cards) }}
</div>
@foreach($cardPages as $pageCards)
    <div class="card-page">
        <div class="cards">
            @foreach($pageCards as $card)
                <div class="card">
                    <div class="card-logo">
                        @php
                            $schoolLines = preg_split('/<br\s*\/?>/i', (string) ($school->name ?? '')) ?: [];
                            $schoolLines = array_values(array_filter(array_map('trim', $schoolLines), fn ($line) => $line !== ''));
                            if (empty($schoolLines)) {
                                $schoolLines = [(string) ($school->name ?? 'Your School')];
                            }
                        @endphp
                        @if(!empty($schoolLogoUrl))
                            <img src="{{ $schoolLogoUrl }}" alt="School Logo">
                        @else
                            <div style="width:48px;height:48px;border-radius:8px;border:1px solid rgba(255,255,255,0.3);display:flex;align-items:center;justify-content:center;font-weight:600;">LOGO</div>
                        @endif
                        <strong>
                            @foreach($schoolLines as $index => $line)
                                @if($index > 0)
                                    <br>
                                @endif
                                {{ strtoupper($line) }}
                            @endforeach
                            <br>RESULT ACCESS CARD
                        </strong>
                    </div>
                    <div class="card-details">
                        <div><strong>Student:</strong> {{ $card['student_name'] }}</div>
                        <div><strong>ADM No:</strong> {{ $card['admission_no'] ?? 'N/A' }}</div>
                        <div><strong>Class:</strong> {{ $card['class_label'] }}</div>
                        <div><strong>Session:</strong> {{ $sessionName ?? 'N/A' }} - <strong>Term:</strong> {{ $termName ?? 'N/A' }}</div>
                        <!-- <div><strong>Term:</strong> {{ $termName ?? 'N/A' }}</div> -->
                    </div>
                    <div class="pin-code">{{ chunk_split($card['pin_code'], 4, ' ') }}</div>
                    <div class="expiry">Valid until: {{ $card['expires_at'] }}</div>
                    @php
                        $studentPortalLink = trim((string) ($studentPortalLink ?? ''));
                    @endphp
                    @if($studentPortalLink !== '')
                        <div class="portal-link">
                            <strong>Student portal link:</strong>
                            <a href="{{ $studentPortalLink }}" target="_blank" rel="noreferrer">
                                {{ $studentPortalLink }}
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endforeach
<div class="footer">
    Protect this card. Keep the PIN confidential. Scratch only when ready to check results.
</div>
@if(!empty($autoPrint))
<script>
    window.addEventListener('load', () => {
        try { window.print(); } catch (error) { console.error('Auto print failed', error); }
    });
</script>
@endif
</body>
</html>
