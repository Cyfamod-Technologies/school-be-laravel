<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadsheet – {{ $class?->name }}{{ $classArm ? ' ' . $classArm->name : '' }} – {{ $term?->name }} {{ $session?->name }}</title>
    <style>
        @page {
            size: A3 landscape;
            margin: 10mm 8mm;
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
            padding: 6mm 4mm;
        }

        .school-header {
            text-align: center;
            margin-bottom: 6px;
        }

        .school-header h1 {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .school-header h2 {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .school-header .class-label {
            font-size: 11px;
            font-weight: bold;
            margin-top: 2px;
        }

        .form-master-line {
            font-size: 9px;
            margin-top: 4px;
            text-align: left;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 6px;
        }

        th, td {
            border: 1px solid #000;
            padding: 2px 2px;
            text-align: center;
            vertical-align: middle;
            font-size: 8.5px;
            word-break: break-word;
        }

        /* Fixed-width columns */
        col.col-sno    { width: 24px; }
        col.col-admno  { width: 44px; }
        col.col-name   { width: 110px; }
        col.col-sex    { width: 22px; }
        col.col-subj   { width: 30px; }
        col.col-passes { width: 34px; }
        col.col-remark { width: 44px; }

        /* Rotated subject headers */
        th.rotated {
            height: 90px;
            white-space: nowrap;
            vertical-align: bottom;
            padding: 0;
        }

        th.rotated > span {
            display: inline-block;
            transform: rotate(-90deg);
            transform-origin: bottom center;
            width: 88px;
            text-align: left;
            padding-left: 4px;
            font-size: 8px;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        th.header-main {
            font-weight: bold;
            font-size: 9px;
        }

        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        td.name-cell {
            text-align: left;
            padding-left: 3px;
            font-size: 8.5px;
        }

        td.score-cell {
            font-size: 8.5px;
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
        <h2>{{ strtoupper($term?->name ?? '') }} BROADSHEET {{ $session?->name ?? '' }} ACADEMIC SESSION</h2>
        <div class="class-label">
            CLASS:&nbsp;
            <strong>
                {{ $class?->name ?? '' }}{{ $classArm ? ' ' . $classArm->name : '' }}
            </strong>
        </div>
    </div>

    <div class="form-master-line">FORM MASTER/MISTRESS: _______________________________</div>

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
                <th class="header-main" rowspan="2">S/NO</th>
                <th class="header-main" rowspan="2">ADM.NO</th>
                <th class="header-main" rowspan="2">NAME OF STUDENT</th>
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
                    <td colspan="{{ 5 + count($subjects) + 1 }}" style="text-align:center; padding: 12px;">
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

</body>
</html>
