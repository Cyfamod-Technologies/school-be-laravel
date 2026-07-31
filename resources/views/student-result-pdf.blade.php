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

        .print-actions {
            display: none;
        }
    </style>
</head>
<body>
    @include('partials.result-page', ['showPrintButton' => false])
</body>
</html>
