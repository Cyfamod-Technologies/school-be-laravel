<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTitle ?? 'Session Results' }}</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            background: #edf2f7;
            color: #0f172a;
        }

        .bulk-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            background: #ffffff;
            border-radius: 8px;
            padding: 16px 20px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.08);
        }

        .bulk-controls h1 {
            margin: 0;
            font-size: 22px;
        }

        .bulk-summary {
            background: #ffffff;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.05);
        }

        .bulk-summary strong {
            display: inline-block;
            min-width: 110px;
        }

        .bulk-actions {
            display: flex;
            gap: 12px;
        }

        .bulk-actions button,
        .session-print-actions button {
            background: #2563eb;
            border: none;
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }

        .bulk-actions button.secondary {
            background: #0f172a;
        }

        .session-page {
            background: #ffffff;
            border-radius: 8px;
            padding: 18px 22px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.05);
            margin-bottom: 24px;
            text-align: center;
        }

        .session-print-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
        }

        .session-header {
            display: flex;
            justify-content: center;
            gap: 20px;
            align-items: center;
            margin-bottom: 16px;
        }

        .session-brand {
            display: flex;
            flex-direction: row;
            gap: 12px;
            align-items: center;
            text-align: center;
        }

        .session-brand img {
            width: 84px;
            height: 84px;
            object-fit: contain;
        }

        .session-brand h1 {
            margin: 0;
            font-size: 22px;
            text-align: center;
        }

        .session-brand p {
            margin: 4px 0 6px;
            font-size: 12px;
            color: #475569;
            text-align: center;
        }

        .session-title {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .session-student-photo img {
            width: 86px;
            height: 104px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
        }

        .session-meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px 18px;
            margin-bottom: 18px;
            font-size: 13px;
            text-align: center;
            align-items: center;
        }

        .session-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .session-table th,
        .session-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 6px;
            font-size: 11px;
            text-align: center;
            white-space: nowrap;
        }

        .session-table th {
            background: #e2e8f0;
            color: #111827;
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.02em;
            line-height: 1.25;
        }

        .session-table .subject-name {
            font-weight: 600;
            white-space: normal;
            min-width: 160px;
        }

        .session-table .subject-label {
            display: block;
            position: relative;
            min-height: 1em;
        }

        .session-table .subject-number {
            position: absolute;
            left: 0;
            top: 0;
            width: 24px;
            text-align: left;
        }

        .session-table .subject-text {
            display: block;
            padding: 0 24px;
            text-align: center;
        }

        .session-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .session-skills-section {
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 10px;
            border: 1px solid #cbd5e1;
            padding: 10px;
            background: #ffffff;
            text-align: center;
        }

        .session-skills-section h2 {
            margin: 0 0 8px;
            font-size: 13px;
            text-transform: uppercase;
        }

        .session-skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
            gap: 6px;
            align-items: start;
        }

        .session-skill-category {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            min-width: 0;
            background: #ffffff;
        }

        .session-skill-category-title {
            margin-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            color: #1e293b;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.2;
        }

        .skill-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .skill-table td {
            padding: 2px;
            color: #334155;
            font-size: 10px;
            line-height: 1.2;
            text-align: left;
            overflow-wrap: anywhere;
        }

        .skill-table td:last-child {
            width: 24px;
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        .session-summary-card {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 16px;
            background: #f8fafc;
            text-align: center;
        }

        .session-summary-card h2 {
            margin: 0 0 10px;
            font-size: 14px;
            text-transform: uppercase;
        }

        .session-summary-card p {
            margin: 6px 0;
            font-size: 12px;
            line-height: 1.5;
        }

        .session-signature img {
            max-height: 50px;
            width: auto;
            margin-top: 8px;
        }

        @media print {
            body {
                padding: 0 !important;
                background: #ffffff;
            }

            .bulk-controls,
            .bulk-summary,
            .session-print-actions {
                display: none !important;
            }

            .session-page {
                box-shadow: none !important;
                page-break-after: always !important;
                break-after: page !important;
                margin: 0 !important;
                padding: 0 !important;
                border-radius: 0;
            }

            .session-page:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            .session-table th {
                background: #e2e8f0 !important;
                color: #111827 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .session-header {
                margin-bottom: 8px;
            }

            .session-meta-grid {
                gap: 4px 12px;
                margin-bottom: 8px;
            }

            .session-table {
                margin-bottom: 8px;
            }

            .session-table th,
            .session-table td {
                padding: 3px 4px;
                font-size: 9px;
                line-height: 1.15;
            }

            .session-table th {
                font-size: 9px;
            }

            .session-skills-section {
                margin-bottom: 6px;
                padding: 6px;
            }

            .session-skills-section,
            .session-skill-category,
            .session-final-summary,
            .session-summary-card {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .session-skills-section h2 {
                margin-bottom: 5px;
                font-size: 11px;
            }

            .session-skills-grid {
                grid-template-columns: repeat(auto-fit, minmax(125px, 1fr));
                gap: 4px;
            }

            .session-skill-category {
                padding: 4px 6px;
            }

            .session-summary-grid {
                gap: 8px;
            }

            .session-summary-card {
                padding: 7px 9px;
                border-radius: 0;
            }

            .session-summary-card h2 {
                margin-bottom: 4px;
                font-size: 11px;
            }

            .session-summary-card p {
                margin: 2px 0;
                font-size: 9px;
                line-height: 1.25;
            }

            .session-signature img {
                max-height: 34px;
                margin-top: 3px;
            }

            @page {
                size: A4 landscape;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>
    <div class="bulk-controls">
        <div>
            <h1>Session Result Printing</h1>
            <div style="font-size:14px;color:#475569;">
                Generate the full-session result sheet for each student in the selected class.
            </div>
        </div>
        <div class="bulk-actions">
            <button type="button" onclick="window.print()">Print</button>
            <button type="button" class="secondary" onclick="window.print()">Export PDF</button>
        </div>
    </div>

    <div class="bulk-summary">
        <div><strong>Session:</strong> {{ $filters['session'] ?? 'N/A' }}</div>
        <div><strong>Class:</strong> {{ $filters['class'] ?? 'N/A' }}</div>
        @if(!empty($filters['class_arm']))
            <div><strong>Class Arm:</strong> {{ $filters['class_arm'] }}</div>
        @endif
        @if(!empty($filters['class_section']))
            <div><strong>Section:</strong> {{ $filters['class_section'] }}</div>
        @endif
        <div><strong>Students:</strong> {{ $filters['student_count'] ?? 0 }}
            @if(isset($filters['total_students']) && $filters['total_students'] > ($filters['student_count'] ?? 0))
                <span style="color:#dc2626;font-size:13px;"> ({{ $filters['total_students'] - ($filters['student_count'] ?? 0) }} student(s) skipped - no results found)</span>
            @endif
        </div>
        <div><strong>Generated:</strong> {{ $generatedAt }}</div>
    </div>

    @forelse($pages as $page)
        @include('partials.session-result-page', array_merge($page, ['showPrintButton' => false]))
    @empty
        <div class="bulk-summary">
            No students were found for the selected filters.
        </div>
    @endforelse

    @if(request()->boolean('autoprint'))
        <script>
            window.addEventListener('load', () => {
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
