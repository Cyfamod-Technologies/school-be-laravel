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
                        <strong>KEY TO GRADINGS:</strong> {{ !empty($gradeLine) ? $gradeLine : 'No grading scale configured.' }}
                    </div>
                </div>
                   <table class="rating-key-table">
                        <tr>
                            <td colspan="2" style="font-weight:bold;text-transform:uppercase;text-align:center;background:#0f172a;color:#ffffff;">Key to Ratings</td>
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

                    <div class="info-box summary-box" style="margin-top: 8px;">
                        <div class="section-title">Summary</div>
                        <p>Marks Obtainable: {{ $aggregate['total_possible'] !== null ? number_format($aggregate['total_possible'], 0) : '-' }}</p>
                        <p>Marks Obtained: {{ $aggregate['total_obtained'] !== null ? number_format($aggregate['total_obtained'], 0) : '-' }}</p>
                        <p>Average: {{ $aggregate['average'] !== null ? number_format($aggregate['average'], 2) : '-' }}</p>
                        <p>Class Average: {{ $aggregate['class_average'] !== null ? number_format($aggregate['class_average'], 2) : '-' }}</p>
                        <p>Position: {{ $aggregate['position'] !== null ? $aggregate['position'] . ' of ' . ($classSize ?: 'N/A') : '-' }}</p>
                        <p>Class Teacher Comment : {{ $aggregate['class_teacher_comment'] ?? 'No comment provided.' }}</p>
                        @if(!empty($classTeacherName))
                            <p><strong>Class Teacher:</strong> {{ $classTeacherName }}</p>
                        @endif
                        <p>Principal Comment : {{ $aggregate['principal_comment'] ?? 'No comment provided.' }}</p>
                        @if(!empty($aggregate['final_grade']))
                            <p><strong>Final Grade:</strong> {{ $aggregate['final_grade'] }}</p>
                        @endif
                        @if(!empty($principalName))
                            <p><strong>Signed:</strong> {{ $principalName }}</p>
                        @endif
                        @if(!empty($principalSignatureUrl))
                            <div style="margin-top: 8px; display: flex; align-items: center; gap: 12px;">
                                <span style="font-weight: 500;">Principal signature:</span>
                                <img src="{{ $principalSignatureUrl }}" alt="Principal signature" style="max-height:50px;width:auto;">
                            </div>
                        @endif
                    </div>

            </div>
            <div class="flex-col">
                <div class="section-title">Skills &amp; Behaviour</div>
                <div class="info-box" style="padding:10px 14px;">
                    @if(!empty($skillRatingsByCategory))
                        @php
                            $skillChunks = array_chunk($skillRatingsByCategory, 2);
                        @endphp
                        @foreach($skillChunks as $chunk)
                            <div class="skill-grid" style="margin-bottom:10px;">
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

        </div>
    </div>
