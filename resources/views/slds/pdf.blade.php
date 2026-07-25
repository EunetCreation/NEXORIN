<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SLD R{{ $revision->revision }} — {{ $project->project_number }}</title>
    <style>
        @page { size: A3 landscape; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #f8fbfa; }
        body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .diagram { width: 420mm; height: 297mm; overflow: hidden; break-after: page; page-break-after: always; }
        .diagram:last-child { break-after: auto; page-break-after: auto; }
        .diagram svg { display: block; width: 100%; height: 100%; }
    </style>
</head>
<body>
    @forelse ($svgs as $svg)
        <div class="diagram">{!! $svg !!}</div>
    @empty
        <div class="diagram"></div>
    @endforelse
</body>
</html>
