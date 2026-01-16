<?php

namespace App\Library\Fpdf;

// Pastikan path ini sesuai dengan tempat Anda menaruh fpdf.php manual
require_once app_path('Library/Fpdf/fpdf.php');

class JalintPDF extends \FPDF
{
    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        // Margin: Kiri 30mm, Atas 25mm, Kanan 30mm
        $this->SetMargins(13, 10, 13);
        $this->SetAutoPageBreak(true, 25);
    }

    // Fungsi untuk Header (Garis Hijau & Logo)
    public function Header()
    {
        $baseline = 25; // Garis dasar (bottom) untuk semua elemen

        // 1. Gambar 1 (Nempel Kiri) - Tinggi misal 20mm
        $this->Image(public_path('assets/images/rec_1.png'), 0, $baseline - 20, 14, 18);

        // 2. Logo 1 - Tinggi 15mm
        $this->Image(public_path('assets/images/rec_2.png'), 12, $baseline - 20, 4, 18);

        $this->Image(public_path('assets/images/jalint_icon.png'), 25, $baseline - 20, 18, 18);

        // 3. Multi Cell Tengah (3 Baris) - Tinggi teks total misal 12mm

        // 5, 6, 7. Tiga Logo Berjejer - Tinggi rata-rata 10mm
        $this->Image(public_path('assets/images/vec_1.png'), 96, $baseline - 20, 2, 18);
        $this->Image(public_path('assets/images/vec_2.png'), 100, $baseline - 20, 2, 18);

        $this->Image(public_path('assets/images/jalint_kan.png'), 110, $baseline - 20, 15, 18);
        $this->Image(public_path('assets/images/lkh.png'), 135, $baseline - 20, 18, 18);
        $this->Image(public_path('assets/images/kemen.png'), 165, $baseline - 20, 20, 20);

        // // 8. Logo Terakhir - Tinggi 8mm
        $this->Image(public_path('assets/images/rec_2.png'), 190, $baseline - 20, 4, 18);

        // // 9. Gambar 3 (Nempel Kanan) - Tinggi 20mm
        $this->Image(public_path('assets/images/rec_1.png'), 194, $baseline - 20, 19, 18);

        // Garis Hijau JALINT (Tepat di bawah baseline)
        $this->SetDrawColor(0, 150, 75);
        $this->SetLineWidth(0.8);
        $this->Line(10, $baseline + 2, 200, $baseline + 2);

        $this->Ln(20);
    }

    // Fungsi Watermark Diagonal
    public function Watermark($text)
    {
        $this->SetFont('Arial', 'B', 50);
        $this->SetTextColor(230, 230, 230); // Abu-abu sangat muda
        $this->StartTransform();
        $this->Rotate(45, 50, 150);
        $this->Text(50, 150, $text);
        $this->StopTransform();
        $this->SetTextColor(0); // Reset warna teks ke hitam
    }

    // FUNGSI KUNCI: Menulis Rumus Kimia Otomatis (Subscript)
    // Contoh penggunaan: $pdf->WriteChemistry('Kadar SO2 dan NO2')
    public function WriteChemistry($text, $h = 10)
    {
        $words = explode(' ', $text);
        foreach ($words as $word) {
            // Cari angka di dalam kata (misal SO2)
            if (preg_match('/([a-zA-Z]+)(\d+)/', $word, $matches)) {
                $this->Write($h, $matches[1]); // Tulis Huruf (SO)

                $currY = $this->GetY();
                $this->SetFontSize($this->FontSizePt * 0.7); // Perkecil font
                $this->SetY($currY + 1.5); // Turunkan posisi
                $this->Write($h, $matches[2]); // Tulis Angka (2)

                $this->SetFontSize($this->FontSizePt / 0.7); // Kembalikan font
                $this->SetY($currY); // Kembalikan posisi Y
            } else {
                $this->Write($h, $word);
            }
            $this->Write($h, ' '); // Spasi antar kata
        }
    }

    // Tambahan fungsi rotasi untuk watermark (standar FPDF script)
    public $angle = 0;

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function StartTransform()
    {
        $this->_out('q');
    }

    public function StopTransform()
    {
        $this->_out('Q');
    }

    public function Footer()
    {
        // Posisi 15 mm dari bawah
        $this->SetY(-15);

        // 1. SET WARNA BACKGROUND (HIJAU 85%)
        // RGB untuk Hijau JALINT
        $this->SetFillColor(0, 150, 75);

        // 2. SET WARNA TEKS (PUTIH)
        $this->SetTextColor(255, 255, 255);

        // 3. SET FONT
        $this->SetFont('Arial', '', 8);

        // Ambil alamat dan encoding agar simbol seperti | aman
        $alamat = 'Jl. Nusa Indah I No. 59 E-F Kelurahan Rawasari Kecamatan Alam Barajo Kota Jambi, Kode Pos 36125 +6282-3123-4995';
        $fullText = mb_convert_encoding($alamat, 'ISO-8859-1', 'UTF-8');

        // Menambahkan nomor halaman dinamis
        // {nb} adalah placeholder untuk total halaman
        $hal = ' | Halaman '.$this->PageNo().' dari {nb}';

        // Gambar kotak background (Fill = true)
        // Cell(lebar, tinggi, teks, border, ln, align, fill)
        // Lebar 0 berarti sampai margin kanan
        $this->Cell(0, 10, $fullText.$hal, 0, 0, 'C', true);
    }
}
