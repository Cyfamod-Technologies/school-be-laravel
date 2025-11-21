<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Scratch Card Printing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            background: #fff;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 15px 45px rgba(15, 23, 42, 0.25);
            max-width: 520px;
            text-align: center;
        }
        h1 {
            margin-top: 0;
            font-size: 1.5rem;
            color: #0f172a;
        }
        p {
            color: #475569;
            font-size: 1rem;
        }
        button {
            margin-top: 16px;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            background: #0f172a;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Unable to Prepare Scratch Cards</h1>
    <p>{{ $message ?? 'An unexpected error occurred while preparing the scratch cards.' }}</p>
    <button type="button" onclick="window.close()">Close</button>
</div>
</body>
</html>
