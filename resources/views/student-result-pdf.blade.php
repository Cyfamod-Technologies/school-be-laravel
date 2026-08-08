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

        html,
        body,
        .page {
            font-family: "DejaVu Sans", sans-serif !important;
        }

        .school-heading h1,
        .school-name-line {
            font-family: "DejaVu Sans", sans-serif !important;
        }

        .school-name-line--rtl {
            direction: rtl;
            unicode-bidi: embed;
        }

        /* Dompdf does not consistently render flex columns. Use a table layout
           so Summary and Skills & Behaviour remain side-by-side on one page. */
        .page-footer > .flex-row {
            display: table !important;
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: separate !important;
            border-spacing: 4px 0 !important;
        }

        .page-footer > .flex-row > .flex-col {
            display: table-cell !important;
            width: 50% !important;
            vertical-align: top !important;
        }

        .page-footer .info-box,
        .page-footer .rating-key-table,
        .page-footer .skill-grid {
            margin-bottom: 3px !important;
        }

        .page-footer .skills-box {
            padding: 5px 7px !important;
        }

        .page-footer .summary-box p {
            margin: 1px 0 !important;
            line-height: 1.2 !important;
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

            .page-footer > .flex-row {
                display: table !important;
            }

            .page-footer > .flex-row > .flex-col {
                display: table-cell !important;
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
