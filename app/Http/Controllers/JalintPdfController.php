<?php

namespace App\Http\Controllers;

use App\Library\Fpdf\JalintPDF;
use App\Library\Tfpdf\JalintTFPDF;
use App\Models\Offer;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JalintPdfController extends Controller
{
    public function suratTugas(Request $request, $id)
    {
        $data = $this->getDataOffer($id);

        $parameters = $this->buildDataUjiForPdf($data);

        $pdf = new JalintPDF();
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AliasNbPages(); // WAJIB: Agar {nb} terbaca jumlah total halaman
        $pdf->AddPage();

        $taskLetterNumber = $data->taskLetter->task_letter_number;
        $namaKegiatan = $data->title;
        $customerName = $data->customer->name;
        $location = $data->location;
        $dataPersonel = $data->taskLetter->officers;
        $tanggalKegiatan = $this->formatTanggalRange($data->taskLetter->start_date, $data->taskLetter->end_date);

        // Setting Font
        $pdf->SetFont('Arial', 'BU', 14);
        $pdf->Cell(0, 8, 'SURAT TUGAS', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 2, "No: $taskLetterNumber", 0, 1, 'C');
        $pdf->Ln(5);

        // --- KALIMAT PEMBUKA (JUSTIFY) ---
        $pdf->SetFont('Arial', '', 11);

        // Ambil data dari database/variabel
        $isiSurat = "Sehubungan dengan $namaKegiatan $customerName di $location, Maka dengan ini kami tugaskan:";

        // MultiCell(lebar, tinggi_baris, teks, border, alignment)
        // 'J' berarti Justify (rata kiri-kanan) agar terlihat rapi seperti dokumen resmi
        $pdf->MultiCell(0, 6, $isiSurat, 0, 'J');

        $pdf->Ln(5);

        // $dataPersonel = [
        //     ['nama' => 'Muhammad Rizki Ardicha', 'jabatan' => 'Koordinator PPC', 'ket' => '-'],
        //     ['nama' => 'Muhammad Fauzi', 'jabatan' => 'PPC', 'ket' => '-'],
        //     ['nama' => 'M. Habib Fadillah P', 'jabatan' => 'PPC', 'ket' => '-'],
        //     ['nama' => 'Zul Hamdi', 'jabatan' => 'Driver', 'ket' => '-'],
        // ];

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
            // dd($row);
            // PENTING: Di setiap awal baris baru, kita harus SetX lagi agar tetap di tengah
            $pdf->SetX($marginTengah);

            // Kolom Nomor (Bold)
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell($wNo, 8, $no++, 1, 0, 'C');

            // Kolom Data (Normal)
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell($wNama, 8, $row['employee']['name'], 1, 0, 'L');
            $pdf->Cell($wJab, 8, $row['position'], 1, 0, 'L');
            $pdf->Cell($wKet, 8, $row['description'], 1, 1, 'L');
        }
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 11);

        // Ambil data dari database/variabel
        $informasiKegiatan = "Untuk melakukan pekerjaan tersebut diatas pada hari $tanggalKegiatan dengan rincian jumlah dan parameter Sebagai berikut:";

        // MultiCell(lebar, tinggi_baris, teks, border, alignment)
        // 'J' berarti Justify (rata kiri-kanan) agar terlihat rapi seperti dokumen resmi
        $pdf->MultiCell(0, 6, $informasiKegiatan, 0, 'J');
        $pdf->Ln(5);

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

        foreach ($parameters as $parameter) {
            // Gunakan mb_convert_encoding agar simbol µ dan °C dari database aman
            $bahan = mb_convert_encoding($parameter['produk_uji'], 'ISO-8859-1', 'UTF-8');
            $baku_mutu = mb_convert_encoding('SO₄²⁻', 'ISO-8859-1', 'UTF-8');
            $parameter = mb_convert_encoding($parameter['parameter'], 'ISO-8859-1', 'UTF-8');
            // $satuan = mb_convert_encoding($parameter['satuan'], 'ISO-8859-1', 'UTF-8');

            // Sebelum memanggil Row, kita set No menjadi Bold
            // Karena script3 menggunakan satu font per baris, kita bisa modifikasi sedikit:

            $pdf->Row([
                $no++, // Angka urutan (Akan ikut font reguler di baris ini)
                $bahan,
                $baku_mutu,
                $parameter,
                'Titik',
                1,
            ]);
        }
        $totaUji = count($data['samples']) >= 2 ? 15 : 35;
        // ========================
        $pdf->Ln($totaUji);
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

    public function suratTugasTFPDF(Request $request, $id)
    {
        $data = $this->getDataOffer($id);
        $parameters = $this->buildDataUjiForPdf($data);

        // Pastikan instansiasi class yang benar
        $pdf = new JalintTFPDF();
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AliasNbPages();
        $pdf->AddPage();

        // Set Font Default (Unicode)
        $pdf->SetFont('DejaVu', '', 10);

        $taskLetterNumber = $data->taskLetter->task_letter_number ?? '-';
        $namaKegiatan = $data->title;
        $customerName = $data->customer->name;
        $location = $data->location;
        $dataPersonel = $data->taskLetter->officers;
        $tanggalKegiatan = $this->formatTanggalRange($data->taskLetter->start_date, $data->taskLetter->end_date);

        // --- JUDUL ---
        $pdf->SetFont('DejaVu', 'B', 14);
        $pdf->Cell(0, 8, 'SURAT TUGAS', 0, 1, 'C');
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(0, 2, "No: $taskLetterNumber", 0, 1, 'C');
        $pdf->Ln(5);

        // --- KALIMAT PEMBUKA ---
        $pdf->SetFont('DejaVu', '', 11);
        $isiSurat = "Sehubungan dengan $namaKegiatan $customerName di $location, Maka dengan ini kami tugaskan:";
        $pdf->MultiCell(0, 6, $isiSurat, 0, 'J');
        $pdf->Ln(5);

        // --- TABEL PERSONEL ---
        $wNo = 10;
        $wNama = 60;
        $wJab = 45;
        $wKet = 35;
        $totalLebarTabel = $wNo + $wNama + $wJab + $wKet;
        $marginTengah = (210 - $totalLebarTabel) / 2;

        $pdf->SetDrawColor(255, 128, 0);
        $pdf->SetX($marginTengah);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell($wNo, 8, 'No', 1, 0, 'C');
        $pdf->Cell($wNama, 8, 'Nama', 1, 0, 'L');
        $pdf->Cell($wJab, 8, 'Jabatan', 1, 0, 'L');
        $pdf->Cell($wKet, 8, 'Ket', 1, 1, 'L');

        $no = 1;
        foreach ($dataPersonel as $row) {
            $pdf->SetX($marginTengah);
            $pdf->SetFont('DejaVu', 'B', 10);
            $pdf->Cell($wNo, 8, $no++, 1, 0, 'C');
            $pdf->SetFont('DejaVu', '', 10);
            // tFPDF tidak butuh mb_convert_encoding, langsung cetak UTF-8
            $pdf->Cell($wNama, 8, $row['employee']['name'], 1, 0, 'L');
            $pdf->Cell($wJab, 8, $row['position'], 1, 0, 'L');
            $pdf->Cell($wKet, 8, $row['description'], 1, 1, 'L');
        }

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Ln(5);

        // --- INFORMASI PARAMETER ---
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->MultiCell(0, 6, "Untuk melakukan pekerjaan tersebut diatas pada hari $tanggalKegiatan dengan rincian sebagai berikut:", 0, 'J');
        $pdf->Ln(5);

        // --- TABEL PARAMETER ---
        $pdf->SetWidths([10, 30, 50, 55, 15, 15]);
        $pdf->SetAligns(['C', 'C', 'L', 'L', 'C', 'C']);
        $pdf->SetFont('DejaVu', 'B', 8);

        $pdf->Row(['No', 'Bahan/Produk', 'Regulasi', 'Jenis Parameter', 'Satuan', 'Jumlah']);
        $pdf->SetFont('DejaVu', '', 8);

        $noPara = 1;
        $totalSample = 0;
        foreach ($parameters as $p) {
            $totalSample += $p['jumlah_parameter'];

            $pdf->Row([
                $noPara++,
                $p['produk_uji'],
                $p['regulasi'], // Gunakan tag jika helper sudah siap, atau langsung 'SO₄²⁻'
                $p['parameter'],
                'Titik',
                1,
            ]);
        }

        // --- TABEL KOORDINAT ---
        $pdf->Ln(10);
        $xPos = $pdf->GetX();
        $pdf->SetFont('DejaVu', 'BI', 8);
        // Header koordinat
        $pdf->Cell(50, 14, 'Identifikasi Uji / Sample ID', 1, 0, 'C');
        $pdf->Cell(40, 14, 'Matriks / Matrix', 1, 0, 'C');
        $pdf->Cell(80, 7, 'Koordinat / Coordinate', 1, 1, 'C');
        $pdf->SetX($xPos + 90);
        $pdf->Cell(40, 7, 'Lintang / Latitude', 1, 0, 'C');
        $pdf->Cell(40, 7, 'Bujur / Longitude', 1, 1, 'C');

        $pdf->SetFont('DejaVu', '', 8);
        $pdf->SetWidths([50, 40, 40, 40]);
        $pdf->SetAligns(['L', 'C', 'C', 'C']);

        // Data sample dengan simbol derajat asli (Unicode)
        // $dataSample = [
        //     ['id' => 'UA-01', 'matrix' => 'Udara Ambien', 'lat' => "03°24'55.2\"", 'long' => "102°44'12.8\""],
        // ];

        // foreach ($dataSample as $row) {
        //     // Langsung masukkan variabel, tFPDF akan merender ° dengan benar
        //     $pdf->Row([$row['id'], $row['matrix'], $row['lat'], $row['long']]);
        // }

        for ($i = 0; $i < $totalSample; ++$i) {
            $pdf->Row(['', '', '', '']); // Array kosong sejumlah kolom tabel (4 kolom)
        }

        // --- PENUTUP ---
        $pdf->Ln(10);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->MultiCell(0, 6, 'Demikian surat tugas ini diberikan agar dapat dilaksanakan dengan penuh tanggung jawab.', 0, 'J');

        // --- TANDA TANGAN ---
        $pdf->Ln(10);
        $xTTD = 120;
        $pdf->SetX($xTTD);
        $pdf->Cell(60, 6, 'Jambi, '.date('d F Y'), 0, 1, 'L');
        $pdf->SetX($xTTD);
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(60, 6, 'Jalint Lab', 0, 1, 'L');
        $pdf->Ln(20);
        $pdf->SetX($xTTD);
        $pdf->SetFont('DejaVu', 'BU', 11);
        $pdf->Cell(60, 6, 'Nama Pejabat Terkait', 0, 1, 'L');

        return response($pdf->Output('S'), 200)->header('Content-Type', 'application/pdf');
    }

    private function getDataOffer($taskLetterId)
    {
        return Offer::query()
            ->whereHas('taskLetter', function ($q) use ($taskLetterId) {
                $q->where('id', $taskLetterId);
            })
            ->with([
                'taskLetter.officers.employee',
                'samples.parameters.testParameter',
                'customer:id,name',
            ])
            ->orderByDesc('created_at')
            ->first();
    }

    public function generateFPPCU()
    {
        $pdf = new JalintTFPDF();
        $pdf->showFooter = false;
        $pdf->AliasNbPages();
        $pdf->AddPage('P', 'A4');

        // Margin atas diperkecil sedikit agar aman
        $pdf->SetTopMargin(10);

        // --- Judul Atas ---
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 5, 'FORMULIR PERMINTAAN PENGUJIAN CONTOH UJI', 0, 1, 'C');
        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->Cell(0, 5, 'JOB NUMBER: LAB-JLI-2502156', 0, 1, 'C');
        $pdf->Ln(3); // Dikurangi dari 5

        $pdf->SetFont('DejaVu', '', 9); // Font diperkecil sedikit ke 9
        $lineHeight = 6; // Dikurangi dari 7

        // --- Bagian Informasi Pelanggan ---
        $this->rowWithDots($pdf, 'Nama Pelanggan', 'PT Siloam', $lineHeight);
        // Untuk alamat, jika MultiCell memakan terlalu banyak ruang, pastikan rowWithDots menangani \n dengan baik
        $this->rowWithDots($pdf, 'Alamat', 'Jl. Soekarno - Hatta, Kel. Paal Merah, Kec. Jambi Selatan, Jambi', $lineHeight);
        $this->rowWithDots($pdf, 'Personil Penghubung', 'Jeremiah', $lineHeight);
        $this->rowWithDots($pdf, 'No. Telp/HP', '0821-7542-1256', $lineHeight);
        $this->rowWithDots($pdf, 'Email Penerima Laporan', '-', $lineHeight);
        $this->rowWithDots($pdf, 'Nama Kegiatan', 'Analisa Contoh Uji Kualitas Air Bulan Februari Tahun 2025', $lineHeight);
        $this->rowWithDots($pdf, 'Tanggal Diterima', '06/02/2025', $lineHeight);

        $pdf->Ln(4);

        // --- Header Tabel (Disesuaikan lebarnya agar total ~190mm) ---
        $pdf->SetFont('DejaVu', 'B', 8);
        $hHeader = 10;
        $pdf->Cell(10, $hHeader, 'No', 1, 0, 'C');
        $pdf->Cell(25, $hHeader, 'Bahan Produk', 1, 0, 'C');
        $pdf->Cell(20, $hHeader, 'Jml Wadah', 1, 0, 'C'); // Diaktifkan kembali
        $pdf->Cell(35, $hHeader, 'Jenis Wadah', 1, 0, 'C');
        $pdf->Cell(25, $hHeader, 'Volume', 1, 0, 'C');
        $pdf->Cell(30, $hHeader, 'Pengawetan', 1, 0, 'C');
        $pdf->Cell(35, $hHeader, 'Keterangan', 1, 1, 'C');

        // --- Isi Tabel (15 Baris) ---
        $pdf->SetFont('DejaVu', '', 8);
        $data = [
            ['1.', 'Air', '3', 'Jerigen', '500 ml', 'HNO3', 'Sudah'],
            ['2.', 'Air', '5', 'Jerigen', '1 L', '-', 'Pengawet'],
        ];

        $rowHeight = 5.5; // Tinggi baris isi tabel agar muat 15 baris
        for ($i = 0; $i < 23; ++$i) {
            $no = $data[$i][0] ?? '';
            $bahan = $data[$i][1] ?? '';
            $jml = $data[$i][2] ?? '';
            $jenis = $data[$i][3] ?? '';
            $vol = $data[$i][4] ?? '';
            $awet = $data[$i][5] ?? '';
            $ket = $data[$i][6] ?? '';

            $pdf->Cell(10, $rowHeight, $no, 1, 0, 'C');
            $pdf->Cell(25, $rowHeight, $bahan, 1, 0, 'L');
            $pdf->Cell(20, $rowHeight, $jml, 1, 0, 'C');
            $pdf->Cell(35, $rowHeight, $jenis, 1, 0, 'L');
            $pdf->Cell(25, $rowHeight, $vol, 1, 0, 'C');
            $pdf->Cell(30, $rowHeight, $awet, 1, 0, 'L');
            $pdf->Cell(35, $rowHeight, $ket, 1, 1, 'L');
        }

        $pdf->Ln(3);

        // --- Status Contoh Uji (Checkbox) ---
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->Cell(35, 5, 'Status Contoh Uji :', 0, 0);

        // Checkbox 1
        $pdf->Cell(5, 5, 'V', 1, 0, 'C');
        $pdf->Cell(40, 5, ' Diantar Pelanggan', 0, 1);

        // Checkbox 2 (Indentasi)
        $pdf->Cell(35, 5, '', 0, 0);
        $pdf->Cell(5, 5, '', 1, 0, 'C');
        $pdf->Cell(40, 5, ' Diambil Oleh Laboratorium', 0, 1);

        $pdf->Ln(6);

        // --- Tanda Tangan ---
        $pdf->SetFont('DejaVu', '', 9);
        $currY = $pdf->GetY();

        $pdf->Cell(45, 5, 'PPCU/Perwakilan Pelanggan,', 0, 0, 'C');
        $pdf->Cell(222, 5, 'Jambi, 06/02/2025', 0, 1, 'C');
        $pdf->Cell(100, 5, '', 0, 0, 'C');
        $pdf->Cell(125, 5, 'Penerima,', 0, 1, 'C');

        $pdf->Ln(12); // Ruang tanda tangan sedikit dipadatkan

        $pdf->Cell(45, 5, '( Dimas Rahmat Hidayat )', 0, 0, 'C');
        $pdf->Cell(125, 5, '( Indah Ayu )', 0, 1, 'R');

        return response($pdf->Output('S', 'Formulir_Permintaan.pdf'))
                ->header('Content-Type', 'application/pdf');
    }

    // Fungsi Pembantu untuk baris dengan titik-titik
    private function rowWithDots($pdf, $label, $value, $h, $isMulti = false)
    {
        $pdf->Cell(45, $h, $label, 0, 0);
        $pdf->Cell(5, $h, ':', 0, 0);

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Cetak titik-titik sebagai latar belakang
        $pdf->SetTextColor(150);
        $pdf->Cell(0, $h, '.........................................................................................................', 0, 0);

        // Cetak nilai aslinya di atas titik-titik
        $pdf->SetXY($x, $y);
        $pdf->SetTextColor(0, 0, 255); // Warna biru seperti tulisan tangan
        if ($isMulti) {
            $pdf->MultiCell(0, $h, $value, 0, 'L');
        } else {
            $pdf->Cell(0, $h, $value, 0, 1);
        }
        $pdf->SetTextColor(0);
    }

    private function buildDataUjiForPdf($offer)
    {
        return $offer->samples->map(function ($sample) {
            $parameterCollection = $sample->parameters
                ->map(fn ($p) => $p->testParameter?->name)
                ->filter()
                ->unique()
                ->values();

            $parameters = $parameterCollection->implode(', ');

            $firstParam = $sample->parameters->first();
            $sampleType = $firstParam?->testParameter?->sampleType;

            return [
                'produk_uji' => $sample->title,
                'regulasi' => $sampleType?->regulation,
                'parameter' => $parameters,
                'satuan' => '-',
                'jumlah' => 1,
                'jumlah_parameter' => $parameterCollection->count(),
            ];
        })
        ->values()
        ->toArray();
    }

    public function formatTanggalRange(string $start, string $end): string
    {
        Carbon::setLocale('id');

        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        $hariMulai = $startDate->translatedFormat('l');
        $hariAkhir = $endDate->translatedFormat('l');

        $tglMulai = $startDate->day;
        $tglAkhir = $endDate->day;

        $bulan = $endDate->translatedFormat('F');
        $tahun = $endDate->year;

        return sprintf(
            '%s-%s %d-%d %s %d',
            $hariMulai,
            $hariAkhir,
            $tglMulai,
            $tglAkhir,
            $bulan,
            $tahun
        );
    }

    public function printInvoice()
    {
        $pdf = new JalintTFPDF();

        // Setup Font
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
        $pdf->AddFont('DejaVu', 'BI', 'DejaVuSans-BoldOblique.ttf', true);

        // ==========================================
        // HALAMAN 1: SURAT (PAKAI HEADER & FOOTER)
        // ==========================================
        $pdf->showHeaderFooter = true; // Set TRUE DULU sebelum AddPage
        $pdf->AddPage('P');
        $this->buatHalamanSurat($pdf);

        // ==========================================
        // HALAMAN 2: KWITANSI (MATIKAN SEMUA)
        // ==========================================
        $pdf->showHeaderFooter = true; // Set FALSE DULU sebelum AddPage
        $pdf->AddPage('L', [210, 148]);
        $this->buatHalamanKwitansi($pdf);

        // ==========================================
        // HALAMAN 3: INVOICE (PAKAI HEADER & FOOTER)
        // ==========================================
        $pdf->showHeaderFooter = true; // Set TRUE DULU sebelum AddPage
        $pdf->AddPage('P');
        $this->buatHalamanInvoice($pdf);

        return response($pdf->Output('S', 'Dokumen_Penagihan.pdf'))
                ->header('Content-Type', 'application/pdf');
    }

    private function buatHalamanSurat($pdf)
    {
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(0, 5, 'Jambi, 20 Desember 2025.', 0, 1, 'R');
        $pdf->Ln(10);

        // --- Bagian Nomor, Lampiran, Perihal ---
        $pdf->Cell(25, 6, 'Nomor', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, '1648/Inv/Jalint-Lab/XII/2025.', 0, 1);

        $pdf->Cell(25, 6, 'Lampiran', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, '1 (satu) berkas', 0, 1);

        $pdf->Cell(25, 6, 'Perihal', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Permohonan Pembayaran Pengujian Sampel', 0, 1);

        $pdf->Ln(10);

        // --- Alamat Tujuan ---
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Kepada Yth :', 0, 1);
        $pdf->Cell(0, 6, 'PT. PERSADA ALAM JAYA', 0, 1);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(0, 6, 'U.p Bapak M Nur Kholis', 0, 1);
        $pdf->Cell(0, 6, 'di', 0, 1);
        $pdf->Cell(0, 6, 'Suban - Batang Asam', 0, 1);

        $pdf->Ln(12);

        // --- Isi Surat ---
        $pdf->Cell(0, 6, 'Dengan hormat,', 0, 1);
        $pdf->Ln(2);

        $text1 = 'Bersama ini kami mengajukan permohonan pembayaran Pengujian Sampel Kualitas Air Limbah Produksi dan Air Limbah Domestik Bulan Desember Tahun 2025 PT. Persada Alam Jaya sebesar Rp1.177.200,00 dengan PPN.';
        $pdf->MultiCell(0, 6, $text1, 0, 'L');

        $pdf->Ln(5);
        $pdf->Cell(0, 6, 'Bersama ini dilampirkan dokumen berupa :', 0, 1);
        $pdf->Cell(10, 6, '1.', 0, 0);
        $pdf->Cell(0, 6, 'Invoice', 0, 1);
        $pdf->Cell(10, 6, '2.', 0, 0);
        $pdf->Cell(0, 6, 'Kwitansi', 0, 1);
        $pdf->Cell(10, 6, '3.', 0, 0);
        $pdf->Cell(0, 6, 'Faktur Pajak', 0, 1);

        $pdf->Ln(5);
        $pdf->Cell(0, 6, 'Demikian surat ini kami sampaikan dan atas kerjasamanya diucapkan terima kasih.', 0, 1);

        $pdf->Ln(20);

        // --- Bagian Tanda Tangan (Disebelah Kanan) ---
        $startX = 120; // Atur posisi horizontal tanda tangan
        $pdf->SetX($startX);
        $pdf->Cell(0, 6, 'Jalint Lab', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->Cell(0, 6, 'a.n Direktur', 0, 1, 'L');

        // Simulasi tempat tanda tangan & stempel
        $pdf->Ln(15);

        $pdf->SetX($startX);
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Retni Azmalia, S.E', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(0, 6, 'Manajer Keuangan', 0, 1, 'L');
    }

    private function buatHalamanKwitansi($pdf)
    {
        // 1. Tetap aktifkan AutoPageBreak tapi dengan margin bawah yang aman bagi Footer
        $pdf->SetAutoPageBreak(true, 25);

        // 2. Tentukan area kerja (Safe Zone)
        // Header berakhir di Y=27, Footer mulai di Y=128 (pada tinggi A5 148mm)
        $startY = 35; // Mulai di bawah garis hijau header
        $boxHeight = 85; // Tinggi bingkai diperkecil agar tidak menyentuh footer

        // 3. Buat bingkai kwitansi
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(5, $startY, 200, $boxHeight);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect(7, $startY + 2, 196, $boxHeight - 4);

        // 4. Posisi Teks Awal
        $pdf->SetY($startY + 8);

        // --- Baris: Telah terima dari ---
        $pdf->SetX(15);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(40, 10, 'Telah terima dari', 0, 0);
        $pdf->Cell(5, 10, ':', 0, 0);
        $pdf->SetFont('DejaVu', 'B', 12);
        $pdf->Cell(0, 10, 'PT. PERSADA ALAM JAYA', 0, 1);

        // --- Baris: Uang sejumlah ---
        $pdf->SetX(15);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(40, 10, 'Uang sejumlah', 0, 0);
        $pdf->Cell(5, 10, ':', 0, 0);
        $pdf->SetFont('DejaVu', 'BI', 11); // Ukuran font diperkecil sedikit
        $pdf->Cell(0, 10, 'Satu juta seratus tujuh puluh tujuh ribu dua ratus rupiah', 0, 1);

        // --- Baris: Untuk Pembayaran ---
        $pdf->SetX(15);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(40, 8, 'Untuk Pembayaran', 0, 0);
        $pdf->Cell(5, 8, ':', 0, 0);
        // MultiCell dipersempit lebarnya (90mm) agar tidak menabrak tanda tangan
        $pdf->MultiCell(90, 7, 'Pengujian Sampel Kualitas Air Limbah Produksi dan Air Limbah Domestik Bulan Desember Tahun 2025 PT. Persada Alam Jaya.', 0, 'L');

        // --- Baris: Nominal (Box Abu-abu) ---
        // Diposisikan relatif terhadap startY
        $pdf->SetXY(15, $startY + 65);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('DejaVu', 'BI', 13);
        $pdf->Cell(55, 10, 'Rp1.177.200,00', 0, 0, 'C', true);

        // --- Bagian Tanda Tangan ---
        $startX = 145;
        $currentY = $startY + 45; // Mulai tanda tangan di tengah kanan bingkai

        $pdf->SetXY($startX, $currentY);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(0, 5, 'Jambi, 20 Desember 2025.', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->Cell(0, 5, 'Yang menerima :', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(0, 5, 'Jalint Lab', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->Cell(0, 5, 'a.n Direktur', 0, 1, 'L');

        // Jarak tanda tangan ke Nama Manajer (diperpendek jaraknya agar tidak menabrak footer)
        $pdf->SetXY($startX, $currentY + 26);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(0, 5, 'Refni Azmalia, S.E', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(0, 5, 'Manajer Keuangan', 0, 1, 'L');
    }

    private function buatHalamanInvoice($pdf)
    {
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(25, 5, 'Invoice No.', 0, 0);
        $pdf->Cell(5, 5, ':', 0, 0);
        $pdf->Cell(0, 5, '1648/Inv/Jalint-Lab/XII/2025.', 0, 1);

        $pdf->Cell(25, 5, 'Tanggal', 0, 0);
        $pdf->Cell(5, 5, ':', 0, 0);
        $pdf->Cell(0, 5, '20 Desember 2025.', 0, 1);

        $pdf->Cell(25, 5, 'N.P.W.P', 0, 0);
        $pdf->Cell(5, 5, ':', 0, 0);
        $pdf->Cell(0, 5, '31.770.541.6-331.000', 0, 1);

        $pdf->Ln(10);

        // --- Alamat Tujuan ---
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(25, 5, 'Kepada Yth', 0, 0);
        $pdf->Cell(5, 5, ':', 0, 0);
        $pdf->Cell(0, 5, 'PT. PERSADA ALAM JAYA', 0, 1);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(30, 5, '', 0, 0); // Offset
        $pdf->Cell(0, 5, 'U.p Bapak M Nur Kholis', 0, 1);
        $pdf->Cell(30, 5, '', 0, 0);
        $pdf->Cell(0, 5, 'di', 0, 1);
        $pdf->Cell(30, 5, '', 0, 0);
        $pdf->Cell(0, 5, 'Suban - Batang Asam', 0, 1);

        $pdf->Ln(15);
        $pdf->Cell(0, 5, 'Bersama ini kami mengajukan penagihan sebagai berikut:', 0, 1);
        $pdf->Ln(2);

        // --- Tabel Rincian Biaya ---
        $pdf->SetFont('DejaVu', 'B', 10);
        // Header Tabel
        $pdf->Cell(10, 7, 'No', 1, 0, 'C');
        $pdf->Cell(130, 7, 'URAIAN', 1, 0, 'C');
        $pdf->Cell(50, 7, 'JUMLAH (Rp)', 1, 1, 'C');

        $pdf->SetFont('DejaVu', '', 9);
        // Baris 1
        $pdf->Cell(10, 14, '1.', 1, 0, 'C');
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell(130, 7, "Pengujian Sampel Kualitas Air Limbah Produksi Bulan\nDesember Tahun 2025 PT. Persada Alam Jaya.", 1, 'L');
        $pdf->SetXY($x + 130, $y);
        $pdf->Cell(50, 14, '580.000,00', 1, 1, 'R');

        // Baris 2
        $pdf->Cell(10, 14, '2.', 1, 0, 'C');
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell(130, 7, "Pengujian Sampel Kualitas Air Limbah Domestik Bulan\nDesember Tahun 2025 PT. Persada Alam Jaya.", 1, 'L');
        $pdf->SetXY($x + 130, $y);
        $pdf->Cell(50, 14, '500.000,00', 1, 1, 'R');

        // Baris 3 (PPN)
        $pdf->Cell(10, 7, '3.', 1, 0, 'C');
        $pdf->Cell(130, 7, 'PPN', 1, 0, 'L');
        $pdf->Cell(50, 7, '118.800,00', 1, 1, 'R');

        // Baris 4 (PPh 23)
        $pdf->Cell(10, 7, '4.', 1, 0, 'C');
        $pdf->Cell(130, 7, 'PPh 23 (2%)', 1, 0, 'L');
        $pdf->Cell(50, 7, '(21.600,00)', 1, 1, 'R');

        // Baris Terbilang & Total
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(140, 7, 'Terbilang :', 1, 0, 'L');
        $pdf->Cell(50, 14, '1.177.200,00', 1, 0, 'R'); // Digabung dengan baris bawahnya
        $pdf->Ln(7);
        $pdf->SetFont('DejaVu', 'BI', 10);
        $pdf->Cell(140, 7, 'Satu juta seratus tujuh puluh tujuh ribu dua ratus rupiah', 1, 1, 'L');

        $pdf->Ln(5);

        // --- Informasi Transfer ---
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(50, 5, 'Transfer Pembayaran Kepada', 0, 0);
        $pdf->Cell(5, 5, ':', 0, 1);

        $pdf->Cell(50, 5, 'Bank', 0, 0);
        $pdf->Cell(5, 5, ':', 0, 0);
        $pdf->Cell(0, 5, 'BNI Cabang Jambi ;', 0, 1);

        $pdf->Cell(50, 5, 'Rekening Giro Nomor', 0, 0);
        $pdf->Cell(5, 5, ':', 0, 0);
        $pdf->Cell(0, 5, '0298820772 ;', 0, 1);

        $pdf->Cell(50, 5, 'Atas Nama', 0, 0);
        $pdf->Cell(5, 5, ':', 0, 0);
        $pdf->Cell(0, 5, 'PT. JAMBI LESTARI INTERNASIONAL.', 0, 1);

        $pdf->Ln(5);
        $pdf->Cell(0, 5, 'Setelah pembayaran, bukti transfer mohon di emailkan ke: jli_lab@jli.co.id.', 0, 1);

        // --- Tanda Tangan ---
        $pdf->Ln(10);
        $startX = 120;
        $pdf->SetX($startX);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(0, 5, 'Jalint Lab', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->Cell(0, 5, 'a.n Direktur', 0, 1, 'L');

        $pdf->Ln(20);

        $pdf->SetX($startX);
        $pdf->Cell(0, 5, 'Refni Azmalia, S.E', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(0, 5, 'Manajer Keuangan', 0, 1, 'L');
    }
}
