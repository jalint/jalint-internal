<?php

namespace App\Http\Controllers;

use App\Library\Tfpdf\LhpFinalTFPDF;
use App\Models\LhpDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LhpFinalPdfController extends Controller
{
    public function printLhpFinal(Request $request)
    {
        $pdf = new LhpFinalTFPDF();
        $pdf->showFooter = false; // Aktifkan jika ingin nomor dokumen di bawah
        $pdf->lhpFinalFooter = true; // Aktifkan jika ingin nomor dokumen di bawah
        $pdf->AddPage('P');
        $pdf->SetMargins(20, 20, 20);

        $lhp = LhpDocument::with(['offer.customer.customerContact', 'details.sampleMatrix', 'details.lhpDocumentParameters.offerSampleParameter.testParameter.testMethod'])->findOrFail($request->id);
        $this->generateLHPClean($pdf, $lhp);

        $pdf->AddPage('P');
        $pdf->SetMargins(10, 10, 10);
        $this->testLhp($pdf, $lhp);

        $dataHeaders = $this->getDataHeaderLHP($lhp);
        // dd($dataHeaders);
        foreach ($dataHeaders as $header) {
            $pdf->AddPage('P');
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(false);
            $this->generateCertificate($pdf, $lhp, $header);
        }

        return response($pdf->Output('S', 'lhp_final.pdf'))
                ->header('Content-Type', 'application/pdf');
    }

    public function generateLHPClean($pdf, $lhp)
    {
        // --- JUDUL & HEADER ---
        $pdf->SetFont('DejaVu', 'BU', 14);
        $pdf->Cell(0, 7, 'LAPORAN HASIL PENGUJIAN', 0, 1, 'C');

        $pdf->SetFont('DejaVu', 'BI', 11);
        $pdf->Cell(0, 6, 'CERTIFICATE OF ANALYSIS', 0, 1, 'C');

        $pdf->SetFont('PlusJakartaSans', 'B', 12);
        $pdf->SetTextColor(0, 102, 204);
        $nomorLhp = $lhp->job_number;
        $pdf->Cell(0, 7, $nomorLhp, 0, 1, 'C');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(10);
        $totalSample = $lhp->offer->samples()->count();
        // --- INFORMASI PELANGGAN (DINAMIS & ANTI-OVER) ---
        $wLabel = 60;
        $wDot = 5;

        // Nama Pelanggan dibuat Size 14 Bold sesuai permintaan
        $this->renderInfoRow($pdf, "Nama Pelanggan/\nCustomer", $lhp->offer->customer->name, $wLabel, $wDot, 14);

        // Informasi lainnya menggunakan size standar 10
        $this->renderInfoRow($pdf, "Personil Penghubung/\nContact Person", $lhp->offer->customer->customerContact->name ?? '-', $wLabel, $wDot, 10);
        $this->renderInfoRow($pdf, "Alamat Lengkap/\nAddress", $lhp->offer->customer->address, $wLabel, $wDot, 10);
        $this->renderInfoRow($pdf, "Nama Kegiatan/\nProject Name", $lhp->offer->title, $wLabel, $wDot, 10, true);
        $this->renderInfoRow($pdf, "Jumlah Contoh Uji/\nSamples", $totalSample, $wLabel, $wDot, 10);

        // --- TANDA TANGAN ---
        $pdf->Ln(12);
        $pdf->SetFont('PlusJakartaSans', '', 10);
        $tanggal = Carbon::parse($lhp->tanggal_diterima)->translatedFormat('d F Y');
        $pdf->Cell(60, 5, 'Jambi, '.$tanggal, 0, 1, 'L');

        $pdf->Ln(25);
        $pdf->SetFont('PlusJakartaSans', 'BU', 10);
        $pdf->Cell(60, 5, 'Jumaida Panggabean, S.Si', 0, 1, 'L');
        $pdf->SetFont('PlusJakartaSans', '', 10);
        $pdf->Cell(60, 5, 'Kepala Laboratorium', 0, 1, 'L');

        // --- FOOTER SECTION ---
        $pdf->SetY(-85);
        if (file_exists(public_path('assets/images/qr.png'))) {
            $pdf->Image(public_path('assets/images/qr.png'), 20, $pdf->GetY() - 6, 22, 20);
        }

        $pdf->SetFont('PlusJakartaSans', 'B', 12);
        $pdf->SetTextColor(0, 102, 204);
        $pdf->Cell(0, 6, 'JALINT LAB', 0, 1, 'C');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('PlusJakartaSans', '', 8);
        $pdf->Cell(0, 4, 'Jl. Nusa Indah I, No. 59E-F, Kel. Rawasari, Kec. Alam Barajo, Kota Jambi, Provinsi Jambi', 0, 1, 'C');
        $pdf->Cell(0, 4, 'Telepon : 0741-3071716 - WA : 08117447787 - Website : www.jambilestari.co.id', 0, 1, 'C');

        // --- KOTAK DISCLAIMER ---
        $pdf->Ln(8);
        $pdf->SetFont('PlusJakartaSans', '', 8);
        $disclaimer = 'Laporan ini dibuat berdasarkan hasil observasi yang objektif dan independen terhadap sampel pelanggan yang bersifat khusus dan rahasia. Data hasil pengujian, interpretasi, dan pendapat-pendapat yang ada di dalamnya mewakili penilaian terbaik dari PT. Jambi Lestari Internasional. Dalam hal penggunaan laporan ini, PT. Jambi Lestari Internasional tidak membuat jaminan secara tersirat maupun tersurat dan tidak bertanggung jawab terhadap produktivitas, kegiatan operasional, ataupun kerugian lainnya yang bersifat materil maupun imaterial. Laporan ini tidak diperbolehkan untuk digandakan, kecuali secara utuh keseluruhannya dan atas persetujuan tertulis dari PT. Jambi Lestari Internasional.';

        // --- KONFIGURASI PADDING ---
        $padding = 3; // Jarak padding dalam mm
        $lebarKotak = 170; // Sesuaikan dengan lebar margin (210 - margin kiri - margin kanan)

        // Simpan koordinat awal
        $currX = $pdf->GetX();
        $currY = $pdf->GetY();

        // 1. Hitung tinggi yang dibutuhkan teks terlebih dahulu (tanpa mencetak)
        // Kita gunakan NbLines untuk tahu berapa baris yang akan dihasilkan
        $nbLines = $pdf->NbLines($lebarKotak - ($padding * 2), $disclaimer);
        $tinggiTeks = $nbLines * 3.5; // 3.5 adalah line-height yang Anda gunakan
        $tinggiKotak = $tinggiTeks + ($padding * 2);

        // 2. Gambar Kotak (Background & Border)
        // Kita gunakan Rect agar bisa mengontrol border secara independen
        $pdf->Rect($currX, $currY, $lebarKotak, $tinggiKotak);

        // 3. Cetak Teks di Dalam Kotak dengan Offset Padding
        $pdf->SetXY($currX + $padding, $currY + $padding);
        $pdf->MultiCell($lebarKotak - ($padding * 2), 3.5, $disclaimer, 0, 'J');

        // Kembalikan posisi Y ke bawah kotak agar tidak tumpang tindih dengan elemen berikutnya
        $pdf->SetY($currY + $tinggiKotak + 5);
    }

    public function generateCertificate($pdf, $lhp, $headers)
    {
        $pdf->SetFont('PlusJakartaSans', '', 8);

        // --- BAGIAN HEADER ---
        $pdf->SetFont('PlusJakartaSans', 'BU', 12);
        $pdf->Cell(0, 6, 'LAPORAN HASIL PENGUJIAN', 0, 1, 'C');

        $pdf->SetFont('PlusJakartaSans', 'BI', 11);
        $pdf->Cell(0, 6, 'CERTIFICATE OF ANALYSIS', 0, 1, 'C');

        $pdf->SetFont('PlusJakartaSans', 'B', 11);
        $pdf->Cell(0, 6, $lhp->job_number, 0, 1, 'C');

        $pdf->SetFont('PlusJakartaSans', 'B', 11);
        $pdf->Cell(0, 6, Str::upper($lhp->offer->customer->name), 0, 1, 'C');

        $pdf->Ln(5);

        // --- DEFINISI KOLOM TABEL ATAS ---
        // Total = 190mm
        $w = [
            45, // Identifikasi Lab
            75, // Identifikasi Contoh Uji
            35, // Matriks
            35, // Tanggal
        ];

        $tableStartY = $pdf->GetY();

        // --- HEADER TABEL ATAS ---
        $pdf->SetFont('PlusJakartaSans', '', 8);
        $headerIndo = ['Identifikasi Laboratorium/', 'Identifikasi Contoh Uji/', 'Matriks/', 'Tanggal Pengambilan/'];
        $headerEng = ['Laboratory Identification', 'Sampel Identification', 'Matrix', 'Date of Sampling'];

        $xStart = $pdf->GetX();
        $yStart = $pdf->GetY();

        for ($i = 0; $i < count($w); ++$i) {
            $pdf->SetXY($xStart, $yStart);
            $pdf->Line($xStart, $yStart, $xStart + $w[$i], $yStart); // Garis atas
            $pdf->Line($xStart, $yStart + 12, $xStart + $w[$i], $yStart + 12); // Garis bawah

            $pdf->SetFont('DejaVu', '', 8);
            $pdf->SetXY($xStart, $yStart + 2);
            $pdf->Cell($w[$i], 4, $headerIndo[$i], 0, 0, 'C');

            $pdf->SetFont('DejaVu', 'I', 9);
            $pdf->SetXY($xStart, $yStart + 6);
            $pdf->Cell($w[$i], 4, $headerEng[$i], 0, 0, 'C');

            $xStart += $w[$i];
        }

        $pdf->SetXY($pdf->GetX() - array_sum($w), $yStart + 13);

        // --- DATA TABEL ATAS ---
        // $headers = [
        //     ['LAB-JLI-2503309A -1/9', 'ATsp-2 (Sumur Pantau Blok B) Maju Terus', 'Air Sumur Pantau Indonesia', '04/03/2025'],
        //     ['LAB-JLI-2503309A -2/9', 'ATsp-2 (Sumur Pantau Blok B)', 'Air Sumur Pantau', '04/03/2025'],
        //     ['LAB-JLI-2503309A -3/9', 'ATsp-3 (Sumur Pantau Blok C)', 'Air Sumur Pantau', '04/03/2025'],
        //     ['LAB-JLI-2503309A -4/9', 'ATsp-4 (Sumur Pantau Blok E)', 'Air Sumur Pantau', '04/03/2025'],
        //     ['LAB-JLI-2503309A -5/9', 'ATsp-5 (Sumur Pantau Blok G)', 'Air Sumur Pantau', '04/03/2025'],
        // ];

        $pdf->SetFont('DejaVu', '', 8);
        foreach ($headers['data'] as $row) {
            $pdf->Cell($w[0], 6, $row[0], 0, 0, 'C');
            $pdf->Cell($w[1], 6, $row[1], 0, 0, 'C');
            $pdf->Cell($w[2], 6, $row[2], 0, 0, 'C');
            $pdf->Cell($w[3], 6, $row[3], 0, 0, 'C');
            $pdf->Ln();
        }

        $tableHeight = $pdf->GetY() - $tableStartY;
        $pdf->Rect(10, $tableStartY, array_sum($w), $tableHeight);

        // ============================================================
        // MULAI TABEL BAWAH (YANG DIPERBAIKI)
        // ============================================================

        $pdf->Ln(3); // Jarak antar tabel

        // Kita perkecil sedikit font untuk tabel bawah agar muat di portrait
        $pdf->SetFont('DejaVu', '', 7);
        $startX = 10;

        // --- INPUT DINAMIS ---
        $dynamicSubHeaders = $headers['headers'];
        $countResults = count($dynamicSubHeaders);

        // --- 2. HITUNG LEBAR KOLOM (DISESUAIKAN KE 190mm) ---
        // Total Lebar Portrait = 190mm.
        // Kita harus mengecilkan kolom tetap agar sisa ruang untuk hasil cukup.

        $w_no = 8;     // Dikecilkan dari 10
        $w_param = 50; // Dikecilkan dari 70 agar muat
        $w_bml = 15;   // Dikecilkan dari 20
        $w_unit = 12;  // Dikecilkan dari 20
        $w_method = 25; // Dikecilkan dari 40

        $totalFixedW = $w_no + $w_param + $w_bml + $w_unit + $w_method; // Total = 110mm

        // Hitung sisa untuk kolom Hasil
        // 190mm (Total) - 110mm (Fixed) = 80mm Sisa
        $availableForResults = 190 - $totalFixedW;

        // Lebar per kolom hasil
        $w_res_item = $availableForResults / $countResults;

        // Build Array Lebar ($w) untuk tabel bawah
        $w2 = [];
        $w2[] = $w_no;
        $w2[] = $w_param;
        for ($i = 0; $i < $countResults; ++$i) {
            $w2[] = $w_res_item;
        }
        $w2[] = $w_bml;
        $w2[] = $w_unit;
        $w2[] = $w_method;

        // --- 3. GAMBAR HEADER TABEL BAWAH ---
        $h_total = 12;
        $h_half = 6;
        $currX = $startX;
        $currY = $pdf->GetY();

        $pdf->SetFont('DejaVu', 'B', 7);
        $pdf->SetFillColor(220, 240, 255);

        // A. Header Kiri (NO, PARAMETER)
        $leftHeaders = ['NO', 'PARAMETER'];
        $leftW = [$w_no, $w_param];
        for ($i = 0; $i < 2; ++$i) {
            $pdf->SetXY($currX, $currY);
            $pdf->Rect($currX, $currY, $leftW[$i], $h_total, 'DF');
            $pdf->SetXY($currX, $currY + 4);
            $pdf->Cell($leftW[$i], 4, $leftHeaders[$i], 0, 0, 'C');
            $currX += $leftW[$i];
        }

        // B. Header Tengah (HASIL / RESULT)
        $totalWResult = $w_res_item * $countResults;
        $pdf->SetXY($currX, $currY);
        $pdf->Cell($totalWResult, $h_half, 'HASIL/RESULT', 1, 0, 'C', true);

        $pdf->SetXY($currX, $currY + $h_half);
        foreach ($dynamicSubHeaders as $sub) {
            $pdf->Cell($w_res_item, $h_half, $sub, 1, 0, 'C', true);
        }
        $currX += $totalWResult;

        // C. Header Kanan (BML, SATUAN, METODE)
        $rightHeaders = ["BML/\nEQS*", "SATUAN/\nUNIT", "METODE/\nMETHOD"];
        $rightW = [$w_bml, $w_unit, $w_method];
        for ($i = 0; $i < 3; ++$i) {
            $pdf->SetXY($currX, $currY);
            $pdf->Rect($currX, $currY, $rightW[$i], $h_total, 'DF');
            $pdf->SetXY($currX, $currY + 2);
            $pdf->MultiCell($rightW[$i], 3, $rightHeaders[$i], 0, 'C');
            $currX += $rightW[$i];
        }

        // --- 4. DATA TABEL BAWAH ---
        $dataRows = [];
        // Row 1
        $row1 = ['1', 'Suhu (Temperature)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row1[] = '26.5';
        }
        array_push($row1, '-', '°C', 'SNI 06-6989.23-2005');
        $dataRows[] = $row1;

        // Row 2
        $row2 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row2[] = '2.31';
        }
        array_push($row2, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row2;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        $row3 = ['2', 'Kebutuhan Oksigen Biokimiawi (Biochemical Oxygen Demand, BOD5)'];
        for ($k = 0; $k < $countResults; ++$k) {
            $row3[] = '2.31';
        }
        array_push($row3, '20', 'mg/L', 'SNI 6989.72:2009');
        $dataRows[] = $row3;

        // --- 5. RENDER ISI TABEL BAWAH ---
        $pdf->SetFont('DejaVu', '', 7);
        $yCurrent = $currY + $h_total;

        foreach ($dataRows as $row) {
            $xCurrent = $startX;

            // Hitung Tinggi Baris
            $maxH = 5;
            for ($i = 0; $i < count($w2); ++$i) {
                $pdf->SetXY(300, 0);
                $pdf->MultiCell($w2[$i], 3, $row[$i], 0, 'L'); // Font size 7 line height 3
                if ($pdf->GetY() > $maxH) {
                    $maxH = $pdf->GetY();
                }
            }
            if ($maxH > 5) {
                $maxH += 2;
            }

            // Cek Page Break (Penting: Jangan ganti Landscape, tetap Portrait)
            if ($yCurrent + $maxH > $pdf->GetPageHeight() - 20) {
                $pdf->AddPage(); // Default Portrait
                $yCurrent = 10;
                // Opsional: Gambar Header lagi
            }

            // Gambar Kotak & Data
            // B. GAMBAR KOTAK & TEKS
            for ($i = 0; $i < count($w2); ++$i) {
                // 1. Gambar Kotak (Border) Full Height
                // Kotak tetap digambar dari Y paling atas agar rapi
                $pdf->SetXY($xCurrent, $yCurrent);
                $pdf->Rect($xCurrent, $yCurrent, $w2[$i], $maxH, 'D');

                // 2. HITUNG TINGGI TEKS CUMA UNTUK KOLOM INI
                // Kita perlu tahu teks ini tingginya berapa baris untuk ngitung center
                $pdf->SetXY(300, 0); // Pindah ke luar layar sebentar
                $pdf->MultiCell($w2[$i], 3, $row[$i], 0, 'L'); // Simulasi tulis
                $currentTextH = $pdf->GetY(); // Dapatkan tinggi teks aktual

                // 3. HITUNG POSISI Y AGAR CENTER
                // Rumus: Y_Awal + ((Tinggi_Kotak - Tinggi_Teks) / 2)
                $yCenter = $yCurrent + (($maxH - $currentTextH) / 2);

                // 4. ATUR POSISI X & Y BARU
                $pdf->SetXY($xCurrent, $yCenter);

                // 5. TENTUKAN ALIGNMENT HORIZONTAL (Left/Center)
                $align = 'C';
                // Param (Index 1) & Metode (Index Terakhir) rata Kiri
                if ($i == 1 || $i == count($w2) - 1) {
                    $align = 'L';
                }

                // 6. TULIS TEKS SEBENARNYA
                $pdf->MultiCell($w2[$i], 3, $row[$i], 0, $align);

                // Geser X ke kolom berikutnya
                $xCurrent += $w2[$i];
            }
            $yCurrent += $maxH;
        }

        // --- 6. BAGIAN KETERANGAN (NOTES) YANG RAPI ---
        $pdf->Ln(5); // Jarak dari tabel

        $pdf->SetFont('DejaVu', '', 6); // Font Kecil

        // Konfigurasi Posisi
        $xSymbol = 10;  // Posisi X untuk Simbol (*, #, <)
        $xText = 14;  // Posisi X untuk Teks (Indented)
        $wText = 186; // Lebar area teks (Total 200 - Margin)
        $hLine = 3;   // Tinggi per baris teks

        // --- ITEM 1: BML (*) ---
        // 1. Simbol
        $pdf->SetXY($xSymbol, $pdf->GetY());
        $pdf->Cell(4, $hLine, '(*)', 0, 0, 'L');

        // 2. Teks Indo
        $pdf->SetXY($xText, $pdf->GetY());
        $text1_indo = 'BML adalah Baku Mutu Lingkungan Sesuai Peraturan Menteri Kesehatan Republik Indonesia No. 02 Tahun 2023 Tentang Pelaksanaan Peraturan Pemerintah Nomor 66 Tahun 2014 Tentang Kesehatan Lingkungan. (Lampiran: Bab II A Bagian 2.a, Tabel 3 : Standar Baku Mutu Kesehatan Lingkungan, Parameter Air Untuk Keperluan Higiene dan Sanitasi)';
        $pdf->MultiCell($wText, $hLine, $text1_indo, 0, 'J'); // 'J' = Justify (Rata Kiri Kanan) agar rapi kotak

        // 3. Teks Inggris (Italic) - Langsung di bawahnya tapi tetap indented
        $pdf->SetXY($xText, $pdf->GetY()); // Pastikan X kembali ke posisi Text (14)
        $pdf->SetFont('DejaVu', 'I', 6);
        $text1_eng = 'EQS is Environmental Quality Standards According to the Regulation of the Minister of Health of the Republic of Indonesia No. 02 of 2023 concerning Implementation of Government Regulation Number 66 of 2014 concerning Environmental Health. (Attachment: Chapter II A Part 2.a, Table 3 : Environmental Health Quality Standards, Water Parameters for Hygiene and Sanitation Purposes)';
        $pdf->MultiCell($wText, $hLine, $text1_eng, 0, 'J');

        $pdf->Ln(1); // Spasi antar item

        // --- ITEM 2: Parameter Belum Terakreditasi (#) ---
        $pdf->SetFont('DejaVu', '', 6);

        // Simbol
        $pdf->SetXY($xSymbol, $pdf->GetY());
        $pdf->Cell(4, $hLine, '(#)', 0, 0, 'L');

        // Teks
        $pdf->SetXY($xText, $pdf->GetY());
        $pdf->MultiCell($wText, $hLine, 'Parameter belum terakreditasi / Parameters not accredited', 0, 'L');

        $pdf->Ln(1);

        // --- ITEM 3: Kurang Dari (<) ---
        // Simbol
        $pdf->SetXY($xSymbol, $pdf->GetY());
        $pdf->Cell(4, $hLine, '<', 0, 0, 'L');

        // Teks Indo
        $pdf->SetXY($xText, $pdf->GetY());
        $pdf->MultiCell($wText, $hLine, 'Menunjukan nilai terkecil dari pengukuran yang didapatkan berdasarkan metode yang berlaku', 0, 'L');

        // Teks Inggris (Italic)
        $pdf->SetXY($xText, $pdf->GetY());
        $pdf->SetFont('DejaVu', 'I', 6);
        $pdf->MultiCell($wText, $hLine, 'Shows the smallest value of the measurements obtained based on the method that applies', 0, 'L');

        $pdf->Ln(1);

        // --- ITEM 4: Metode Pengambilan (^) ---
        $pdf->SetFont('DejaVu', '', 6);

        // Simbol
        $pdf->SetXY($xSymbol, $pdf->GetY());
        $pdf->Cell(4, $hLine, '(^)', 0, 0, 'L');

        // Teks
        $pdf->SetXY($xText, $pdf->GetY());
        $pdf->MultiCell($wText, $hLine, 'Metode pengambilan contoh uji : SNI 8995:2021 / Sampling Method : SNI 8995:2021', 0, 'L');

        // --- 7. DISCLAIMER (PALING BAWAH, BUKAN FOOTER) ---
        $pdf->Ln(6); // Beri jarak agak jauh dari notes di atas

        $pdf->SetFont('DejaVu', 'B', 7); // Bold
        // Gunakan Cell(0) agar otomatis Center di tengah halaman
        $pdf->Cell(0, 4, 'Hasil hanya berhubungan dengan contoh yang diuji dan laporan ini tidak boleh digandakan kecuali seluruhnya.', 0, 1, 'C');

        $pdf->SetFont('DejaVu', 'BI', 7); // Bold Italic
        $pdf->Cell(0, 4, 'The result relate only to the samples tested and this report shall not be reproduced except in full.', 0, 1, 'C');
    }

    public function testLhp($pdf, $lhp)
    {
        // --- SETUP AWAL ---
        $startX = $pdf->GetX() - 10; // Ambil posisi margin kiri saat ini (misal 10mm)
        $totalWidth = 190;      // Lebar total tabel (Pastikan ini konsisten)

        // Setup Lebar Kolom
        $w1 = 70;  // Label
        $w2 = 5;   // Titik dua
        $w3 = 115; // Isi (190 - 70 - 5 = 115)

        // --- BAGIAN JUDUL (HEADER) ---
        $pdf->SetFont('DejaVu', 'B', 12);

        // BARIS 1: JUDUL UTAMA
        $pdf->SetX($startX); // <--- PAKSA X KEMBALI KE KIRI
        $pdf->Cell($totalWidth, 8, 'INFORMASI CONTOH UJI', 'LTR', 1, 'C');

        // BARIS 2: SUB JUDUL
        $pdf->SetFont('DejaVu', 'BI', 10);
        $pdf->SetX($startX); // <--- PAKSA X KEMBALI KE KIRI
        $pdf->Cell($totalWidth, 4, 'SAMPLE INFORMATION', 'LBR', 1, 'C');

        // --- BAGIAN ISI (LOOPING) ---
        $pdf->SetFont('DejaVu', '', 10);

        $info = [
            ['Nomor Pekerjaan/Job Number', $lhp->job_number],
            ['Nama Pelanggan/Customer', $lhp->offer->customer->name],
            ['Personil Penghubung/Contact Person', $lhp->offer->customer->customerContact->name],
            ['Tanggal Dilaporkan/Reported Date', $lhp->tanggal_diterima],
        ];

        foreach ($info as $row) {
            // KUNCI UTAMA ADA DI SINI:
            // Sebelum menggambar kolom pertama, RESET posisi X ke $startX
            $pdf->SetX($startX);

            // Kolom 1
            $pdf->Cell($w1, 6, $row[0], 'L', 0, 'L');

            // Kolom 2
            $pdf->Cell($w2, 6, ':', 0, 0, 'C');

            // Kolom 3
            $pdf->Cell($w3, 6, $row[1], 'R', 1, 'L');
        }

        // --- BAGIAN PENUTUP (GARIS BAWAH) ---
        $pdf->SetX($startX); // <--- PAKSA X KEMBALI KE KIRI
        $pdf->Cell($totalWidth, 0, '', 'T', 1);

        // Bawah Nya
        $pdf->ln(3);

        // --- 1. SETUP UKURAN & FONT ---
        $pdf->SetFont('DejaVu', 'B', 10);
        $startX = $pdf->GetX();
        if ($startX < 5) {
            $startX = 10;
        } // Margin safety

        // Definisi Lebar Kolom (Total 190mm)
        $w = [
            30, // 0: ID Lab
            29, // 1: ID Contoh
            16, // 2: Matriks
            16, // 3: Tgl Ambil
            16, // 4: Waktu Ambil
            15, // 5: Tgl Terima
            15, // 6: Waktu Terima
            15, // 7: Waktu Analisis
            19, // 8: Lintang
            19, // 9: Bujur
        ];

        // --- 2. HEADER TABEL ---
        $pdf->SetFont('DejaVu', '', 5.5);
        $h_header = 18;
        $yHeader = $pdf->GetY();
        $currentX = $startX;

        $headers = [
            "Identifikasi Laboratorium\nLaboratory Identification",
            "Identifikasi Contoh Uji\nSample Identification",
            "Matriks\Matrix",
            "Tanggal\nPengambilan\\\nDate of\nSampling",
            "Waktu\nPengambilan\\\nTime of\nSampling",
            "Tanggal\nPenerimaan\\\nDate of\nReceived",
            "Waktu\nPenerimaan\\\nTime of\nReceived",
            "Waktu\nAnalisis\\\nTime of\nAnalysis",
        ];

        // A. Header Biasa (Kolom 0-7)
        for ($i = 0; $i < 8; ++$i) {
            $pdf->SetXY($currentX, $yHeader);
            $pdf->Rect($currentX, $yHeader, $w[$i], $h_header); // Kotak Header

            // Logika Align Header: Kolom 0 (Left), Sisa (Center)
            $alignHeader = ($i == 0) ? 'L' : 'C';

            $pdf->SetXY($currentX, $yHeader + 2);
            $pdf->MultiCell($w[$i], 2.5, $headers[$i], 0, $alignHeader);
            $currentX += $w[$i];
        }

        // B. Header Koordinat (Kolom 8 & 9)
        $pdf->SetXY($currentX, $yHeader);
        $wCoord = $w[8] + $w[9];
        $pdf->Cell($wCoord, 8, 'Koordinat / Coordinate', 1, 2, 'C');

        $xSub = $currentX;
        $ySub = $pdf->GetY();
        $pdf->SetXY($xSub, $ySub);
        $pdf->Cell($w[8], 10, 'Lintang/Latitude', 1, 0, 'C');
        $pdf->Cell($w[9], 10, 'Bujur/Longitude', 1, 0, 'C');

        // --- 3. ISI DATA (LOOPING) ---
        $pdf->SetFont('DejaVu', '', 6);
        $yStartData = $yHeader + $h_header;
        $pdf->SetXY($startX, $yStartData);

        // Data Dummy
        // 1. Ambil Nomor Job
        $lhpNomor = $lhp->job_number;

        // 2. Hitung total detail untuk penyebut (contoh: per /9)
        $totalDetails = $lhp->details->count();

        $data = [];

        // 3. Loop data details dengan $index untuk penomoran
        foreach ($lhp->details as $index => $detail) {
            // Helper: Format tanggal analisis (misal: 06/03 - 08/03)
            // Asumsi field di DB adalah format date/datetime standar
            $startAnalisis = $detail->waktu_analisis_start ? Carbon::parse($detail->waktu_analisis_start)->format('d/m') : '-';
            $endAnalisis = $detail->waktu_analisis_end ? Carbon::parse($detail->waktu_analisis_end)->format('d/m') : '-';
            $rangeAnalisis = "{$startAnalisis} - {$endAnalisis}";

            // Helper: Format Tanggal Pengambilan & Penerimaan (dd/mm/yyyy)
            $tglPengambilan = $detail->tanggal_pengambilan ? Carbon::parse($detail->tanggal_pengambilan)->format('d/m/Y') : '-';
            $tglPenerimaan = $detail->tanggal_penerimaan ? Carbon::parse($detail->tanggal_penerimaan)->format('d/m/Y') : '-';

            // Helper: Format Waktu (H:i) -> Ambil jam:menit saja
            $waktuPengambilan = $detail->waktu_pengambilan ? Carbon::parse($detail->waktu_pengambilan)->format('H:i') : '-';
            $waktuPenerimaan = $detail->waktu_penerimaan ? Carbon::parse($detail->waktu_penerimaan)->format('H:i') : '-';

            // Susun Array
            $data[] = [
                // Kolom 1: Job Number + Index/Total (cth: LAB-JLI-2503309A\n-1/9)
                "{$lhpNomor}-".($index + 1)."/{$totalDetails}",

                // Kolom 2: Identifikasi Contoh Uji
                $detail->identifikasi_contoh_uji,

                // Kolom 3: Matriks Sample
                $detail->sampleMatrix->name ?? '-',

                // Kolom 4 & 5: Tgl & Waktu Pengambilan
                $tglPengambilan,
                $waktuPengambilan,

                // Kolom 6 & 7: Tgl & Waktu Penerimaan
                $tglPenerimaan,
                $waktuPenerimaan,

                // Kolom 8: Range Analisis
                $rangeAnalisis,

                // Kolom 9: Koordinat Lintang (Tambah prefix LS jika belum ada)
                'LS: '.$detail->koordinat_lintang,

                // Kolom 10: Koordinat Bujur (Tambah prefix BT jika belum ada)
                'BT: '.$detail->koordinat_bujur,
            ];
        }
        foreach ($data as $row) {
            $yStartRow = $pdf->GetY();
            $xStartRow = $startX;

            // A. UKUR TINGGI BARIS (Invisible Measure)
            $maxH = 5; // Minimal tinggi 5mm
            for ($i = 0; $i < count($w); ++$i) {
                $pdf->SetXY(300, 0); // Pindah keluar layar
                $pdf->MultiCell($w[$i], 3, $row[$i], 0, 'L');
                if ($pdf->GetY() > $maxH) {
                    $maxH = $pdf->GetY();
                }
            }

            // Cek Page Break
            if ($yStartRow + $maxH > $pdf->GetPageHeight() - 30) {
                $pdf->AddPage();
                $yStartRow = $pdf->GetY(); // Reset Y di halaman baru
                // (Idealnya gambar header ulang disini, tapi kita skip biar simple)
            }

            // B. GAMBAR DATA & GARIS VERTIKAL (TANPA GARIS HORIZONTAL)
            $currentX = $xStartRow;
            for ($i = 0; $i < count($w); ++$i) {
                // 1. Gambar Garis Vertikal Kiri & Kanan SAJA (Bukan Rect penuh)
                // Ini membuat efek "Polos" (tanpa garis horizontal antar baris)
                $pdf->Line($currentX, $yStartRow, $currentX, $yStartRow + $maxH); // Kiri
                $pdf->Line($currentX + $w[$i], $yStartRow, $currentX + $w[$i], $yStartRow + $maxH); // Kanan

                // 2. Tentukan Alignment Data
                // Kolom 0 & 1 = Left, Sisanya = Center
                $alignData = ($i == 0 || $i == 1) ? 'L' : 'C';

                // 3. Tulis Teks
                $pdf->SetXY($currentX, $yStartRow);
                $pdf->MultiCell($w[$i], 3, $row[$i], 0, $alignData);

                $currentX += $w[$i];
            }
            // Pindah ke baris berikutnya
            $pdf->SetY($yStartRow + $maxH);
        }

        // --- 4. FILLER (PENGISI KEKOSONGAN SAMPAI BAWAH) ---
        // Tentukan batas bawah kertas (misal sisa 30mm dari bawah untuk footer tanda tangan)
        $batasBawah = $pdf->GetPageHeight() - 30;
        $posisiYTerakhir = $pdf->GetY();

        // Hitung sisa ruang
        $sisaTinggi = $batasBawah - $posisiYTerakhir;

        if ($sisaTinggi > 0) {
            $currX = $startX;
            for ($i = 0; $i < count($w); ++$i) {
                // Tarik garis vertikal dari posisi data terakhir sampai batas bawah
                $pdf->Line($currX, $posisiYTerakhir, $currX, $batasBawah);     // Garis Kiri Kolom
                $pdf->Line($currX + $w[$i], $posisiYTerakhir, $currX + $w[$i], $batasBawah); // Garis Kanan Kolom

                $currX += $w[$i];
            }

            // Tutup dengan satu garis horizontal di paling bawah
            $pdf->Line($startX, $batasBawah, $startX + array_sum($w), $batasBawah);

            // Update posisi cursor PDF ke paling bawah
            $pdf->SetY($batasBawah);
        }
    }

    private function renderInfoRow($pdf, $label, $value, $wLabel, $wDot, $fontSize = 10, $isBold = false)
    {
        $startY = $pdf->GetY();
        $pdf->SetFont('DejaVu', '', 9);

        // Render Label (Mendukung baris baru \n)
        $pdf->MultiCell($wLabel, 4.5, $label, 0, 'L');
        $endYLabel = $pdf->GetY();

        // Render Titik Dua
        $pdf->SetXY($pdf->GetX() + $wLabel, $startY);
        $pdf->Cell($wDot, 6, ':', 0, 0, 'L');

        // Render Value dengan Font Custom
        $pdf->SetFont('DejaVu', ($fontSize > 10 || $isBold) ? 'B' : '', $fontSize);
        $pdf->MultiCell(0, 6, $value, 0, 'L');
        $endYValue = $pdf->GetY();

        // Set posisi Y untuk baris berikutnya (ambil posisi paling bawah antara label atau value)
        $pdf->SetY(max($endYLabel, $endYValue) + 2);
    }

    private function getDataHeaderLHPDebug($lhp)
    {
        $allDetails = $lhp->details->sortBy('id')->values();
        $totalGlobal = $allDetails->count();
        $jobNumber = $lhp->job_number;

        // 2. Mapping Nomor Global (1/10, 2/10, dst)
        $mappedDetails = $allDetails->map(function ($detail, $index) use ($totalGlobal, $jobNumber) {
            $nomorUrut = $index + 1;
            $detail->nomor_lengkap = "{$jobNumber}-{$nomorUrut}/{$totalGlobal}";

            $detail->tgl_sampling = $detail->tanggal_pengambilan
                ? Carbon::parse($detail->tanggal_pengambilan)->format('d/m/Y')
                : '-';

            return $detail;
        });

        // 3. Grouping berdasarkan Matrix ID
        $groupedDetails = $mappedDetails->groupBy('sample_matrix_id');

        $finalOutput = [];

        foreach ($groupedDetails as $matrixId => $items) {
            // --- INI YANG ANDA MINTA ---
            // Mengambil semua 'identifikasi_contoh_uji' dalam grup ini menjadi array simple
            $dynamicSubHeaders = $items->pluck('identifikasi_contoh_uji')->toArray();

            // Susun data baris (Rows) untuk tabel
            $rows = $items->map(function ($item) {
                return [
                    $item->nomor_lengkap,
                    $item->identifikasi_contoh_uji,
                    $item->sampleMatrix->name ?? '-',
                    $item->tgl_sampling,
                ];
            })->values()->toArray(); // values() untuk reset key array, toArray() ubah collection jadi array

            // Masukkan ke array utama per Halaman/Kelompok
            $finalOutput[] = [
                'headers' => $dynamicSubHeaders, // Array ['ATsp-1', 'ATsp-2', ...]
                'data' => $rows,               // Array data tabel
            ];
        }

        return $finalOutput;
    }

    private function getDataHeaderLHP($lhp)
    {
        $allDetails = $lhp->details->sortBy('id')->values();
        $totalGlobal = $allDetails->count();
        $jobNumber = $lhp->job_number;

        // Mapping Data Global
        $mappedDetails = $allDetails->map(function ($detail, $index) use ($totalGlobal, $jobNumber) {
            $nomorUrut = $index + 1;
            $detail->nomor_lengkap = "{$jobNumber}-{$nomorUrut}/{$totalGlobal}";
            $detail->tgl_sampling = $detail->tanggal_pengambilan
                ? Carbon::parse($detail->tanggal_pengambilan)->format('d/m/Y')
                : '-';

            return $detail;
        });

        // Grouping
        $groupedDetails = $mappedDetails->groupBy('sample_matrix_id');

        $finalOutput = [];

        foreach ($groupedDetails as $matrixId => $items) {
            // 1. Ambil Header Sample
            // $dynamicSubHeaders = $items->pluck('identifikasi_contoh_uji')->toArray();
            $dynamicSubHeaders = $items->pluck('identifikasi_contoh_uji')
                                        ->map(fn ($val) => trim(explode('(', $val)[0]))
                                        ->toArray();

            // 2. Ambil Data Row Detail
            $rows = $items->map(function ($item) {
                return [
                    $item->nomor_lengkap,
                    $item->identifikasi_contoh_uji,
                    $item->sampleMatrix->name ?? '-',
                    $item->tgl_sampling,
                ];
            })->values()->toArray();

            // --- BAGIAN BARU: AMBIL SEMUA PARAMETER ---
            // flatMap akan menggabungkan semua parameter dari 5 detail (misalnya) menjadi 1 array panjang
            $allParametersRaw = $items->flatMap(function ($detail) {
                return $detail->lhpDocumentParameters;
            });

            $this->processParameters($allParametersRaw);

            // Masukkan ke array output
            $finalOutput[] = [
                'headers' => $dynamicSubHeaders,
                'data' => $rows,

                // INI DATA YANG MAU ANDA LIHAT
                // Kita convert ke array biar mudah dibaca saat dd()
                'debug_parameters' => $allParametersRaw->toArray(),
            ];
        }

        return $finalOutput;
    }

    public function processParameters($parameters)
    {
    }
}
