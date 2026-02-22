<?php

namespace App\Http\Controllers;

use App\Library\Tfpdf\JalintTFPDF;
use App\Models\Invoice;
use App\Models\LhpDocument;
use App\Models\Offer;
use App\Utils\AmountToWordsUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JalintPdfController extends Controller
{
    public function suratTugasTFPDF(Request $request, $id)
    {
        $data = $this->getDataOffer($id);
        $parameters = $this->buildDataUjiForPdf($data);

        $pdf = new JalintTFPDF();
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('DejaVu', '', 10);

        // Data Mapping
        $taskLetterNumber = $data->taskLetter->task_letter_number ?? '-';
        $namaKegiatan = $data->title ?? '-';
        $customerName = $data->customer->name ?? '-';
        $location = $data->location ?? '-';
        $dataPersonel = $data->taskLetter->officers ?? [];
        $tanggalKegiatan = $this->formatTanggalRange($data->taskLetter->start_date, $data->taskLetter->end_date);

        // --- JUDUL ---
        $pdf->SetFont('PlusJakartaSans', 'B', 14);
        $pdf->Cell(0, 8, 'SURAT TUGAS', 0, 1, 'C');
        $pdf->SetFont('PlusJakartaSans', '', 10);
        $pdf->Cell(0, 2, "No: $taskLetterNumber", 0, 1, 'C');
        $pdf->Ln(8);

        // --- KALIMAT PEMBUKA ---
        $pdf->SetFont('PlusJakartaSans', '', 11);
        $isiSurat = "Sehubungan dengan $namaKegiatan $customerName di $location, maka dengan ini kami tugaskan:";
        $pdf->MultiCell(0, 6, $isiSurat, 0, 'J');
        $pdf->Ln(5);

        // --- TABEL PERSONEL (DINAMIS) ---
        $pdf->SetDrawColor(255, 128, 0);
        $pdf->SetWidths([10, 60, 55, 65]);
        $pdf->SetAligns(['C', 'L', 'L', 'L']);
        $pdf->SetFont('PlusJakartaSans', 'B', 10);

        // Header Tabel
        $pdf->Row(['No', 'Nama', 'Jabatan', 'Keterangan']);

        $pdf->SetFont('PlusJakartaSans', '', 10);
        $no = 1;
        foreach ($dataPersonel as $row) {
            $pdf->Row([
                $no++,
                $row['employee']['name'] ?? '-',
                $row['position'] ?? '-',
                $row['description'] ?? '-',
            ]);
        }
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Ln(8);

        // --- INFORMASI PARAMETER ---
        $pdf->SetFont('PlusJakartaSans', '', 11);
        $pdf->MultiCell(0, 6, "Untuk melakukan pekerjaan tersebut diatas pada hari $tanggalKegiatan dengan rincian sebagai berikut:", 0, 'J');
        $pdf->Ln(5);

        // --- TABEL PARAMETER (DINAMIS) ---
        $pdf->SetWidths([10, 40, 50, 55, 15, 20]);
        $pdf->SetAligns(['C', 'L', 'L', 'L', 'C', 'C']);
        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->Row(['No', 'Bahan/Produk', 'Regulasi', 'Jenis Parameter', 'Satuan', 'Jumlah']);

        $pdf->SetFont('DejaVu', '', 8);
        $noPara = 1;
        $totalSample = 0;
        foreach ($parameters as $p) {
            $totalSample += ($p['jumlah_parameter'] ?? 0);
            $pdf->Row([
                $noPara++,
                $p['produk_uji'] ?? '-',
                $p['regulasi'] ?? '-',
                $p['parameter'] ?? '-',
                'Titik',
                $p['jumlah_parameter'] ?? 0,
            ]);
        }
        $pdf->Ln(10);

        // --- TABEL KOORDINAT (HEADER KHUSUS) ---
        $pdf->SetFont('DejaVu', 'BI', 9);
        $xPos = $pdf->GetX();
        $pdf->Cell(50, 14, 'Identifikasi Uji / Sample ID', 1, 0, 'C');
        $pdf->Cell(40, 14, 'Matriks / Matrix', 1, 0, 'C');
        $pdf->Cell(100, 7, 'Koordinat / Coordinate', 1, 1, 'C');
        $pdf->SetX($xPos + 90);
        $pdf->Cell(50, 7, 'Lintang / Latitude', 1, 0, 'C');
        $pdf->Cell(50, 7, 'Bujur / Longitude', 1, 1, 'C');

        $pdf->SetFont('DejaVu', '', 9);
        $pdf->SetWidths([50, 40, 50, 50]);
        $pdf->SetAligns(['L', 'C', 'C', 'C']);

        // Contoh data koordinat dinamis
        if (isset($data->samples) && count($data->samples) > 0) {
            foreach ($data->samples as $sample) {
                $pdf->Row([$sample->sample_id, $sample->matrix, $sample->lat, $sample->long]);
            }
        } else {
            // Jika data kosong, buat baris kosong sesuai total sample
            for ($i = 0; $i < $totalSample; ++$i) {
                $pdf->Row(['', '', '', '']);
            }
        }

        // --- PENUTUP ---
        $pdf->Ln(10);
        $pdf->SetFont('PlusJakartaSans', '', 11);
        $pdf->MultiCell(0, 6, 'Demikian surat tugas ini diberikan agar dapat dilaksanakan dengan penuh tanggung jawab.', 0, 'J');

        // --- TANDA TANGAN ---
        $pdf->Ln(10);
        $pdf->SetX(130);
        $pdf->Cell(60, 6, 'Jambi, '.date('d F Y'), 0, 1, 'L');
        $pdf->SetX(130);
        $pdf->SetFont('PlusJakartaSans', 'B', 11);
        $pdf->Cell(60, 6, 'Jalint Lab', 0, 1, 'L');
        $pdf->Ln(20);
        $pdf->SetX(130);
        $pdf->SetFont('PlusJakartaSans', 'BU', 11);
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

        // Data Mapping
        $customerName = $lhpDocument->offer->customer->name ?? '-';
        $alamat = $lhpDocument->offer->customer->address ?? '-';
        $judulKegiatan = $lhpDocument->offer->title ?? '-';
        $noTelp = $lhpDocument->offer->customer->customerContact->phone ?? '-';
        $pic = $lhpDocument->offer->customer->customerContact->name ?? '-';
        $picEmail = $lhpDocument->offer->customer->customerContact->email ?? '-';
        $statusContohUji = $lhpDocument->status_contoh_uji;
        $tanggalDiterima = Carbon::parse($lhpDocument->tanggal_diterima)->format('d/m/Y');

        // Margin atas
        $pdf->SetTopMargin(10);

        // ======================
        // JUDUL
        // ======================
        $pdf->SetFont('PlusJakartaSans', 'B', 11);
        $pdf->Cell(0, 5, 'FORMULIR PERMINTAAN PENGUJIAN CONTOH UJI', 0, 1, 'C');
        $pdf->SetFont('PlusJakartaSans', 'B', 9);
        $pdf->Cell(0, 5, 'JOB NUMBER: LAB-JLI-.......................', 0, 1, 'C');
        $pdf->Ln(3);

        // ======================
        // INFORMASI PELANGGAN (Dinamis dengan rowWithDots)
        // ======================
        $pdf->SetFont('PlusJakartaSans', '', 9);
        $lineHeight = 6;

        // Gunakan MultiCell di dalam rowWithDots agar Alamat panjang tidak terpotong
        $this->rowWithDots($pdf, 'Nama Pelanggan', $customerName, $lineHeight);
        $this->rowWithDots($pdf, 'Alamat', $alamat, $lineHeight);
        $this->rowWithDots($pdf, 'Personil Penghubung', $pic, $lineHeight);
        $this->rowWithDots($pdf, 'No. Telp/HP', $noTelp, $lineHeight);
        $this->rowWithDots($pdf, 'Email Penerima Laporan', $picEmail, $lineHeight);
        $this->rowWithDots($pdf, 'Nama Kegiatan', $judulKegiatan, $lineHeight);
        $this->rowWithDots($pdf, 'Tanggal Diterima', $tanggalDiterima, $lineHeight);

        $pdf->Ln(4);

        // ======================
        // HEADER TABEL
        // ======================
        $pdf->SetFont('DejaVu', 'B', 8);
        // Atur Lebar Kolom (Total 185 untuk A4 dengan margin standar)
        $w = [10, 30, 45, 35, 30, 35];
        $pdf->SetWidths($w);
        $pdf->SetAligns(['C', 'C', 'C', 'C', 'C', 'C']);

        $pdf->Row(['No', 'Bahan Produk', 'Jenis Wadah Contoh Uji', 'Volume Contoh Uji', 'Pengawetan', 'Keterangan']);

        // ======================
        // ISI TABEL (DINAMIS)
        // ======================
        $pdf->SetFont('DejaVu', '', 8);
        $pdf->SetAligns(['C', 'L', 'L', 'C', 'L', 'L']);

        $rows = [];
        $noPara = 1;
        $dataFppcu = $lhpDocument->fppcu;

        foreach ($dataFppcu as $row) {
            $namaBahan = $row->nama_bahan_produk ?? '';
            $params = $row->fppcuParameters ?? [];

            foreach ($params as $fp) {
                $rows[] = [
                    $noPara++,
                    $namaBahan,
                    $fp->jenis_wadah ?? '',
                    $fp->volume_contoh_uji ?? '',
                    $fp->pengawetan ?? '',
                    $fp->keterangan ?? '',
                ];
            }
        }

        // Tampilkan data yang ada
        foreach ($rows as $rowData) {
            $pdf->Row($rowData);
        }

        // Tambahkan baris kosong jika data kurang dari 15 agar formulir tidak terlihat buntung
        // (Opsional, sesuaikan kebutuhan minimal baris)
        $minRows = 15;
        if (count($rows) < $minRows) {
            for ($i = count($rows); $i < $minRows; ++$i) {
                $pdf->Row(['', '', '', '', '', '']);
            }
        }

        $pdf->Ln(4);

        // ======================
        // STATUS CONTOH UJI
        // ======================
        $pdf->SetFont('PlusJakartaSans', '', 9);
        $pdf->Cell(35, 5, 'Status Contoh Uji :', 0, 0);

        // Checkbox Dinamis
        $pdf->Cell(5, 5, $statusContohUji == 'diantar_pelanggan' ? 'V' : '', 1, 0, 'C');
        $pdf->Cell(50, 5, ' Diantar Pelanggan', 0, 1);
        $pdf->Cell(35, 5, '', 0, 0);
        $pdf->Cell(5, 5, $statusContohUji == 'diambil_oleh_laboratorium' ? 'V' : '', 1, 0, 'C');
        $pdf->Cell(50, 5, ' Diambil Oleh Laboratorium', 0, 1);

        $pdf->Ln(8);

        // ======================
        // TANDA TANGAN
        // ======================
        $pdf->SetFont('PlusJakartaSans', '', 9);
        $pdf->Cell(70, 5, 'PPCU / Perwakilan Pelanggan,', 0, 0, 'C');
        $pdf->Cell(0, 5, 'Jambi, '.$tanggalDiterima, 0, 1, 'R');

        $pdf->Ln(20);

        $pdf->SetFont('PlusJakartaSans', 'U', 9);
        $pdf->Cell(70, 5, '( Ulfi Atha Tifalni )', 0, 0, 'C');
        $pdf->Cell(0, 5, "( $pic )", 0, 1, 'R');

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

    public function printInvoice(Request $request)
    {
        $pdf = new JalintTFPDF();

        // Setup Font
        // $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        // $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
        // $pdf->AddFont('DejaVu', 'BI', 'DejaVuSans-BoldOblique.ttf', true);

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
        $pdf->SetFont('PlusJakartaSans', '', 11);
        $pdf->Cell(0, 5, "Jambi, {$tanggalSurat}", 0, 1, 'R');
        $pdf->Ln(10);

        // --- Nomor / Lampiran / Perihal ---
        $this->barisInfo($pdf, 'Nomor', $invoices->invoice_number);
        $this->barisInfo($pdf, 'Lampiran', '1 (satu) berkas');

        $pdf->Cell(25, 6, 'Perihal', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->SetFont('PlusJakartaSans', 'B', 11);
        $pdf->Cell(0, 6, 'Permohonan Pembayaran Pengujian Sampel', 0, 1);
        $pdf->SetFont('PlusJakartaSans', '', 11);

        $pdf->Ln(10);

        // --- Tujuan surat ---
        $pdf->SetFont('PlusJakartaSans', 'B', 11);
        $pdf->Cell(0, 6, 'Kepada Yth :', 0, 1);
        $pdf->Cell(0, 6, $customerName, 0, 1);

        $pdf->SetFont('PlusJakartaSans', '', 11);
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
        $pdf->SetFont('PlusJakartaSans', 'B', 11);
        $pdf->Cell(0, 6, 'Retni Azmalia, S.E', 0, 1);

        $pdf->SetX($startX);
        $pdf->SetFont('PlusJakartaSans', '', 11);
        $pdf->Cell(0, 6, 'Manajer Keuangan', 0, 1);
    }

    /**
     * Helper baris label : value.
     */
    private function barisInfo($pdf, string $label, string $value): void
    {
        $pdf->SetFont('PlusJakartaSans', '', 11);
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
        $pdf->SetFont('PlusJakartaSans', '', 11);
        $pdf->Cell(40, 10, 'Telah terima dari', 0, 0);
        $pdf->Cell(5, 10, ':', 0, 0);
        $pdf->SetFont('PlusJakartaSans', 'B', 12);
        $pdf->Cell(0, 10, $customerName, 0, 1);

        // --- Baris: Uang sejumlah ---
        $pdf->SetX(15);
        $pdf->SetFont('PlusJakartaSans', '', 11);
        $pdf->Cell(40, 10, 'Uang sejumlah', 0, 0);
        $pdf->Cell(5, 10, ':', 0, 0);
        $pdf->SetFont('PlusJakartaSans', 'BI', 11); // Ukuran font diperkecil sedikit
        $pdf->MultiCell(0, 10, AmountToWordsUtil::toWords($invoices->total_amount), 0, 1);

        // --- Baris: Untuk Pembayaran ---
        $pdf->SetX(15);
        $pdf->SetFont('PlusJakartaSans', '', 11);
        $pdf->Cell(40, 8, 'Untuk Pembayaran', 0, 0);
        $pdf->Cell(5, 8, ':', 0, 0);
        // MultiCell dipersempit lebarnya (90mm) agar tidak menabrak tanda tangan
        $pdf->MultiCell(90, 7, "{$judulPenawaran} {$customerName}.", 0, 'L');

        // --- Baris: Nominal (Box Abu-abu) ---
        // Diposisikan relatif terhadap startY
        $pdf->SetXY(15, $startY + 65);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('PlusJakartaSans', 'BI', 13);
        $pdf->Cell(55, 10, $total, 0, 0, 'C', true);

        // --- Bagian Tanda Tangan ---
        $startX = 145;
        $currentY = $startY + 45; // Mulai tanda tangan di tengah kanan bingkai

        $pdf->SetXY($startX, $currentY);
        $pdf->SetFont('PlusJakartaSans', '', 10);
        $pdf->Cell(0, 5, "Jambi, {$tanggalSurat}.", 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->Cell(0, 5, 'Yang menerima :', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->SetFont('PlusJakartaSans', 'B', 10);
        $pdf->Cell(0, 5, 'Jalint Lab', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->Cell(0, 5, 'a.n Direktur', 0, 1, 'L');

        // Jarak tanda tangan ke Nama Manajer (diperpendek jaraknya agar tidak menabrak footer)
        $pdf->SetXY($startX, $currentY + 26);
        $pdf->SetFont('PlusJakartaSans', 'B', 10);
        $pdf->Cell(0, 5, 'Refni Azmalia, S.E', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->SetFont('PlusJakartaSans', '', 10);
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
        $discountAmount = number_format($invoices->discount_amount, 0, ',', '.');

        $total = number_format($invoices->total_amount, 0, ',', '.');

        // =====================================================
        // HEADER
        // =====================================================
        $pdf->SetFont('PlusJakartaSans', '', 10);
        $this->barisInfo($pdf, 'Invoice No.', $invoices->invoice_number);
        $this->barisInfo($pdf, 'Tanggal', $tanggal);
        $this->barisInfo($pdf, 'N.P.W.P', '31.770.541.6-331.000');

        $pdf->Ln(10);

        // --- Tujuan ---
        $pdf->SetFont('PlusJakartaSans', 'B', 10);
        $this->barisInfo($pdf, 'Kepada Yth', $customerName);
        $pdf->SetFont('PlusJakartaSans', '', 10);
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
        // $pdf->SetFont('DejaVu', 'B', 10);
        // $pdf->Cell(10, 7, 'No', 1, 0, 'C');
        // $pdf->Cell(130, 7, 'URAIAN', 1, 0, 'C');
        // $pdf->Cell(50, 7, 'JUMLAH (Rp)', 1, 1, 'C');

        // $pdf->SetFont('DejaVu', '', 9);

        // $no = 1;

        // // --- Subtotal ---
        // $pdf->Cell(10, 7, $no++, 1, 0, 'C');
        // $pdf->Cell(130, 7, "{$judul} {$customerName}", 1, 0);
        // $pdf->Cell(50, 7, $subtotal, 1, 1, 'R');

        // // --- PPN ---
        // if ($vatPercent > 0) {
        //     $pdf->Cell(10, 7, $no++, 1, 0, 'C');
        //     $pdf->Cell(130, 7, "PPN ({$vatPercent}%)", 1, 0);
        //     $pdf->Cell(50, 7, $ppnAmount, 1, 1, 'R');
        // }

        // // --- PPh 23 ---
        // if ($pphPercent > 0) {
        //     $pdf->Cell(10, 7, $no++, 1, 0, 'C');
        //     $pdf->Cell(130, 7, "PPh 23 ({$pphPercent}%)", 1, 0);
        //     $pdf->Cell(50, 7, $pphAmount, 1, 1, 'R');
        // }

        // if ($discountAmount > 0) {
        //     $pdf->Cell(10, 7, $no++, 1, 0, 'C');
        //     $pdf->Cell(130, 7, 'Potongan', 1, 0);
        //     $pdf->Cell(50, 7, $discountAmount, 1, 1, 'R');
        // }

        // // --- Total ---
        // $pdf->SetFont('DejaVu', 'B', 10);
        // $pdf->Cell(140, 7, 'Terbilang :', 1, 0);
        // $pdf->Cell(50, 14, $total, 1, 0, 'R');
        // $pdf->Ln(7);

        // $pdf->SetFont('DejaVu', 'BI', 10);
        // $pdf->MultiCell(140, 7, AmountToWordsUtil::toWords($invoices->total_amount), 1, 1);
        // =====================================================
        // TABEL
        // =====================================================
        $pdf->SetFont('PlusJakartaSans', 'B', 10);
        $pdf->Cell(10, 7, 'No', 1, 0, 'C');
        $pdf->Cell(130, 7, 'URAIAN', 1, 0, 'C');
        $pdf->Cell(50, 7, 'JUMLAH (Rp)', 1, 1, 'C');

        $pdf->SetFont('PlusJakartaSans', '', 9);

        // --- Subtotal ---
        // Gunakan MultiCell untuk uraian agar jika judul sangat panjang, tabel tidak hancur
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Simpan posisi awal untuk baris isi
        $pdf->Cell(10, 7, '1', 1, 0, 'C');
        $pdf->Cell(130, 7, "{$judul} {$customerName}", 1, 0);
        $pdf->Cell(50, 7, $subtotal, 1, 1, 'R');

        $no = 2;
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

        if ($discountAmount > 0) {
            $pdf->Cell(10, 7, $no++, 1, 0, 'C');
            $pdf->Cell(130, 7, 'Potongan', 1, 0);
            $pdf->Cell(50, 7, $discountAmount, 1, 1, 'R');
        }

        // =====================================================
        // BAGIAN TOTAL & TERBILANG (DINAMIS)
        // =====================================================
        $pdf->SetFont('PlusJakartaSans', 'B', 10);

        $currentX = $pdf->GetX();
        $currentY = $pdf->GetY();
        $lebarTerbilang = 140;
        $lebarTotal = 50;

        // 1. Gambar Label "Terbilang :"
        $pdf->Cell($lebarTerbilang, 7, 'Terbilang :', 'LTR', 1, 'L');

        // 2. Gambar Isi Terbilang (MultiCell)
        $pdf->SetFont('PlusJakartaSans', 'BI', 9);
        $terbilangTeks = AmountToWordsUtil::toWords($invoices->total_amount).' Rupiah';

        // Hitung tinggi terbilang untuk menyamakan kotak sebelah kanan
        $ySebelumMulti = $pdf->GetY();
        $pdf->MultiCell($lebarTerbilang, 7, $terbilangTeks, 'LBR', 'L');
        $ySesudahMulti = $pdf->GetY();

        $tinggiTotalBaris = $ySesudahMulti - $currentY;

        // 3. Pindah kembali ke posisi Y awal untuk menggambar sel TOTAL di kanan
        $pdf->SetXY($currentX + $lebarTerbilang, $currentY);
        $pdf->SetFont('PlusJakartaSans', 'B', 11);

        // Gambar sel Total dengan tinggi yang sudah dihitung (tinggi label + tinggi multicell)
        $pdf->Cell($lebarTotal, $tinggiTotalBaris, "Rp $total", 1, 1, 'R');

        // Kembalikan posisi Y ke bawah setelah tabel selesai
        $pdf->SetY($ySesudahMulti);

        // =====================================================
        // FOOTER INFO + TTD (tidak diubah logika)
        // =====================================================
        $pdf->Ln(5);

        $pdf->SetFont('PlusJakartaSans', '', 10);
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
        $pdf->SetFont('PlusJakartaSans', 'B', 10);
        $pdf->Cell(0, 5, 'Jalint Lab', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->Cell(0, 5, 'a.n Direktur', 0, 1, 'L');
        $pdf->Ln(20);
        $pdf->SetX($startX);
        $pdf->Cell(0, 5, 'Refni Azmalia, S.E', 0, 1, 'L');
        $pdf->SetX($startX);
        $pdf->SetFont('PlusJakartaSans', '', 10);
        $pdf->Cell(0, 5, 'Manajer Keuangan', 0, 1, 'L');
    }
}
