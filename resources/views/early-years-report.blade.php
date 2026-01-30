<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $schoolName }} | {{ $reportTitle ?? 'Early Years Report' }}</title>
    <style>
        @include('partials.result-styles')

        .early-years-table th {
            background: #0f172a;
            color: #ffffff;
            text-transform: uppercase;
            font-size: 12px;
        }

        .early-years-table td {
            font-size: 12px;
        }

        .category-cell {
            font-weight: 600;
            background: #f8fafc;
            width: 220px;
        }

        .skills-grading th {
            background: #0f172a;
            color: #ffffff;
            text-transform: uppercase;
            font-size: 12px;
        }

        .skills-grading td {
            text-align: center;
            font-size: 12px;
        }

        .report-footer {
            display: flex;
            gap: 24px;
            align-items: flex-start;
            justify-content: space-between;
            margin-top: 12px;
        }

        .comment-box {
            flex: 1;
            font-size: 13px;
        }

        .signature-box {
            min-width: 220px;
            text-align: right;
            font-size: 13px;
        }

        .signature-box img {
            max-height: 80px;
            width: auto;
            display: block;
            margin-left: auto;
        }

        .signature-line {
            border-bottom: 1px solid #94a3b8;
            width: 180px;
            margin-left: auto;
            margin-top: 20px;
        }
    </style>
</head>
<body>
@php
    $classLabel = trim(collect([$studentInfo['class'] ?? null, $studentInfo['class_arm'] ?? null])->filter()->implode(' '));
@endphp
<div class="page">
    @if(($showPrintButton ?? true))
        <div class="print-actions">
            <button id="print-button" type="button" onclick="window.print()">Print</button>
        </div>
    @endif

    <div class="school-heading">
        @php
            $schoolLines = preg_split('/<br\s*\/?>/i', (string) $schoolName) ?: [];
            $schoolLines = array_values(array_filter(array_map('trim', $schoolLines), fn ($line) => $line !== ''));
            if (empty($schoolLines)) {
                $schoolLines = [(string) $schoolName];
            }
        @endphp
        <h1>
            @foreach ($schoolLines as $index => $line)
                @if ($index > 0)
                    <br>
                @endif
                {{ strtoupper($line) }}
            @endforeach
        </h1>
        <p>
            @if(!empty($schoolAddress))
                {{ $schoolAddress }}
            @endif
            @if(!empty($schoolPhone))
                {{ !empty($schoolAddress) ? ' | ' : '' }}Phone: {{ $schoolPhone }}
            @endif
            @if(!empty($schoolEmail))
                {{ (!empty($schoolAddress) || !empty($schoolPhone)) ? ' | ' : '' }}Email: {{ $schoolEmail }}
            @endif
        </p>
    </div>

    <table class="table-one">
        <tr>
            <td class="logo-cell">
                @if($schoolLogoUrl)
                    <img src="{{ $schoolLogoUrl }}" alt="School logo">
                @else
                    <span class="placeholder">Logo</span>
                @endif
            </td>
            <td colspan="3" class="term-info">
                <strong>{{ $reportTitle ?? 'Early Years Report' }}</strong><br>
                @if($termStart && $termEnd)
                    Term Period: {{ $termStart }} - {{ $termEnd }}<br>
                @endif
                @if($nextTermStart)
                    Next resumption date: {{ $nextTermStart }}
                @endif
            </td>
            <td colspan="2" class="student-meta">
                Admission No.: {{ $studentInfo['admission_no'] ?? 'N/A' }}<br>
                Name: {{ $studentInfo['name'] ?? 'N/A' }}<br>
                Gender: {{ $studentInfo['gender'] ?? 'N/A' }}<br>
                Class: {{ $classLabel ?: 'N/A' }}
            </td>
            <td rowspan="2" class="photo-cell" align="center">
                @if($studentPhotoUrl)
                    <img src="{{ $studentPhotoUrl }}" alt="Student photo">
                @else
                    <span class="placeholder">Photo</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Session: {{ $sessionName ?? 'N/A' }}</td>
            <td>Term: {{ $termName ?? 'N/A' }}</td>
            <td>No. of Times School Opened: {{ $schoolOpenedDays ?? 'N/A' }}</td>
            <td>No. of Days Present: {{ $attendance['present'] ?? 'N/A' }}</td>
            <td>No. of Days Absent: {{ $attendance['absent'] ?? 'N/A' }}</td>
            <td>No. in Class: {{ $classSize ?: 'N/A' }}</td>
        </tr>
    </table>

    <table class="table-three skills-grading">
        <tr>
            <th colspan="5">Skills Grading</th>
        </tr>
        <tr>
            <td>Q1</td>
            <td>Q2</td>
            <td>Q3</td>
            <td>Q4</td>
            <td>Q5</td>
        </tr>
        <tr>
            <td>Weak</td>
            <td>Average</td>
            <td>Good</td>
            <td>Very Good</td>
            <td>Excellent</td>
        </tr>
    </table>

    <table class="table-three early-years-table">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Skills</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            @forelse($skillRatingsByCategory as $category)
                @php
                    $skills = $category['skills'] ?? [];
                    $skillCount = count($skills);
                @endphp
                @foreach($skills as $index => $skill)
                    <tr>
                        @if($index === 0)
                            <td class="category-cell" rowspan="{{ $skillCount }}">{{ $category['category'] }}</td>
                        @endif
                        <td>{{ $skill['skill'] }}</td>
                        <td>{{ $skill['grade'] ?? '-' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="3">No skill ratings recorded for the selected term.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="report-footer">
        <div class="comment-box">
            <strong>Class Teacher's Comment:</strong>
            {{ $teacherComment ?? 'No comment provided.' }}
            @if(!empty($classTeacherName))
                <div style="margin-top: 6px;">
                    <strong>Class Teacher:</strong> {{ $classTeacherName }}
                </div>
            @endif
        </div>
        <div class="signature-box">
            <strong>Director's Signature</strong>
            @if($directorSignatureUrl)
                <img src="{{ $directorSignatureUrl }}" alt="Director signature">
            @else
                <div class="signature-line"></div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
