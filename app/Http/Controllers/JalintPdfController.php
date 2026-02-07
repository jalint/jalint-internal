<?php

namespace App\Http\Controllers;

use App\Library\Fpdf\JalintPDF;
use App\Library\Tfpdf\JalintTFPDF;
use App\Models\Invoice;
use App\Models\LhpDocument;
use App\Models\Offer;
use App\Utils\AmountToWordsUtil;
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
        $wJab = 55;
        $wKet = 57;
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
        $pdf->SetWidths([10, 30, 50, 60, 15, 15]);
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
        $pdf->Cell(90, 7, 'Koordinat / Coordinate', 1, 1, 'C');
        $pdf->SetX($xPos + 90);
        $pdf->Cell(45, 7, 'Lintang / Latitude', 1, 0, 'C');
        $pdf->Cell(45, 7, 'Bujur / Longitude', 1, 1, 'C');

        $pdf->SetFont('DejaVu', '', 8);
        $pdf->SetWidths([50, 40, 45, 45]);
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

    public function generateFPPCU(Request $request, $id)
    {
        $pdf = new JalintTFPDF();
        $pdf->showFooter = false;
        $pdf->AliasNbPages();
        $pdf->AddPage('P', 'A4');

        $lhpDocument = LhpDocument::query()->where('id', $id)
         ->with(['offer.customer.customerContact', 'fppcu', 'fppcu.fppcuParameters'])
         ->firstOrFail();

        // dd($lhpDocument->offer->customer->name);

        $customerName = $lhpDocument->offer->customer->name;
        $alamat = $lhpDocument->offer->customer->address;
        $judulKegiatan = $lhpDocument->offer->title;
        $noTelp = $lhpDocument->offer->customer->customerContact->phone;
        $pic = $lhpDocument->offer->customer->customerContact->name;
        $picEmail = $lhpDocument->offer->customer->customerContact->email;
        $statusContohUji = $lhpDocument->status_contoh_uji;
        $tanggalDiterima = Carbon::parse($lhpDocument->tanggal_diterima)->format('d/m/Y');

        $noPara = 1;
        // Margin atas
        $pdf->SetTopMargin(10);

        // ======================
        // JUDUL
        // ======================
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 5, 'FORMULIR PERMINTAAN PENGUJIAN CONTOH UJI', 0, 1, 'C');

        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->Cell(0, 5, 'JOB NUMBER: LAB-JLI-.......................', 0, 1, 'C');
        $pdf->Ln(3);

        // ======================
        // INFORMASI PELANGGAN
        // ======================
        $pdf->SetFont('DejaVu', '', 9);
        $lineHeight = 6;

        $this->rowWithDots($pdf, 'Nama Pelanggan', $customerName, $lineHeight);
        $this->rowWithDots(
            $pdf,
            'Alamat',
            $alamat,
            $lineHeight
        );
        $this->rowWithDots($pdf, 'Personil Penghubung', $pic, $lineHeight);
        $this->rowWithDots($pdf, 'No. Telp/HP', $noTelp, $lineHeight);
        $this->rowWithDots($pdf, 'Email Penerima Laporan', $picEmail, $lineHeight);
        $this->rowWithDots(
            $pdf,
            'Nama Kegiatan',
            $judulKegiatan,
            $lineHeight
        );
        $this->rowWithDots($pdf, 'Tanggal Diterima', $tanggalDiterima, $lineHeight);

        $pdf->Ln(4);

        // ======================
        // HEADER TABEL
        // ======================
        $pdf->SetFont('DejaVu', 'B', 8);
        $hHeader = 9;

        $pdf->Cell(10, $hHeader, 'No', 1, 0, 'C');
        $pdf->Cell(30, $hHeader, 'Bahan Produk', 1, 0, 'C');
        $pdf->Cell(45, $hHeader, 'Jenis Wadah Contoh Uji', 1, 0, 'C');
        $pdf->Cell(35, $hHeader, 'Volume Contoh Uji', 1, 0, 'C');
        $pdf->Cell(30, $hHeader, 'Pengawetan', 1, 0, 'C');
        $pdf->Cell(35, $hHeader, 'Keterangan', 1, 1, 'C');

        // ======================
        // ISI TABEL
        // ======================
        $pdf->SetFont('DejaVu', '', 8);

        $data = $lhpDocument->fppcu->toArray();

        $rowHeight = 6;
        $totalRow = 21; // jumlah baris form (sisanya kosong)
        $rows = [];
        $noPara = 1;

        foreach ($data as $row) {
            $namaBahan = $row['nama_bahan_produk'] ?? '';
            $params = $row['fppcu_parameters'] ?? [];

            foreach ($params as $fp) {
                $rows[] = [
                    'no' => $noPara++,
                    'nama_bahan' => $namaBahan,
                    'jenis_wadah' => $fp['jenis_wadah'] ?? '',
                    'volume' => $fp['volume_contoh_uji'] ?? '',
                    'pengawetan' => $fp['pengawetan'] ?? '',
                    'keterangan' => $fp['keterangan'] ?? '',
                ];
            }
        }

        $pdf->SetFont('DejaVu', '', 8);
        $rowHeight = 6;
        $totalRow = 21;

        for ($i = 0; $i < $totalRow; ++$i) {
            $row = $rows[$i] ?? [
                'no' => '',
                'nama_bahan' => '',
                'jenis_wadah' => '',
                'volume' => '',
                'pengawetan' => '',
                'keterangan' => '',
            ];

            $pdf->Cell(10, $rowHeight, $row['no'], 1, 0, 'C');
            $pdf->Cell(30, $rowHeight, $row['nama_bahan'], 1, 0, 'L');
            $pdf->Cell(45, $rowHeight, $row['jenis_wadah'], 1, 0, 'L');
            $pdf->Cell(35, $rowHeight, $row['volume'], 1, 0, 'C');
            $pdf->Cell(30, $rowHeight, $row['pengawetan'], 1, 0, 'L');
            $pdf->Cell(35, $rowHeight, $row['keterangan'], 1, 1, 'L');
        }
        $pdf->Ln(4);
        // ======================
        // STATUS CONTOH UJI
        // ======================
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->Cell(35, 5, 'Status Contoh Uji :', 0, 0);

        // Checkbox 1
        $pdf->Cell(5, 5, $statusContohUji == 'diantar_pelanggan' ? 'V' : '', 1, 0, 'C');
        $pdf->Cell(50, 5, ' Diantar Pelanggan', 0, 1);

        // Checkbox 2
        $pdf->Cell(35, 5, '', 0, 0);
        $pdf->Cell(5, 5, $statusContohUji == 'diambil_oleh_laboratorium' ? 'V' : '', 1, 0, 'C');
        $pdf->Cell(50, 5, ' Diambil Oleh Laboratorium', 0, 1);

        $pdf->Ln(8);

        // ======================
        // TANDA TANGAN
        // ======================
        $pdf->SetFont('DejaVu', '', 9);

        $pdf->Cell(70, 5, 'PPCU / Perwakilan Pelanggan,', 0, 0, 'C');
        $pdf->Cell(0, 5, 'Jambi, '.$tanggalDiterima, 0, 1, 'R');

        $pdf->Ln(15);

        $pdf->Cell(68, 5, '( Ulfi Atha Tifalni )', 0, 0, 'C');
        $pdf->Cell(0, 5, "( {$pic} )", 0, 1, 'R');

        return response(
            $pdf->Output('S', 'Formulir_Permintaan.pdf')
        )->header('Content-Type', 'application/pdf');
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

    public function printInvoice(Request $request)
    {
        $pdf = new JalintTFPDF();

        // Setup Font
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
        $pdf->AddFont('DejaVu', 'BI', 'DejaVuSans-BoldOblique.ttf', true);

        $customerAccount = auth('customer')->user();

        $invoices = Invoice::query()
        ->where('id', $request->id)
        ->when($customerAccount?->customer_id, function ($query) use ($customerAccount) {
            $query->where('customer_id', $customerAccount->customer_id);
        })
        ->with(['customer', 'customer.customerContact', 'offer'])
        ->first();

        // dd($invoices->customer->customerContact->name);
        // ==========================================
        // HALAMAN 1: SURAT (PAKAI HEADER & FOOTER)
        // ==========================================
        $pdf->showHeaderFooter = true; // Set TRUE DULU sebelum AddPage
        $pdf->AddPage('P');
        $this->buatHalamanSurat($pdf, $invoices);

        // ==========================================
        // HALAMAN 2: KWITANSI (MATIKAN SEMUA)
        // ==========================================
        $pdf->showHeaderFooter = true; // Set FALSE DULU sebelum AddPage
        $pdf->AddPage('L', [210, 148]);
        $this->buatHalamanKwitansi($pdf, $invoices);

        // ==========================================
        // HALAMAN 3: INVOICE (PAKAI HEADER & FOOTER)
        // ==========================================
        $pdf->showHeaderFooter = true; // Set TRUE DULU sebelum AddPage
        $pdf->AddPage('P');
        $this->buatHalamanInvoice($pdf, $invoices);

        return response($pdf->Output('S', 'Dokumen_Penagihan.pdf'))
                ->header('Content-Type', 'application/pdf');
    }

    private function buatHalamanSurat($pdf, $invoices)
    {
        // --- Data dasar ---
        $customer = $invoices->customer;
        $customerName = $customer->name;
        $contactName = $customer->customerContact->name;
        $alamat = "{$customer->city} - {$customer->province}";
        $total = 'Rp '.number_format($invoices->total_amount, 0, ',', '.');
        $isPPN = $invoices->vat_percent ? ' dengan PPN' : '';
        $tanggalSurat = Carbon::parse($invoices->issued_at)
                                ->locale('id')
                                ->translatedFormat('d F Y');
        $judulPenawaran = $invoices->offer->title;
        // --- Header tanggal ---
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(0, 5, "Jambi, {$tanggalSurat}", 0, 1, 'R');
        $pdf->Ln(10);

        // --- Nomor / Lampiran / Perihal ---
        $this->barisInfo($pdf, 'Nomor', $invoices->invoice_number);
        $this->barisInfo($pdf, 'Lampiran', '1 (satu) berkas');

        $pdf->Cell(25, 6, 'Perihal', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Permohonan Pembayaran Pengujian Sampel', 0, 1);
        $pdf->SetFont('DejaVu', '', 11);

        $pdf->Ln(10);

        // --- Tujuan surat ---
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Kepada Yth :', 0, 1);
        $pdf->Cell(0, 6, $customerName, 0, 1);

        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(0, 6, "U.p {$contactName}", 0, 1);
        $pdf->Cell(0, 6, 'di', 0, 1);
        $pdf->Cell(0, 6, $alamat, 0, 1);

        $pdf->Ln(12);

        // --- Isi surat ---
        $pdf->Cell(0, 6, 'Dengan hormat,', 0, 1);
        $pdf->Ln(2);

        $isi = "Bersama ini kami mengajukan permohonan pembayaran {$judulPenawaran} {$customerName} "
             ."sebesar {$total}{$isPPN}.";

        $pdf->MultiCell(0, 6, $isi);

        $pdf->Ln(5);
        $pdf->Cell(0, 6, 'Bersama ini dilampirkan dokumen berupa :', 0, 1);

        $lampiran = ['Invoice', 'Kwitansi'];
        if ($invoices->vat_percent) {
            $lampiran[] = 'Faktur Pajak';
        }

        foreach ($lampiran as $i => $item) {
            $pdf->Cell(10, 6, ($i + 1).'.', 0, 0);
            $pdf->Cell(0, 6, $item, 0, 1);
        }

        $pdf->Ln(5);
        $pdf->Cell(
            0,
            6,
            'Demikian surat ini kami sampaikan dan atas kerjasamanya diucapkan terima kasih.',
            0,
            1
        );

        $pdf->Ln(20);

        // --- Tanda tangan ---
        $startX = 120;
        $pdf->SetX($startX);
        $pdf->Cell(0, 6, 'Jalint Lab', 0, 1);
        $pdf->SetX($startX);
        $pdf->Cell(0, 6, 'a.n Direktur', 0, 1);

        $pdf->Ln(15);

        $pdf->SetX($startX);
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Retni Azmalia, S.E', 0, 1);

        $pdf->SetX($startX);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(0, 6, 'Manajer Keuangan', 0, 1);
    }

    /**
     * Helper baris label : value.
     */
    private function barisInfo($pdf, string $label, string $value): void
    {
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(25, 6, $label, 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, $value, 0, 1);
    }

    private function buatHalamanKwitansi($pdf, $invoices)
    {
        // 1. Tetap aktifkan AutoPageBreak tapi dengan margin bawah yang aman bagi Footer
        $pdf->SetAutoPageBreak(true, 25);

        $customer = $invoices->customer;
        $customerName = $customer->name;
        $contactName = $customer->customerContact->name;
        $alamat = "{$customer->city} - {$customer->province}";
        $total = 'Rp '.number_format($invoices->total_amount, 0, ',', '.');
        $isPPN = $invoices->vat_percent ? ' dengan PPN' : '';
        $tanggalSurat = Carbon::parse($invoices->issued_at)
                                ->locale('id')
                                ->translatedFormat('d F Y');
        $judulPenawaran = $invoices->offer->title;

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
        $pdf->Cell(0, 10, $customerName, 0, 1);

        // --- Baris: Uang sejumlah ---
        $pdf->SetX(15);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(40, 10, 'Uang sejumlah', 0, 0);
        $pdf->Cell(5, 10, ':', 0, 0);
        $pdf->SetFont('DejaVu', 'BI', 11); // Ukuran font diperkecil sedikit
        $pdf->Cell(0, 10, AmountToWordsUtil::toWords($invoices->total_amount), 0, 1);

        // --- Baris: Untuk Pembayaran ---
        $pdf->SetX(15);
        $pdf->SetFont('DejaVu', '', 11);
        $pdf->Cell(40, 8, 'Untuk Pembayaran', 0, 0);
        $pdf->Cell(5, 8, ':', 0, 0);
        // MultiCell dipersempit lebarnya (90mm) agar tidak menabrak tanda tangan
        $pdf->MultiCell(90, 7, "{$judulPenawaran} {$customerName}.", 0, 'L');

        // --- Baris: Nominal (Box Abu-abu) ---
        // Diposisikan relatif terhadap startY
        $pdf->SetXY(15, $startY + 65);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('DejaVu', 'BI', 13);
        $pdf->Cell(55, 10, $total, 0, 0, 'C', true);

        // --- Bagian Tanda Tangan ---
        $startX = 145;
        $currentY = $startY + 45; // Mulai tanda tangan di tengah kanan bingkai

        $pdf->SetXY($startX, $currentY);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(0, 5, "Jambi, {$tanggalSurat}.", 0, 1, 'L');
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

    private function buatHalamanInvoice($pdf, $invoices)
    {
        // --- Data dasar ---
        $customer = $invoices->customer;
        $customerName = $customer->name;
        $contactName = $customer->customerContact->name;
        $alamat = "{$customer->city} - {$customer->province}";
        $judul = $invoices->offer->title;

        $tanggal = Carbon::parse($invoices->issued_at)
            ->locale('id')
            ->translatedFormat('d F Y');

        // --- Angka ---
        $subtotal = number_format($invoices->subtotal_amount, 0, ',', '.');

        $vatPercent = (float) ($invoices->vat_percent ?? 0);   // contoh: 11
        $pphPercent = (float) ($invoices->pph_percent ?? 0);   // contoh: 2.00

        $ppnAmount = number_format($invoices->ppn_amount, 0, ',', '.');

        $pphAmount = number_format($invoices->pph_amount, 0, ',', '.');

        $total = number_format($invoices->total_amount, 0, ',', '.');

        $fmt = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');

        // =====================================================
        // HEADER
        // =====================================================
        $pdf->SetFont('DejaVu', '', 10);
        $this->barisInfo($pdf, 'Invoice No.', $invoices->invoice_number);
        $this->barisInfo($pdf, 'Tanggal', $tanggal);
        $this->barisInfo($pdf, 'N.P.W.P', '31.770.541.6-331.000');

        $pdf->Ln(10);

        // --- Tujuan ---
        $pdf->SetFont('DejaVu', 'B', 10);
        $this->barisInfo($pdf, 'Kepada Yth', $customerName);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(30, 5, '', 0, 0);
        $pdf->Cell(0, 5, "U.p {$contactName}", 0, 1);
        $pdf->Cell(30, 5, '', 0, 0);
        $pdf->Cell(0, 5, 'di', 0, 1);
        $pdf->Cell(30, 5, '', 0, 0);
        $pdf->Cell(0, 5, $alamat, 0, 1);

        $pdf->Ln(10);
        $pdf->Cell(0, 5, 'Bersama ini kami mengajukan penagihan sebagai berikut:', 0, 1);
        $pdf->Ln(3);

        // =====================================================
        // TABEL
        // =====================================================
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(10, 7, 'No', 1, 0, 'C');
        $pdf->Cell(130, 7, 'URAIAN', 1, 0, 'C');
        $pdf->Cell(50, 7, 'JUMLAH (Rp)', 1, 1, 'C');

        $pdf->SetFont('DejaVu', '', 9);

        $no = 1;

        // --- Subtotal ---
        $pdf->Cell(10, 7, $no++, 1, 0, 'C');
        $pdf->Cell(130, 7, "{$judul} {$customerName}", 1, 0);
        $pdf->Cell(50, 7, $subtotal, 1, 1, 'R');

        // --- PPN ---
        if ($vatPercent > 0) {
            $pdf->Cell(10, 7, $no++, 1, 0, 'C');
            $pdf->Cell(130, 7, "PPN ({$vatPercent}%)", 1, 0);
            $pdf->Cell(50, 7, $ppnAmount, 1, 1, 'R');
        }

        // --- PPh 23 ---
        if ($pphPercent > 0) {
            $pdf->Cell(10, 7, $no++, 1, 0, 'C');
            $pdf->Cell(130, 7, "PPh 23 ({$pphPercent}%)", 1, 0);
            $pdf->Cell(50, 7, $pphAmount, 1, 1, 'R');
        }

        // --- Total ---
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(140, 7, 'Terbilang :', 1, 0);
        $pdf->Cell(50, 14, $total, 1, 0, 'R');
        $pdf->Ln(7);

        $pdf->SetFont('DejaVu', 'BI', 10);
        $pdf->Cell(140, 7, AmountToWordsUtil::toWords($total), 1, 1);

        // =====================================================
        // FOOTER INFO + TTD (tidak diubah logika)
        // =====================================================
        $pdf->Ln(5);

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
