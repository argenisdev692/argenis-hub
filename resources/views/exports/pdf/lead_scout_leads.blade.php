<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>LeadScout — Leads</title>
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
    <h1>LeadScout — Leads</h1>
    <p>Generated: {{ $generatedAt }}</p>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Domain</th>
                <th>Country</th>
                <th>Type</th>
                <th>Origin</th>
                <th>Tier</th>
                <th>Score</th>
                <th>Confidence</th>
                <th>Research</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Domain'] }}</td>
                    <td>{{ $row['Country'] }}</td>
                    <td>{{ $row['Type'] }}</td>
                    <td>{{ $row['Origin'] }}</td>
                    <td>{{ $row['Tier'] }}</td>
                    <td>{{ $row['Score'] }}</td>
                    <td>{{ $row['Confidence'] }}</td>
                    <td>{{ $row['NeedsResearch'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
