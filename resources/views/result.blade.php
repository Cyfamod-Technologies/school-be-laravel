<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $schoolName }} | Result Slip</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            background: #f5f6f8;
            color: #212529;
        }

        .page {
            max-width: 1080px;
            margin: 0 auto;
            background: #ffffff;
            padding: 24px 32px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
        }

        #print-button {
            background: #1f7a8c;
            border: none;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
        }

        #print-button:hover {
            background: #155b6b;
        }

        .school-heading {
            text-align: center;
            margin-top: 8px;
            margin-bottom: 16px;
        }

        .school-heading h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: 1px;
        }

        .school-heading p {
            margin: 6px 0 0;
            font-size: 14px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .table-one td,
        .table-two th,
        .table-two td,
        .table-three td {
            border: 1px solid #d0d5dd;
            padding: 8px 10px;
            vertical-align: top;
        }

        .table-one td {
            font-size: 14px;
        }

        .logo-cell {
            width: 140px;
            text-align: center;
        }

        .logo-cell img {
            width: 120px;
            height: 120px;
            object-fit: contain;
        }

        .placeholder {
            display: inline-flex;
            width: 120px;
            height: 120px;
            align-items: center;
            justify-content: center;
            border: 1px dashed #94a3b8;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            background: #f1f5f9;
        }

        .term-info {
            font-size: 14px;
            line-height: 1.5;
        }

        .student-meta {
            font-size: 13px;
            line-height: 1.6;
        }

        .photo-cell img {
            width: 120px;
            height: 140px;
            object-fit: cover;
            border-radius: 4px;
        }

        .table-two th {
            background: #0f172a;
            color: #ffffff;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.3px;
            text-align: center;
        }

        .table-two td {
            text-align: center;
            font-size: 13px;
        }

        .subject-name {
            text-align: left;
            font-weight: 600;
        }

        .table-three td {
            font-size: 13px;
        }

        .section-title {
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 13px;
            color: #0f172a;
        }

        .flex-row {
            display: flex;
            gap: 24px;
        }

        .flex-col {
            flex: 1;
        }

        .signature-box {
            margin-top: 24px;
        }

        .signature-box img {
            max-width: 160px;
            height: auto;
        }

        .info-box {
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            padding: 12px 16px;
            background: #fdfdfd;
            margin-top: 12px;
        }

        .skill-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .skill-card {
            flex: 1 1 45%;
            min-width: 240px;
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            overflow: hidden;
            background: #ffffff;
        }

        .skill-card-title {
            background: #0f172a;
            color: #ffffff;
            padding: 8px 12px;
            font-size: 12px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .skill-table {
            width: 100%;
            border-collapse: collapse;
        }

        .skill-table td {
            border: 1px solid #d0d5dd;
            padding: 6px 10px;
            font-size: 13px;
        }

        .skill-table td:first-child {
            font-weight: 500;
            color: #1e293b;
        }

        .grade-line {
            font-size: 13px;
            color: #0f172a;
            font-weight: 600;
            text-transform: uppercase;
        }

        .grade-line span {
            display: block;
            margin-top: 6px;
            font-weight: 400;
            text-transform: none;
            font-size: 12px;
            color: #475569;
        }

        .rating-key-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .rating-key-table td {
            border: 1px solid #d0d5dd;
            padding: 6px 10px;
            font-size: 12px;
        }

        .rating-key-table td:first-child {
            width: 18%;
            text-align: center;
            font-weight: 600;
        }

        .rating-key-table tr:first-child td:first-child {
            width: auto;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .page {
                box-shadow: none;
            }

            #print-button {
                display: none;
            }
        }
    </style>
</head>
@php
    $classLabel = trim(collect([$studentInfo['class'] ?? null, $studentInfo['class_arm'] ?? null])->filter()->implode(' '));
@endphp
<body>
    <div class="page">
        <div class="print-actions">
            <button id="print-button" type="button" onclick="window.print()">Print</button>
        </div>

        <div class="school-heading">
            <h1>{{ strtoupper($schoolName) }}</h1>
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
                    <strong>End of Term Report</strong><br>
                    @if($termStart && $termEnd)
                        Term Period: {{ $termStart }} - {{ $termEnd }}<br>
                    @endif
                    @if($nextTermStart)
                        Next term begins: {{ $nextTermStart }}
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
                <td>Report Date: {{ $reportDate }}</td>
                <td>No. of Days Present: {{ $attendance['present'] ?? 'N/A' }}</td>
                <td>No. of Days Absent: {{ $attendance['absent'] ?? 'N/A' }}</td>
                <td>No. in Class: {{ $classSize ?: 'N/A' }}</td>
            </tr>
        </table>

        <table class="table-two">
            <tr>
                <th>Subject</th>
                @foreach($resultsColumns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
                <th>Total Marks</th>
                <th>Grade</th>
                <th>Position</th>
                <th>Class Average</th>
                <th>Lowest</th>
                <th>Highest</th>
            </tr>
            @forelse($resultsRows as $row)
                <tr>
                    <td class="subject-name">{{ $row['subject_name'] }}</td>
                    @foreach($resultsColumns as $column)
                        @php
                            $value = $row['component_values'][$column['id']] ?? null;
                        @endphp
                        <td>{{ $value !== null ? number_format($value, 0) : '-' }}</td>
                    @endforeach
                    <td>{{ $row['total'] !== null ? number_format($row['total'], 0) : '-' }}</td>
                    <td>{{ $row['grade'] ?? '-' }}</td>
                    <td>{{ $row['position'] ?? '-' }}</td>
                    <td>{{ $row['class_average'] !== null ? number_format($row['class_average'], 1) : '-' }}</td>
                    <td>{{ $row['lowest'] !== null ? number_format($row['lowest'], 1) : '-' }}</td>
                    <td>{{ $row['highest'] !== null ? number_format($row['highest'], 1) : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 7 + count($resultsColumns) }}">No subject results available for the selected period.</td>
                </tr>
            @endforelse
        </table>

        <div class="flex-row">
            <div class="flex-col">
                <div class="section-title">Grading System</div>
                <div class="info-box">
                    @php
                        $formatScore = function ($value) {
                            $formatted = number_format($value ?? 0, 2);
                            return rtrim(rtrim($formatted, '0'), '.');
                        };

                        $gradeLine = collect($gradeRanges ?? [])->map(function ($range) use ($formatScore) {
                            $label = strtoupper($range['label'] ?? '');
                            $min = $formatScore($range['min'] ?? 0);
                            $max = $formatScore($range['max'] ?? 0);
                            $description = strtoupper($range['description'] ?? '');
                            return $description
                                ? "{$label} = {$min} - {$max} [{$description}]"
                                : "{$label} = {$min} - {$max}";
                        })->implode(' , ');
                    @endphp
                    <div class="grade-line">
                        KEY TO GRADINGS
                        <span>{{ !empty($gradeLine) ? $gradeLine : 'No grading scale configured.' }}</span>
                    </div>
                </div>
                   <table class="rating-key-table">
                        <tr>
                            <td colspan="2" style="font-weight:600;text-transform:uppercase;text-align:center;background:#0f172a;color:#ffffff;">Key to Ratings</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td>Excellent Degree of Observable Trait</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>Good Level of Observable Trait</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>Fair But Acceptable Level of Observable Trait</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Poor Level of Observable Trait</td>
                        </tr>
                        <tr>
                            <td>1</td>
                            <td>No Observable Trait</td>
                        </tr>
                    </table>
            </div>
            <div class="flex-col">
                <div class="section-title">Skills &amp; Behaviour</div>
                <div class="info-box" style="padding:18px 20px;">
                    @if(!empty($skillRatingsByCategory))
                        @php
                            $skillChunks = array_chunk($skillRatingsByCategory, 2);
                        @endphp
                        @foreach($skillChunks as $chunk)
                            <div class="skill-grid" style="margin-bottom:16px;">
                                @foreach($chunk as $category)
                                    <div class="skill-card">
                                        <div class="skill-card-title">{{ strtoupper($category['category']) }}</div>
                                        <table class="skill-table">
                                            @foreach($category['skills'] as $skill)
                                                <tr>
                                                    <td>{{ $skill['skill'] }}</td>
                                                    <td width="80" align="center">{{ $skill['value'] !== null ? number_format($skill['value'], 0) : '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                @endforeach
                                @if(count($chunk) === 1)
                                    <div class="skill-card" style="visibility:hidden;"></div>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <p style="margin:0;">No skill ratings recorded.</p>
                    @endif
                 
                </div>
            </div>
        </div>

        <div class="flex-row">
            <div class="flex-col">
                <div class="section-title">Class Teacher Comment</div>
                <p>{{ $aggregate['class_teacher_comment'] ?? 'No comment provided.' }}</p>
                @if(!empty($classTeacherName))
                    <p><strong>Class Teacher:</strong> {{ $classTeacherName }}</p>
                @endif
            </div>
            <div class="flex-col">
                <div class="section-title">Principal Comment</div>
                <p>{{ $aggregate['principal_comment'] ?? 'No comment provided.' }}</p>
                @if(!empty($aggregate['final_grade']))
                    <p><strong>Final Grade:</strong> {{ $aggregate['final_grade'] }}</p>
                @endif
                @if(!empty($principalName))
                    <p><strong>Signed:</strong> {{ $principalName }}</p>
                @endif
                <!-- @if(!empty($principalSignatureUrl))
                    <div style="margin-top: 10px;">
                        <img src="{{ $principalSignatureUrl }}" alt="Principal signature" style="max-height:70px;width:auto;">
                    </div>
                @endif -->
            </div>
        </div>

        <div class="flex-row signature-box">
            <div class="flex-col">
                <div class="info-box">
                <div class="section-title">Summary</div>
                <p>Marks Obtainable: {{ $aggregate['total_possible'] !== null ? number_format($aggregate['total_possible'], 0) : '-' }}</p>
                <p>Marks Obtained: {{ $aggregate['total_obtained'] !== null ? number_format($aggregate['total_obtained'], 0) : '-' }}</p>
                <p>Average: {{ $aggregate['average'] !== null ? number_format($aggregate['average'], 2) : '-' }}</p>
                <p>Class Average: {{ $aggregate['class_average'] !== null ? number_format($aggregate['class_average'], 2) : '-' }}</p>
                <p>Position: {{ $aggregate['position'] !== null ? $aggregate['position'] . ' of ' . ($classSize ?: 'N/A') : '-' }}</p>
            </div>
            </div>
            <!-- <div class="flex-col" style="text-align: right;">
                <div class="section-title">School Logo</div>
                @php($schoolLogoUrl = optional($student->school)->logo_url)
                @if(!empty($schoolLogoUrl))
                    <img src="{{ $schoolLogoUrl }}" alt="School logo" style="max-height:70px;width:auto;">
                @endif
            </div> -->
        </div>
    </div>
</body>
</html>
