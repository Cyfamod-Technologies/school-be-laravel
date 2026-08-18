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
            direction: ltr;
            unicode-bidi: normal;
            letter-spacing: 0;
        }

        .student-meta-line--admission {
            white-space: nowrap;
            font-size: 10px;
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

        /* Keep the full report on one A4 sheet. The shared print rule makes
           every descendant unbreakable, which causes Dompdf to move this
           complete footer to a second page. */
        .page,
        .page-content,
        .page-main,
        .page-footer,
        .page-footer > .flex-row,
        .page-footer > .flex-row > .flex-col {
            page-break-before: auto !important;
            page-break-after: auto !important;
            page-break-inside: auto !important;
        }

        body {
            padding: 0 !important;
        }

        .page {
            padding: 4px 6px !important;
        }

        .school-heading {
            margin-top: 0 !important;
            margin-bottom: 2px !important;
        }

        .school-heading h1 {
            font-size: 16px !important;
            line-height: 1.15 !important;
        }

        .school-heading p {
            margin-top: 1px !important;
            font-size: 8.5px !important;
        }

        table {
            margin-bottom: 3px !important;
        }

        .table-one td {
            padding: 2px 3px !important;
            font-size: 8.5px !important;
            line-height: 1.25 !important;
        }

        .table-one .student-meta-line--admission {
            font-size: 8px !important;
        }

        .logo-cell,
        .logo-cell img {
            width: 74px !important;
        }

        .logo-cell img {
            height: 74px !important;
        }

        .photo-cell img {
            width: 64px !important;
            height: 76px !important;
        }

        .table-two th,
        .table-two td {
            padding: 3px 2px !important;
            font-size: 8px !important;
            line-height: 1.15 !important;
        }

        .section-title {
            margin-bottom: 2px !important;
            font-size: 9px !important;
        }

        .page-footer .grade-line,
        .page-footer .summary-box p,
        .page-footer .rating-key-table td,
        .page-footer .skill-table td {
            font-size: 7.5px !important;
            line-height: 1.15 !important;
        }

        .page-footer .rating-key-table td,
        .page-footer .skill-table td {
            padding: 1px 3px !important;
        }

        .page-footer .skill-card-title {
            padding: 2px 4px !important;
            font-size: 8px !important;
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
    @include('partials.result-page', ['showPrintButton' => false, 'shapeArabicForPdf' => true])
</body>
</html>
