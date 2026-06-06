<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadsheet – {{ $class?->name }}{{ $classArm ? ' ' . $classArm->name : '' }} – {{ $term?->name }} {{ $session?->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            background: #fff;
            color: #000;
        }

        .no-print {
            display: flex;
            gap: 10px;
            padding: 12px 16px;
            background: #f0f4f8;
            border-bottom: 1px solid #ccc;
        }

        .no-print button {
            padding: 8px 18px;
            background: #1a56db;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .no-print button:hover { background: #1240a8; }

        .page {
            padding: 4mm 3mm;
        }

        .school-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .school-header h1 {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .school-header h2 {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .school-header .class-label {
            font-size: 10px;
            font-weight: bold;
            margin-top: 2px;
        }

        .broadsheet-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6px;
            font-size: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 4px;
        }

        th, td {
            border: 1px solid #000;
            padding: 1px 2px;
            text-align: center;
            vertical-align: middle;
            font-size: 7px;
            word-break: break-word;
        }

        col.col-sno    { width: 22px; }
        col.col-admno  { width: 46px; }
        col.col-name   { width: 120px; }
        col.col-sex    { width: 22px; }
        col.col-subj   { width: 22px; }
        col.col-passes { width: 34px; }
        col.col-remark { width: 44px; }

        th.rotated {
            height: 92px;
            white-space: nowrap;
            vertical-align: bottom;
            padding: 0;
        }

        th.rotated > span {
            display: inline-block;
            transform: rotate(-90deg);
            transform-origin: bottom center;
            width: 86px;
            text-align: left;
            padding-left: 4px;
            font-size: 7px;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        th.header-main {
            font-weight: bold;
            font-size: 7px;
        }

        thead {
            display: table-header-group;
        }

        td.name-cell {
            text-align: left;
            padding-left: 3px;
            font-size: 7px;
        }

        td.score-cell {
            font-size: 7px;
            height: 18px;
        }

        .footer {
            margin-top: 8px;
            font-size: 8px;
            text-align: right;
            color: #555;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .page { padding: 0; }
            tr, td, th {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">&#128438; Print Broadsheet</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="page">
    <div class="school-header">
        <h1>{{ strtoupper($school?->name ?? 'School Name') }}</h1>
        <h2>{{ strtoupper($term?->name ?? '') }} TERM BROADSHEET {{ $session?->name ?? '' }} ACADEMIC SESSION</h2>
        <div class="class-label">
            CLASS:&nbsp;
            <strong>
                {{ $class?->name ?? '' }}{{ $classArm ? ' ' . $classArm->name : '' }}
            </strong>
        </div>
    </div>

    <div class="broadsheet-meta">
        <div>Students: <strong>{{ $rows->count() }}</strong></div>
        <div>Generated: <strong>{{ $generatedAt ?? now()->format('d/m/Y H:i') }}</strong></div>
    </div>

    <table>
        <colgroup>
            <col class="col-sno">
            <col class="col-admno">
            <col class="col-name">
            <col class="col-sex">
            @foreach ($subjects as $subject)
                <col class="col-subj">
            @endforeach
            <col class="col-passes">
            <col class="col-remark">
        </colgroup>
        <thead>
            <tr>
                <th class="header-main" rowspan="2">S/N</th>
                <th class="header-main" rowspan="2">ADM NO</th>
                <th class="header-main" rowspan="2">NAME</th>
                <th class="header-main" rowspan="2">SEX</th>
                @foreach ($subjects as $subject)
                    <th class="rotated">
                        <span title="{{ $subject->name }}">{{ strtoupper($subject->name) }}</span>
                    </th>
                @endforeach
                <th class="rotated">
                    <span>NO. OF PASSES</span>
                </th>
                <th class="header-main" rowspan="2">REMARK</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $student = $row['student'];
                    $fullName = collect([$student->last_name, $student->first_name, $student->middle_name])
                        ->filter()
                        ->implode(', ');
                    $sex = strtoupper(substr((string) ($student->gender ?? ''), 0, 1));
                @endphp
                <tr>
                    <td>{{ $row['sno'] }}</td>
                    <td>{{ $student->admission_no ?? '' }}</td>
                    <td class="name-cell">{{ $fullName }}</td>
                    <td>{{ $sex }}</td>
                    @foreach ($row['scores'] as $score)
                        <td class="score-cell">{{ $score['score'] }}</td>
                    @endforeach
                    <td>{{ $row['passes'] > 0 ? $row['passes'] : '' }}</td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 6 + count($subjects) }}" style="text-align:center; padding: 12px;">
                        No students found for this class.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated: {{ now()->format('d/m/Y H:i') }}
        &nbsp;|&nbsp;
        {{ $session?->name }} &ndash; {{ $term?->name }}
        &nbsp;|&nbsp;
        {{ $rows->count() }} student(s)
    </div>
</div>

@if(request()->boolean('autoprint'))
<script>
    window.addEventListener('load', function () {
        try {
            window.print();
        } catch (error) {
            console.error('Unable to trigger print dialog automatically', error);
        }
    });
</script>
@endif

</body>
</html>
