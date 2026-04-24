<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { margin-bottom: 0; }
        .muted { color: #6b7280; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f9fafb; }
    </style>
</head>
<body>
<h1>Report Summary</h1>
<p class="muted">Generated at: {{ now()->format('Y-m-d H:i:s') }}</p>

<table>
    <tr><th>Metric</th><th>Value</th></tr>
    @foreach(($dataset['summary'] ?? []) as $key => $value)
        <tr>
            <td>{{ str_replace('_', ' ', ucfirst($key)) }}</td>
            <td>{{ is_numeric($value) ? number_format((float)$value, 2) : $value }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
