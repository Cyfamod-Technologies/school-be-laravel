<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Class Results</title>
    <style>
        @include('partials.result-styles')

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

        .bulk-actions button {
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

        .bulk-empty {
            background: #fff7ed;
            border: 1px dashed #f97316;
            color: #9a3412;
            padding: 24px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }

        .page {
            margin-bottom: 24px;
        }

        @media print {
            body {
                padding: 0 !important;
                background: #ffffff;
            }

            .bulk-controls,
            .bulk-summary {
                display: none !important;
            }

            .page {
                box-shadow: none !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
                margin: 0 auto 24px auto !important;
            }

            .page:last-child {
                page-break-after: auto !important;
            }

            @page {
                size: A4 portrait;
                margin: 8mm;
            }
        }
    </style>
</head>
<body class="bulk-print">
    <div class="bulk-controls">
        <div>
            <h1>Bulk Result Printing</h1>
            <div style="font-size:14px;color:#475569;">
                Generate print-ready slips for every student in the selected class. Use “Export PDF” to open the print dialog and save the compiled pages as a PDF.
            </div>
        </div>
        <div class="bulk-actions">
            <button type="button" onclick="window.print()">Print</button>
            <button type="button" class="secondary" onclick="window.print()">Export PDF</button>
        </div>
    </div>

    <div class="bulk-summary">
        <div><strong>Session:</strong> {{ $filters['session'] ?? 'N/A' }}</div>
        <div><strong>Term:</strong> {{ $filters['term'] ?? 'N/A' }}</div>
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
        @include('partials.result-page', array_merge($page, ['showPrintButton' => false]))
    @empty
        <div class="bulk-empty">
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
