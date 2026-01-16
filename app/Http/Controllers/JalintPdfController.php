<?php

namespace App\Http\Controllers;

use App\Library\Fpdf\JalintPDF;
use Illuminate\Http\Request;

class JalintPdfController extends Controller
{
    public function suratTugas(Request $request)
    {
        $pdf = new JalintPDF();
        $pdf->AliasNbPages(); // WAJIB: Agar {nb} terbaca jumlah total halaman
        $pdf->AddPage();

        // Setting Font
        $pdf->SetFont('Arial', 'BU', 14);
        $pdf->Cell(0, 8, 'SURAT TUGAS', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 2, 'No: 800/ST/Jalint-Lab/XII/2025', 0, 1, 'C');
        $pdf->Ln(5);

        // --- KALIMAT PEMBUKA (JUSTIFY) ---
        $pdf->SetFont('Arial', '', 11);

        // Ambil data dari database/variabel
        $isiSurat = 'Sehubungan dengan Kegiatan Pengambilan dan Analisis Contoh Uji Air lingkungan kegiatan pemantauan lingkungan SII 2025 PT. Seleraya Merangin Dua di Lapangan Tampi Desa Belani Kecamatan Rawas Ilir Kabupaten Musi Rawas Utara Kabupaten Sumatera Selatan, Maka dengan ini kami tugaskan:';

        // Konversi encoding untuk keamanan karakter (seperti titik atau simbol lainnya)
        $isiSurat = mb_convert_encoding($isiSurat, 'ISO-8859-1', 'UTF-8');

        // MultiCell(lebar, tinggi_baris, teks, border, alignment)
        // 'J' berarti Justify (rata kiri-kanan) agar terlihat rapi seperti dokumen resmi
        $pdf->MultiCell(0, 6, $isiSurat, 0, 'J');

        $pdf->Ln(5);

        $dataPersonel = [
            ['nama' => 'Muhammad Rizki Ardicha', 'jabatan' => 'Koordinator PPC', 'ket' => '-'],
            ['nama' => 'Muhammad Fauzi', 'jabatan' => 'PPC', 'ket' => '-'],
            ['nama' => 'M. Habib Fadillah P', 'jabatan' => 'PPC', 'ket' => '-'],
            ['nama' => 'Zul Hamdi', 'jabatan' => 'Driver', 'ket' => '-'],
        ];

        // Membuat Tabel Header
        // --- SET HEADER TABEL ---
        // 1. Tentukan Lebar masing-masing kolom
        $wNo = 10;
        $wNama = 60;
        $wJab = 45;
        $wKet = 35;
        $totalLebarTabel = $wNo + $wNama + $wJab + $wKet;

        // 2. Hitung Margin Kiri agar Center
        // A4 lebar standarnya 210mm
        $lebarKertas = 210;
        $marginTengah = ($lebarKertas - $totalLebarTabel) / 2;

        $pdf->SetDrawColor(255, 128, 0);
        $pdf->SetLineWidth(0.3);

        // 3. Set Posisi X sebelum menggambar Header
        $pdf->SetX($marginTengah);

        // --- HEADER TABEL ---
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell($wNo, 8, 'No', 1, 0, 'C', true);
        $pdf->Cell($wNama, 8, 'Nama', 1, 0, 'L', true);
        $pdf->Cell($wJab, 8, 'Jabatan', 1, 0, 'L', true);
        $pdf->Cell($wKet, 8, 'Ket', 1, 1, 'L', true); // 1 artinya pindah baris

        // --- ISI TABEL DINAMIS ---
        $no = 1;
        foreach ($dataPersonel as $row) {
            // PENTING: Di setiap awal baris baru, kita harus SetX lagi agar tetap di tengah
            $pdf->SetX($marginTengah);

            // Kolom Nomor (Bold)
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell($wNo, 8, $no++, 1, 0, 'C');

            // Kolom Data (Normal)
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell($wNama, 8, mb_convert_encoding($row['nama'], 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
            $pdf->Cell($wJab, 8, mb_convert_encoding($row['jabatan'], 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
            $pdf->Cell($wKet, 8, mb_convert_encoding($row['ket'], 'ISO-8859-1', 'UTF-8'), 1, 1, 'L');
        }
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 11);

        // Ambil data dari database/variabel
        $x = 'Untuk melakukan pekerjaan tersebut diatas pada hari Kamis-Sabtu 18-20 Desember 2025 dengan rincian jumlah dan parameter Sebagai berikut:';

        // MultiCell(lebar, tinggi_baris, teks, border, alignment)
        // 'J' berarti Justify (rata kiri-kanan) agar terlihat rapi seperti dokumen resmi
        $pdf->MultiCell(0, 6, $x, 0, 'J');
        $pdf->Ln(5);

        // Table UJI ==============================================
        // 1. Data Dinamis (Contoh data dari Database)
        $dataUji = [
            [
                'bahan' => 'Udara Ambien',
                'parameter' => 'SO2, NO2, CO, TSP, PM10, PM2.5, Pb, O3, HC',
                'satuan' => 'Titik',
                'jumlah' => '2',
            ],
            [
                'bahan' => 'Kebisingan',
                'parameter' => 'Kebisingan Lingkungan (24 Jam)',
                'satuan' => 'Titik',
                'jumlah' => '2',
            ],
            [
                'bahan' => 'Air Bersih',
                'parameter' => 'Fisika, Kimia, Mikrobiologi (Permenkes 2/2023)',
                'satuan' => 'Sampel',
                'jumlah' => '1',
            ],
        ];

        // 2. Pengaturan Posisi Tengah
        // 1. Data Dinamis
        $dataUji = [
            [
                'bahan' => 'Udara Ambien',
                'baku_mutu' => 'PP RI No. 22 Tahun 2021 Lampiran VII (Tentang Penyelenggaraan Perlindungan dan Pengelolaan Lingkungan Hidup)',
                'parameter' => 'Sulfur Dioksida (SO2), Karbon Monoksida (CO), Nitrogen Dioksida (NO2), Oksidan Fotokimia (Ox) sebagai Ozon (O3), Hidrokarbon Non Metana (NMHC), Debu Teruspensi Total (TSP), Partikulat Debu (PM10), Partikulat Debu (PM2.5), Timbal (Pb)',
                'satuan' => 'Titik',
                'jumlah' => '4',
            ],
            [
                'bahan' => 'Kebisingan Lingkungan',
                'baku_mutu' => 'Keputusan Menteri Negara Lingkungan Hidup No. 48 Tahun 1996 (Tingkat Kebisingan untuk Kawasan Industri/Pemukiman)',
                'parameter' => 'Tingkat Kebisingan Siang-Malam (Lsm) selama 24 Jam menggunakan Sound Level Meter Terkalibrasi',
                'satuan' => 'Titik',
                'jumlah' => '4',
            ],
            [
                'bahan' => 'Air Limbah Domestik',
                'baku_mutu' => 'Peraturan Menteri LHK No. P.68 Tahun 2016 (Tentang Baku Mutu Air Limbah Domestik bagi Usaha dan/atau Kegiatan)',
                'parameter' => 'pH, Total Suspended Solids (TSS), Biochemical Oxygen Demand (BOD), Chemical Oxygen Demand (COD), Minyak & Lemak, Amonia, Total Coliform',
                'satuan' => 'Sampel',
                'jumlah' => '2',
            ],
            [
                'bahan' => 'Emisi Sumber Tidak Bergerak (Genset)',
                'baku_mutu' => 'Peraturan Menteri LHK No. 11 Tahun 2021 Lampiran I.1 (Kapasitas 101KW - 500KW Bahan Bakar Minyak)',
                'parameter' => 'Nitrogen Oksida (NOx) ditentukan sebagai NO2, Karbon Monoksida (CO), Laju Alir, Partikulat, Sulfur Dioksida (SO2)',
                'satuan' => 'Titik',
                'jumlah' => '3',
            ],
            [
                'bahan' => 'Air Permukaan',
                'baku_mutu' => 'PP RI No. 22 Tahun 2021 Lampiran VI (Baku Mutu Air Nasional - Kelas II untuk Rekreasi Air dan Budidaya)',
                'parameter' => 'Temperatur, Zat Terlarut (TDS), Zat Tersuspensi (TSS), pH, DO, BOD, COD, Fosfat (PO4), Nitrat (NO3), Arsen (As), Kadmium (Cd), Kobalt (Co), Kromium (Cr), Tembaga (Cu), Besi (Fe), Timbal (Pb), Seng (Zn), Merkuri (Hg)',
                'satuan' => 'Sampel',
                'jumlah' => '5',
            ],
        ];

        // 2. Lebar Kolom
        // 1. Pengaturan Font
        // 1. Tentukan Lebar Kolom (Total 190mm jika margin 10mm, atau sesuaikan)
        // Sesuai margin 30mm Anda, sisa ruang adalah 150mm.
        $pdf->SetWidths([10, 30, 50, 55, 15, 15]);

        // 2. Tentukan Alignment Kolom
        // No: Center, Bahan: Center, BML: Justify, Parameter: Justify, Satuan: Center, Jumlah: Center
        $pdf->SetAligns(['C', 'C', 'C', 'C', 'C', 'C']);

        // 3. Header Tabel (Bold & Hitam Putih)
        $pdf->SetFont('Arial', 'B', 8);
        // Row() adalah fungsi dari script3.php yang otomatis menangani MultiCell
        $pdf->Row([
            'No',
            'Bahan/Produk yang diuji',
            'Baku Mutu Lingkungan (BML)',
            'Jenis Pengujian/Parameter',
            'Satuan',
            'Jumlah',
        ]);
        $pdf->SetAligns(['C', 'C', 'L', 'L', 'C', 'C']);

        // 4. Isi Tabel Dinamis
        $pdf->SetFont('Arial', '', 8);
        $no = 1;

        foreach ($dataUji as $item) {
            // Gunakan mb_convert_encoding agar simbol µ dan °C dari database aman
            $bahan = mb_convert_encoding($item['bahan'], 'ISO-8859-1', 'UTF-8');
            $baku_mutu = mb_convert_encoding($item['baku_mutu'], 'ISO-8859-1', 'UTF-8');
            $parameter = mb_convert_encoding($item['parameter'], 'ISO-8859-1', 'UTF-8');
            $satuan = mb_convert_encoding($item['satuan'], 'ISO-8859-1', 'UTF-8');

            // Sebelum memanggil Row, kita set No menjadi Bold
            // Karena script3 menggunakan satu font per baris, kita bisa modifikasi sedikit:

            $pdf->Row([
                $no++, // Angka urutan (Akan ikut font reguler di baris ini)
                $bahan,
                $baku_mutu,
                $parameter,
                $satuan,
                $item['jumlah'],
            ]);
        }

        // ========================
        // $pdf->Ln(30);
        // 1. Set Lebar Kolom untuk fungsi Row()
        $pdf->SetWidths([50, 40, 40, 40]);
        $pdf->SetAligns(['C', 'C', 'C', 'C']);

        // 2. Membuat Header Bertingkat (Manual)
        $pdf->SetFont('Arial', 'BI', 8);

        // Simpan posisi awal untuk baris kedua header
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Baris 1: Kotak yang tingginya 2 baris (Identifikasi & Matriks)
        $pdf->Cell(50, 14, mb_convert_encoding('Identifikasi Uji / Sample ID', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
        $pdf->Cell(40, 14, mb_convert_encoding('Matriks / Matrix', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');

        // Baris 1: Kotak Koordinat (Lebar gabungan 35+35 = 70)
        $pdf->Cell(80, 7, mb_convert_encoding('Koordinat / Coordinate', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C');

        // Baris 2: Sub-header (Lintang & Bujur) di bawah Koordinat
        $pdf->SetX($x + 50 + 40); // Geser posisi X ke awal kolom koordinat
        $pdf->Cell(40, 7, mb_convert_encoding('Lintang / Latitude', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
        $pdf->Cell(40, 7, mb_convert_encoding('Bujur / Longitude', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C');

        // 3. Isi Tabel Dinamis
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetAligns(['C', 'C', 'C', 'C']); // Kembali ke alignment isi

        $dataSample = [
            ['id' => 'UA-01 (Area Parkir)', 'matrix' => 'Udara Ambien', 'lat' => "03'24'55.2\"", 'long' => "102'44'12.8\""],
            ['id' => 'UA-02 (Depan Kantor)', 'matrix' => 'Udara Ambien', 'lat' => "03'24'58.1\"", 'long' => "102'44'15.3\""],
        ];

        foreach ($dataSample as $row) {
            $pdf->Row([
                mb_convert_encoding($row['id'], 'ISO-8859-1', 'UTF-8'),
                mb_convert_encoding($row['matrix'], 'ISO-8859-1', 'UTF-8'),
                mb_convert_encoding($row['lat'], 'ISO-8859-1', 'UTF-8'),
                mb_convert_encoding($row['long'], 'ISO-8859-1', 'UTF-8'),
            ]);
        }

        // ========================

        $pdf->Ln(10); // Memberi jarak dari tabel ke kalimat penutup

        // --- KALIMAT PENUTUP ---
        $pdf->SetFont('Arial', '', 11);
        $isiPenutup = 'Demikian surat tugas ini diberikan agar dapat dilaksanakan dengan penuh tanggung jawab.';
        // Gunakan MultiCell agar jika teks sangat panjang otomatis pindah baris
        $pdf->MultiCell(0, 6, mb_convert_encoding($isiPenutup, 'ISO-8859-1', 'UTF-8'), 0, 'J');

        $pdf->Ln(10); // Jarak menuju blok tanda tangan

        // --- BLOK TANDA TANGAN (SEBELAH KANAN) ---
        // Kita tentukan X di posisi 120mm (agar berada di area kanan kertas A4)
        $xTandaTangan = 120;

        // Tanggal
        $pdf->SetX($xTandaTangan);
        $pdf->Cell(60, 6, 'Jambi, 12 Desember 2026', 0, 1, 'L');

        // Nama Instansi
        $pdf->SetX($xTandaTangan);
        $pdf->SetFont('Arial', 'B', 11); // Bold untuk nama instansi
        $pdf->Cell(60, 6, 'Jalint Lab', 0, 1, 'L');

        $pdf->Ln(20); // Ruang kosong untuk tanda tangan basah / stempel

        // Nama Terang & Jabatan (Opsional)
        $pdf->SetX($xTandaTangan);
        $pdf->SetFont('Arial', 'BU', 11); // Bold Underline
        $pdf->Cell(60, 6, 'Nama Pejabat Terkait', 0, 1, 'L');

        $pdf->SetX($xTandaTangan);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(60, 6, 'Manager Laboratorium', 0, 1, 'L');
        // $pdf->Cell(40, 10, mb_convert_encoding('Suhu 25°C & Kadar 150 µg/Nm3', 'ISO-8859-1', 'UTF-8'), 1, 0);

        return response($pdf->Output('S'), 200)
                ->header('Content-Type', 'application/pdf');
    }
}
