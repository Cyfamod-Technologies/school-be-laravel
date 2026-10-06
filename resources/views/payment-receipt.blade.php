<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #1f2933; font-size: 12px; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header .school { display: table-cell; vertical-align: middle; }
        .header .school img { max-height: 56px; }
        .header .title { display: table-cell; text-align: right; vertical-align: middle; }
        .title h1 { font-size: 20px; margin: 0; color: #0f766e; }
        .title .ref { color: #6b7280; font-size: 11px; }
        .school-name { font-size: 16px; font-weight: bold; margin: 0; }
        .school-address { color: #6b7280; margin: 2px 0 0; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.meta td { padding: 4px 0; vertical-align: top; }
        table.meta td.label { color: #6b7280; width: 160px; }
        table.amount { width: 100%; border-collapse: collapse; margin: 18px 0; }
        table.amount td, table.amount th { border: 1px solid #d1d5db; padding: 8px 10px; text-align: left; }
        table.amount th { background: #f3f4f6; }
        .amount-value { text-align: right; font-weight: bold; }
        .total-row td { font-weight: bold; background: #f9fafb; }
        .footer { margin-top: 28px; color: #6b7280; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; background: #dcfce7; color: #166534; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">
            <p class="school-name">{{ $school->name ?? 'School' }}</p>
            @if(!empty($school->address))
                <p class="school-address">{{ $school->address }}</p>
            @endif
        </div>
        <div class="title">
            <h1>Payment Receipt</h1>
            <div class="ref">{{ $payment->receipt_number }}</div>
            <div class="ref">Reference: {{ $payment->reference }}</div>
        </div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Student</td>
            <td>{{ $studentName }} ({{ $student->admission_no }})</td>
            <td class="label">Class</td>
            <td>{{ $className }}</td>
        </tr>
        <tr>
            <td class="label">Session / Term</td>
            <td>{{ $payment->session?->name }} &mdash; {{ $payment->term?->name }}</td>
            <td class="label">Date Paid</td>
            <td>{{ optional($payment->paid_at)->format('j F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Method</td>
            <td>{{ $methodLabel }}</td>
            <td class="label">Status</td>
            <td><span class="badge">Verified</span></td>
        </tr>
        @if($payment->payer_reference)
        <tr>
            <td class="label">Bank Reference</td>
            <td colspan="3">{{ $payment->payer_reference }}</td>
        </tr>
        @endif
    </table>

    <table class="amount">
        <thead>
            <tr>
                <th>Applied To</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allocations as $allocation)
                <tr>
                    <td>{{ $allocation['name'] }}</td>
                    <td class="amount-value">{{ number_format((float) $allocation['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Not yet allocated to a specific fee.</td>
                </tr>
            @endforelse
            @if($unallocated !== '0.00')
                <tr>
                    <td>Unallocated credit</td>
                    <td class="amount-value">{{ number_format((float) $unallocated, 2) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td>Total Paid</td>
                <td class="amount-value">NGN {{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="footer">
        Verified by {{ $verifierName }} on {{ optional($payment->verified_at)->format('j F Y, g:i a') }}.
        This receipt confirms a verified payment recorded by {{ $school->name ?? 'the school' }}.
    </p>
</body>
</html>
