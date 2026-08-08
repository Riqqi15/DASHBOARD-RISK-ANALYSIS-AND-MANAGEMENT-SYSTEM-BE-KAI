<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $scope }}</title>
    <style>
        @page { margin: 26px 26px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: "DejaVu Sans", sans-serif; font-size: 8px; }
        .header { border-bottom: 3px solid #f26522; margin-bottom: 14px; padding-bottom: 10px; }
        .brand { color: #171650; font-size: 18px; font-weight: 800; letter-spacing: .5px; }
        .brand span { color: #f26522; }
        h1 { color: #171650; font-size: 15px; margin: 7px 0 3px; }
        .meta { color: #526079; font-size: 8px; }
        table { border-collapse: collapse; table-layout: auto; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #171650; border: 1px solid #292766; color: #fff; font-size: 7px; padding: 5px 4px; text-align: left; }
        td { border: 1px solid #d8dee8; padding: 4px; vertical-align: top; word-break: break-word; }
        tbody tr:nth-child(even) td { background: #f5f7fa; }
        .empty { border: 1px solid #d8dee8; color: #667085; padding: 24px; text-align: center; }
        .footer-note { color: #667085; font-size: 7px; margin-top: 8px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="brand">KAI <span>RAMS</span></div>
        <h1>{{ $title }}</h1>
        <div class="meta">Area: {{ $scope }} &nbsp;|&nbsp; Dibuat: {{ $generatedAt->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</div>
    </header>

    @if (count($rows))
        <table>
            <thead><tr>@foreach ($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>@foreach ($row as $value)<td>{{ is_bool($value) ? ($value ? 'Ya' : 'Tidak') : ($value ?? '-') }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">Belum ada data pada area dan jenis laporan yang dipilih.</div>
    @endif

    <p class="footer-note">Dokumen dihasilkan oleh Dashboard Risk Analysis and Management System KAI. Data mengikuti pembatasan unit akun.</p>
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(730, 565, "Halaman {PAGE_NUM} / {PAGE_COUNT}", "DejaVu Sans", 7, [0.35, 0.39, 0.48]);
        }
    </script>
</body>
</html>
