<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>LeadScout — Funnel</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background-color: #f3f4f6;
            color: #333;
            text-align: center;
            padding: 8px;
            border: 1px solid #e5e7eb;
            font-weight: bold;
        }

        td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
            vertical-align: middle;
        }

        tr:nth-child(even) {
            background-color: #fafafa;
        }
    </style>
</head>
<body>
    <h1>LeadScout — Funnel</h1>
    <p>Generated: {{ $generatedAt }}</p>
    <table>
        <thead>
            <tr>
                <th>Company</th>
                <th>Stage</th>
                <th>Medium</th>
                <th>Kind</th>
                <th>Sent</th>
                <th>Variant</th>
                <th>Opps</th>
                <th>Hours</th>
                <th>Amount</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['Company'] }}</td>
                    <td>{{ $row['Stage'] }}</td>
                    <td>{{ $row['Medium'] }}</td>
                    <td>{{ $row['Kind'] }}</td>
                    <td>{{ $row['SentAt'] }}</td>
                    <td>{{ $row['Variant'] }}</td>
                    <td>{{ $row['Opportunities'] }}</td>
                    <td>{{ $row['Hours'] }}</td>
                    <td>{{ $row['Amount'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
