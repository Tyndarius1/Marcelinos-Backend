<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm; }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    @include('filament.pages.partials.print-report-template', [
        'title' => $title,
        'subtitle' => $subtitle,
        'localData' => $localData,
        'foreignData' => $foreignData,
        'logoSrc' => $logoSrc ?? null,
    ])
</body>
</html>

