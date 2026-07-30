<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ($studentInfo['name'] ?? 'Student') . ' Result' }}</title>
    <style>
        @include('partials.result-styles')

        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            height: auto;
            overflow: visible;
        }

        .page {
            width: 100%;
            max-width: none;
            min-height: 0;
            max-height: none;
            margin: 0;
            padding: 0;
            overflow: visible;
            box-shadow: none;
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
