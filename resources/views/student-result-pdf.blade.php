<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ($studentInfo['name'] ?? 'Student') . ' Result' }}</title>
    <style>
        @include('partials.result-styles')

        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        @media print {
            html,
            body {
                height: auto !important;
            }

            .page {
                min-height: 0 !important;
                height: auto !important;
            }

            .page-spacer {
                display: none !important;
            }
        }

        .print-actions {
            display: none;
        }
    </style>
</head>
<body>
    @include('partials.result-page', ['showPrintButton' => false])
</body>
</html>
