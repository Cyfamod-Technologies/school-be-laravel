        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            background: #f5f6f8;
            color: #212529;
            height: 100vh;
            overflow: hidden;
        }

        .page {
            max-width: 1080px;
            margin: 0 auto;
            background: #ffffff;
            padding: 16px 24px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            overflow-x: hidden;
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
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .school-heading h1 {
            margin: 0;
            font-size: 24px;
            letter-spacing: 1px;
        }

        .school-heading p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .table-one td,
        .table-two th,
        .table-two td,
        .table-three td {
            border: 1px solid #d0d5dd;
            padding: 6px 8px;
            vertical-align: top;
        }

        .table-one td {
            font-size: 13px;
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
            font-size: 11px;
            letter-spacing: 0.3px;
            text-align: center;
            font-weight: bold;
        }

        .table-two td {
            text-align: center;
            font-size: 12px;
        }

        .subject-name {
            text-align: left;
            font-weight: 600;
        }

        .table-three td {
            font-size: 13px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 6px;
            text-transform: uppercase;
            font-size: 12px;
            color: #0f172a;
        }

        .flex-row {
            display: flex;
            gap: 16px;
        }

        .flex-col {
            flex: 1;
        }

        .signature-box {
            margin-top: 6px;
        }

        .signature-box img {
            max-width: 160px;
            height: auto;
        }

        .summary-box {
            max-width: 100%;
        }

        .summary-box p {
            margin: 4px 0;
            font-size: 12px;
            line-height: 1.4;
        }

        .info-box {
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            padding: 8px 12px;
            background: #fdfdfd;
            margin-top: 4px;
        }

        .skill-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
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
            padding: 6px 10px;
            font-size: 11px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .skill-table {
            width: 100%;
            border-collapse: collapse;
        }

        .skill-table td {
            border: 1px solid #d0d5dd;
            padding: 4px 8px;
            font-size: 12px;
        }

        .skill-table td:first-child {
            font-weight: 500;
            color: #1e293b;
        }

        .grade-line {
            font-size: 12px;
            color: #0f172a;
            line-height: 1.5;
        }

        .grade-line strong {
            font-weight: bold;
            text-transform: uppercase;
        }

        .rating-key-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .rating-key-table td {
            border: 1px solid #d0d5dd;
            padding: 4px 8px;
            font-size: 11px;
        }

        .rating-key-table td:first-child {
            width: 18%;
            text-align: center;
            font-weight: bold;
        }

        .rating-key-table tr:first-child td:first-child {
            width: auto;
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                padding: 0;
                background: #ffffff;
                height: auto;
                overflow: visible;
            }

            .page {
                box-shadow: none;
                max-height: none;
                overflow: visible;
                page-break-after: avoid;
                page-break-inside: avoid;
                padding: 8px 12px;
                transform: scale(0.90);
                transform-origin: top center;
            }

            #print-button {
                display: none;
            }

            /* Force content to fit on one page */
            @page {
                size: A4 portrait;
                margin: 5mm;
            }

            /* Prevent page breaks inside elements */
            * {
                page-break-inside: avoid !important;
                page-break-before: avoid !important;
                page-break-after: avoid !important;
            }

            html, body {
                height: 100% !important;
                overflow: visible !important;
            }

            /* Reduce all spacing to fit content */
            table {
                margin-bottom: 3px;
                font-size: 9px;
            }

            .table-one td {
                padding: 2px 3px;
                font-size: 10px;
            }

            .table-two th {
                padding: 2px 3px;
                font-weight: 900 !important;
                font-size: 9px;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .table-two td {
                padding: 2px 3px;
                font-size: 9px;
            }

            .section-title,
            .skill-card-title,
            .grade-line,
            .rating-key-table td:first-child {
                font-weight: 900 !important;
            }

            .section-title {
                margin-bottom: 3px;
                font-size: 11px;
                font-weight: 900 !important;
            }

            .school-heading {
                margin-top: 1px;
                margin-bottom: 3px;
            }

            .school-heading h1 {
                font-size: 18px;
            }

            .school-heading p {
                font-size: 10px;
                margin-top: 1px;
            }

            .info-box {
                margin-top: 3px;
                margin-bottom: 3px;
                padding: 5px 8px;
            }

            .skill-grid {
                gap: 4px;
                margin-bottom: 0;
            }

            .flex-row {
                gap: 8px;
            }

            .skill-table td {
                padding: 2px 4px;
                font-size: 9px;
            }

            .skill-card-title {
                padding: 3px 6px;
                font-size: 10px;
                font-weight: 900 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .rating-key-table td {
                padding: 2px 4px;
                font-size: 9px;
            }

            .rating-key-table tr:first-child td {
                font-weight: 900 !important;
                font-size: 10px;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .rating-key-table {
                margin-top: 3px;
            }

            .grade-line {
                font-size: 10px;
            }

            .summary-box {
                max-width: 100%;
            }

            .summary-box p {
                margin: 2px 0;
                font-size: 10px;
                line-height: 1.3;
            }

            .signature-box {
                margin-top: 3px;
            }

            .signature-box img,
            .summary-box img {
                max-height: 40px !important;
            }
        }
    
