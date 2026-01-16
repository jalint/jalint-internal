<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Tugas</title>
    <style>
        @page {
            size: A4;
            margin: 20mm 15mm;
        }

        .header-text-right {
    text-align: right;
}

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
        }

        .page {
            width: 100%;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #0a8f6a;
            margin-bottom: 10px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-center {
            text-align: center;
        }

        .header-title {
            font-size: 14px;
            font-weight: bold;
        }

        .header-subtitle {
            font-size: 10px;
        }

        h2 {
            text-align: center;
            font-size: 13px;
            margin: 10px 0;
            text-decoration: underline;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
        }

        th {
            text-align: center;
            font-weight: bold;
        }

        .no-border td {
            border: none;
        }

        .text-center {
            text-align: center;
        }

        .page-break {
            page-break-after: always;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 9px;
            text-align: center;
        }

        .logo-placeholder {
            width: 45px;
            height: 35px;
            border: 1px solid #999;
            font-size: 9px;
            line-height: 35px;
            text-align: center;
        }

        .header-text-main {
            font-size: 7px;
            font-weight: bold;
            color: purple;
        }

        .header-text-sub {
            font-size: 6px;
            line-height: 1.2;
        }

        .header-cell {
            vertical-align: bottom;
            margin-bottom: 0px;
            padding-bottom: 6px; /* ruang aman untuk teks */
        }

        .header-center-cell {
            vertical-align: bottom;
            padding-bottom: 6px;
            min-height: 95px; /* kunci agar teks tidak terpotong */
        }


    .header-table tr {
    height: 110px; /* sesuaikan dengan desain awal */
}



    </style>
</head>
<body>

<!-- ================= HALAMAN 1 ================= -->
<div class="page">

    <!-- HEADER -->
    <!-- HEADER 9 KOLOM -->
    <table width="100%" style="border-bottom:2px solid #0a8f6a; margin-bottom:8px;">
        <tr>
            <td width="8%" align="center" class="header-cell">
                <div class="logo-placeholder">IMG</div>
            </td>

            <td width="8%" align="center" class="header-cell">
                <div class="logo-placeholder">IMG</div>
            </td>

            <!-- IMAGE + TEKS (TIDAK TERPOTONG) -->
            <td width="20%" align="center" class="header-center-cell">
                <div class="logo-placeholder" style="margin-bottom:4px;">
                    <img src="{{ public_path('assets/images/jalint_logo.png') }}" width="100">
                </div>

                <div class="header-text-main header-text-right">
                    Laboratorium Penguji, Lingkungan dan PJK3
                </div>
                <div class="header-text-sub header-text-right">
                    No. Reg. KLHK RI: 00119/LPJ/LABLING-1/LRK/KLHK
                </div>
                <div class="header-text-sub header-text-right">
                    SKP PJK3 No:5/217/AS.01.01/III/2024
                </div>
            </td>

            <td width="8%" align="center" class="header-cell">
                <div class="logo-placeholder">IMG</div>
            </td>

            <td width="8%" align="center" class="header-cell">
                <div class="logo-placeholder">IMG</div>
            </td>

            <td width="8%" align="center" class="header-cell">
                <div class="logo-placeholder">IMG</div>
            </td>

            <td width="8%" align="center" class="header-cell">
                <div class="logo-placeholder">IMG</div>
            </td>

            <td width="8%" align="center" class="header-cell">
                <div class="logo-placeholder">IMG</div>
            </td>

            <td width="8%" align="center" class="header-cell">
                <div class="logo-placeholder">IMG</div>
            </td>
        </tr>
    </table>



    <div style="text-align:center; margin-top:8px; margin-bottom:12px;">
        <div style="font-size:13px; font-weight:bold; text-decoration:underline;">
            SURAT TUGAS
        </div>
        <div style="font-size:11px; margin-top:4px;">
            No. 085/ST/Jalint-Lab/XII/2025
        </div>
    </div>


    <p>
        Sehubung dengan Kegiatan Pengambilan dan Analisis Contoh Uji Air linkungan kegiatan pemantauan lingkungan SII 2025 PT. Seleraya Merangin Dua di Lapangan Tampi Desa Belani Kecamatan Rawas Ilir Kabupaten Musi Rawas Utara, Maka dengan ini kamu tugaskan:
    </p>

    <!-- TABEL PERSONEL -->
    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Nama</th>
                <th width="30%">Jabatan</th>
                <th width="30%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>Muhammad Rizki Ardicha</td>
                <td>Koordinator PPC</td>
                <td>-</td>
            </tr>
            <tr>
                <td class="text-center">2</td>
                <td>Muhammad Fauzi</td>
                <td>PPC</td>
                <td>-</td>
            </tr>
            <tr>
                <td class="text-center">3</td>
                <td>M. Habib Fadillah P</td>
                <td>PPC</td>
                <td>-</td>
            </tr>
            <tr>
                <td class="text-center">4</td>
                <td>Zul Hamdi</td>
                <td>Driver</td>
                <td>-</td>
            </tr>
        </tbody>
    </table>

    <br>

    <!-- TABEL PARAMETER -->
    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="25%">Jenis Sampel</th>
                <th width="50%">Parameter Uji</th>
                <th width="10%">Satuan</th>
                <th width="10%">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>Udara Ambien</td>
                <td>SO₂, NO₂, CO, TSP, PM₁₀</td>
                <td class="text-center">Titik</td>
                <td class="text-center">2</td>
            </tr>
            <tr>
                <td class="text-center">2</td>
                <td>Kebisingan</td>
                <td>Tingkat Kebisingan</td>
                <td class="text-center">Titik</td>
                <td class="text-center">2</td>
            </tr>
            <!-- Tambahkan baris sesuai kebutuhan -->
        </tbody>
    </table>

</div>

<div class="page-break"></div>

<!-- ================= HALAMAN 2 ================= -->
<div class="page">

    <table>
        <thead>
            <tr>
                <th>Kode Sampel</th>
                <th>Lokasi</th>
                <th>Koordinat</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>UA-1</td>
                <td>Area Produksi</td>
                <td>LS 02°35'22" – BT 103°12'25"</td>
            </tr>
            <tr>
                <td>KB-1</td>
                <td>Gerbang Utama</td>
                <td>LS 02°35'27" – BT 103°12'27"</td>
            </tr>
        </tbody>
    </table>

    <br><br>

    <p>
        Demikian surat tugas ini dibuat untuk dapat dilaksanakan dengan
        penuh tanggung jawab.
    </p>

    <br><br>

    <table class="no-border" width="100%">
        <tr>
            <td width="60%"></td>
            <td width="40%" class="text-center">
                Jambi, 12 Desember 2025<br><br><br>
                <strong>Nama Penanggung Jawab</strong>
            </td>
        </tr>
    </table>

</div>

<div class="footer">
    Alamat Footer Placeholder – Halaman <script type="text/php">echo $PAGE_NUM;</script>
</div>

</body>
</html>




