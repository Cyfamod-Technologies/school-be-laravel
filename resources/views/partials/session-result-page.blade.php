@php
    $classLabel = trim(collect([$studentInfo['class'] ?? null, $studentInfo['class_arm'] ?? null])->filter()->implode(' '));
    $schoolLines = preg_split('/<br\s*\/?>/i', (string) ($schoolName ?? '')) ?: [];
    $schoolLines = array_values(array_filter(array_map('trim', $schoolLines), fn ($line) => $line !== ''));
    if (empty($schoolLines)) {
        $schoolLines = [(string) ($schoolName ?? 'School')];
    }

    $resultPageSettings = $resultPageSettings ?? [];
    $showGrade = $resultPageSettings['show_grade'] ?? true;
    $showPosition = $resultPageSettings['show_position'] ?? true;
    $showClassAverage = $resultPageSettings['show_class_average'] ?? true;
    $showLowest = $resultPageSettings['show_lowest'] ?? true;
    $showHighest = $resultPageSettings['show_highest'] ?? true;
    $showRemarks = $resultPageSettings['show_remarks'] ?? true;
    $termSections = $termSections ?? [];

    $termExtraColumnCount = 1
        + ($showGrade ? 1 : 0)
        + ($showPosition ? 1 : 0)
        + ($showClassAverage ? 1 : 0)
        + ($showLowest ? 1 : 0)
        + ($showHighest ? 1 : 0)
        + ($showRemarks ? 1 : 0);

    $annualColumnCount = 5;
    $tableColspan = 1 + collect($termSections)->sum(fn ($section) => count($section['columns'] ?? []) + $termExtraColumnCount) + $annualColumnCount;
@endphp

