{{-- Ekspor PDF pendaftar (dompdf). Semua nilai lewat {{ }} (di-escape). Tanpa gambar/font remote. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Data Pendaftar - {{ $siteName }}</title>
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 7.5pt; color: #1c1917; }
        h1 { font-size: 12pt; margin: 0 0 2mm; color: #065f46; }
        .meta { margin: 0 0 1mm; color: #44403c; }
        .note { margin: 2mm 0; padding: 2mm; border: 1px solid #d97706; color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin-top: 3mm; }
        th, td { border: 1px solid #a8a29e; padding: 1.2mm; vertical-align: top; text-align: left; }
        th { background: #ecfdf5; color: #064e3b; font-weight: bold; }
        tr:nth-child(even) td { background: #fafaf9; }
        .num { text-align: right; white-space: nowrap; }
        .nik { white-space: nowrap; }
        .empty { text-align: center; padding: 4mm; color: #57534e; }
    </style>
</head>
<body>
    <h1>Data Pendaftar Santri Baru &middot; {{ $siteName }}</h1>
    <p class="meta">Diekspor: {{ $exportedAt }} &middot; Jumlah: {{ $total }}</p>
    <p class="meta">Saringan: {{ $filters ? implode('; ', $filters) : 'Semua pendaftar' }}</p>
    @unless ($revealNik)
        <p class="meta">NIK disamarkan (pengekspor tidak memiliki izin melihat NIK lengkap).</p>
    @endunless
    @if ($truncated)
        <p class="note">Hanya {{ $max }} baris pertama yang dimuat di PDF. Persempit saringan atau gunakan Ekspor Excel untuk data lengkap.</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>NIK</th>
                <th>TTL</th>
                <th>Alamat</th>
                <th>Mondok sebelumnya</th>
                <th>Pendidikan terakhir</th>
                <th>Jenjang / Kelas</th>
                <th>Ayah</th>
                <th>Ibu</th>
                <th>Wali</th>
                <th>Dihubungi / Bayar / Masuk</th>
                <th>Tanggal daftar</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="num">{{ $row[0] }}</td>
                    <td>{{ $row[1] }}</td>
                    <td class="nik">{{ $row[2] }}</td>
                    <td>{{ $row[3] }}</td>
                    <td>{{ $row[4] }}</td>
                    <td>{{ $row[5] }}@if ($row[6] !== '-'): {{ $row[6] }}@endif</td>
                    <td>{{ $row[7] }}</td>
                    <td>{{ $row[8] }} / {{ $row[9] }}</td>
                    <td>{{ $row[10] }}<br>{{ $row[11] }}</td>
                    <td>{{ $row[12] }}<br>{{ $row[13] }}</td>
                    <td>{{ $row[14] }}</td>
                    <td>{{ $row[15] }} / {{ $row[16] }} / {{ $row[17] }}</td>
                    <td class="nik">{{ $row[18] }}</td>
                </tr>
            @empty
                <tr><td colspan="13" class="empty">Tidak ada data pendaftar untuk saringan ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
