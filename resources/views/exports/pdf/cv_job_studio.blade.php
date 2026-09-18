<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Studio postings export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
        .meta { margin-bottom: 12px; color: #555; }
    </style>
</head>
<body>
<h1>Studio postings</h1>
<p class="meta">Generated {{ $generatedAt }} — heuristic estimates, not vendor ATS scores.</p>
<table>
    <thead>
    <tr>
        <th>Title</th>
        <th>Employer</th>
        <th>Location</th>
        <th>Remote scope</th>
        <th>Status</th>
        <th>Score</th>
        <th>Band</th>
        <th>Cap</th>
        <th>Channel</th>
        <th>Created</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            <td>{{ $row['Title'] }}</td>
            <td>{{ $row['Employer'] }}</td>
            <td>{{ $row['Location'] }}</td>
            <td>{{ $row['Remote scope'] }}</td>
            <td>{{ $row['Status'] }}</td>
            <td>{{ $row['Score'] }}</td>
            <td>{{ $row['Band'] }}</td>
            <td>{{ $row['Cap'] }}</td>
            <td>{{ $row['Channel'] }}</td>
            <td>{{ $row['Created'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