<div class="session-page">
    @if(($showPrintButton ?? true))
        <div class="session-print-actions">
            <button id="print-button" type="button" onclick="window.print()">Print</button>
        </div>
    @endif

    <div class="session-header">
        <div class="session-brand">
            @if($schoolLogoUrl)
                <img src="{{ $schoolLogoUrl }}" alt="School logo">
            @endif
            <div>
                <h1>
                    @foreach ($schoolLines as $index => $line)
                        @if ($index > 0)
                            <br>
                        @endif
                        {{ strtoupper($line) }}
                    @endforeach
                </h1>
                <p>
                    {{ $schoolAddress ?? '' }}
                    @if(!empty($schoolPhone))
                        {{ !empty($schoolAddress) ? ' | ' : '' }}{{ $schoolPhone }}
                    @endif
                    @if(!empty($schoolEmail))
                        {{ (!empty($schoolAddress) || !empty($schoolPhone)) ? ' | ' : '' }}{{ $schoolEmail }}
                    @endif
                </p>
                <div class="session-title">Session Result Sheet (3rd Term)</div>
            </div>
        </div>
        <div class="session-student-photo">
            @if($studentPhotoUrl)
                <img src="{{ $studentPhotoUrl }}" alt="Student photo">
            @endif
        </div>
    </div>

    <div class="session-meta-grid">
        <div><strong>Student:</strong> {{ $studentInfo['name'] ?? 'N/A' }}</div>
        <div><strong>Admission No:</strong> {{ $studentInfo['admission_no'] ?? 'N/A' }}</div>
        <div><strong>Gender:</strong> {{ $studentInfo['gender'] ?? 'N/A' }}</div>
        <div><strong>Class:</strong> {{ $classLabel ?: 'N/A' }}</div>
        <div><strong>Session:</strong> {{ $sessionName ?? 'N/A' }}</div>
        <div><strong>No. in Class:</strong> {{ $classSize ?: 'N/A' }}</div>
    </div>

    <table class="session-table">
        <thead>
            <tr>
                <th rowspan="2">Subject</th>
                @foreach($termSections as $section)
                    <th colspan="{{ count($section['columns'] ?? []) + $termExtraColumnCount }}">{{ strtoupper($section['label'] ?? 'Term') }}</th>
                @endforeach
                <th colspan="{{ $annualColumnCount }}">Annual</th>
            </tr>
            <tr>
                @foreach($termSections as $section)
                    @foreach(($section['columns'] ?? []) as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th>Total</th>
                    @if($showGrade)
                        <th>Grade</th>
                    @endif
                    @if($showHighest)
                        <th>Highest</th>
                    @endif
                    @if($showLowest)
                        <th>Lowest</th>
                    @endif
                    @if($showClassAverage)
                        <th>Average</th>
                    @endif
                    @if($showPosition)
                        <th>Position</th>
                    @endif
                    @if($showRemarks)
                        <th>Remark</th>
                    @endif
                @endforeach
                <th>1st</th>
                <th>2nd</th>
                <th>3rd</th>
                <th>Total</th>
                <th>Avg</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resultsRows as $row)
                <tr>
                    <td class="subject-name">{{ $row['subject_name'] }}</td>
                    @foreach($termSections as $section)
                        @php
                            $termNumber = $section['number'] ?? null;
                            $termRow = $termNumber !== null ? ($row['per_term'][$termNumber] ?? null) : null;
                        @endphp
                        @foreach(($section['columns'] ?? []) as $column)
                            @php
                                $value = $termRow['component_values'][$column['id']] ?? null;
                            @endphp
                            <td>{{ $value !== null ? number_format($value, 0) : '-' }}</td>
                        @endforeach
                        <td>{{ isset($termRow['total']) && $termRow['total'] !== null ? number_format($termRow['total'], 0) : '-' }}</td>
                        @if($showGrade)
                            <td>{{ $termRow['grade'] ?? '-' }}</td>
                        @endif
                        @if($showHighest)
                            <td>{{ isset($termRow['highest']) && $termRow['highest'] !== null ? number_format($termRow['highest'], 0) : '-' }}</td>
                        @endif
                        @if($showLowest)
                            <td>{{ isset($termRow['lowest']) && $termRow['lowest'] !== null ? number_format($termRow['lowest'], 0) : '-' }}</td>
                        @endif
                        @if($showClassAverage)
                            <td>{{ isset($termRow['class_average']) && $termRow['class_average'] !== null ? number_format($termRow['class_average'], 0) : '-' }}</td>
                        @endif
                        @if($showPosition)
                            <td>{{ $termRow['position'] ?? '-' }}</td>
                        @endif
                        @if($showRemarks)
                            <td>{{ $termRow['remarks'] ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td>{{ isset($row['annual']['first']) && $row['annual']['first'] !== null ? number_format($row['annual']['first'], 0) : '-' }}</td>
                    <td>{{ isset($row['annual']['second']) && $row['annual']['second'] !== null ? number_format($row['annual']['second'], 0) : '-' }}</td>
                    <td>{{ isset($row['annual']['third']) && $row['annual']['third'] !== null ? number_format($row['annual']['third'], 0) : '-' }}</td>
                    <td>{{ isset($row['annual']['total']) && $row['annual']['total'] !== null ? number_format($row['annual']['total'], 0) : '-' }}</td>
                    <td>{{ isset($row['annual']['average']) && $row['annual']['average'] !== null ? number_format($row['annual']['average'], 2) : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $tableColspan }}">No session results available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @php
        $skillRatingsByCategory = $skillRatingsByCategory ?? [];
        $hasSkillRatings = !empty($skillRatingsByCategory);
    @endphp

    @if($hasSkillRatings)
        <div class="session-summary-grid">
            <div class="session-summary-card" style="width: 100%; max-width: none;">
                <h2 style="margin-bottom: 12px;">Skills &amp; Behaviour (3rd Term)</h2>
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    @foreach($skillRatingsByCategory as $category)
                        <div style="flex: 1; min-width: 250px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px;">
                            <div style="font-weight: 700; color: #1e293b; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                                {{ strtoupper($category['category']) }}
                            </div>
                            <table class="skill-table" style="width: 100%; border-collapse: collapse;">
                                @foreach($category['skills'] as $skill)
                                    <tr>
                                        <td style="padding: 4px 0; font-size: 13px; color: #334155;">{{ $skill['skill'] }}</td>
                                        <td style="padding: 4px 0; font-size: 13px; font-weight: 700; text-align: right; color: #0f172a;">
                                            {{ $skill['rating'] !== null ? number_format($skill['rating'], 0) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="session-summary-grid">
        <div class="session-summary-card">
            <h2>Summary</h2>
            <p><strong>Subjects:</strong> {{ $aggregate['subject_count'] ?? 0 }}</p>
            <p><strong>Total Obtained:</strong> {{ $aggregate['total_obtained'] !== null ? number_format($aggregate['total_obtained'], 0) : '-' }}</p>
            <p><strong>Total Possible:</strong> {{ $aggregate['total_possible'] !== null ? number_format($aggregate['total_possible'], 0) : '-' }}</p>
            <p><strong>Average:</strong> {{ $aggregate['average'] !== null ? number_format($aggregate['average'], 2) : '-' }}</p>
            @if($showClassAverage)
                <p><strong>Class Average:</strong> {{ $aggregate['class_average'] !== null ? number_format($aggregate['class_average'], 2) : '-' }}</p>
            @endif
            @if($showPosition)
                <p><strong>Position:</strong> {{ $aggregate['position'] !== null ? $aggregate['position'] . ' of ' . ($classSize ?: 'N/A') : '-' }}</p>
            @endif
        </div>
        <div class="session-summary-card">
            <h2>Comments</h2>
            <p><strong>Class Teacher:</strong> {{ $aggregate['class_teacher_comment'] ?? 'No comment provided.' }}</p>
            <p><strong>{{ $signatoryLabel }}:</strong> {{ $aggregate['principal_comment'] ?? 'No comment provided.' }}</p>
            @if(!empty($principalName))
                <p><strong>Signed:</strong> {{ $principalName }}</p>
            @endif
            @if(!empty($principalSignatureUrl))
                <div class="session-signature">
                    <img src="{{ $principalSignatureUrl }}" alt="{{ $signatoryLabel }} signature">
                </div>
            @endif
        </div>
    </div>
</div>
