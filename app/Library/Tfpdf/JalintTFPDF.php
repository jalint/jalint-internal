<?php

namespace App\Library\Tfpdf;

require_once app_path('Library/Tfpdf/tfpdf.php');

class JalintTFPDF extends \tFPDF
{
    public $showHeaderFooter = true;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $this->SetMargins(13, 10, 13);
        $this->SetAutoPageBreak(true, 25);

        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', app_path('Library/Tfpdf/font/unifont/'));
        }

        // WAJIB: Tambahkan font yang mendukung Unicode
        // Pastikan file font .ttf ada di folder font tFPDF Anda
        $this->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $this->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
        $this->AddFont('DejaVu', 'BI', 'DejaVuSans-BoldOblique.ttf', true);
    }

    public function Header()
    {
        if ($this->showHeaderFooter) {
            $baseline = 25;

            // Image handling tetap sama
            $this->Image(public_path('assets/images/rec_1.png'), 0, $baseline - 20, 14, 18);
            $this->Image(public_path('assets/images/rec_2.png'), 12, $baseline - 20, 4, 18);
            $this->Image(public_path('assets/images/jalint_icon.png'), 25, $baseline - 20, 18, 18);
            $this->Image(public_path('assets/images/jalint.png'), 50, $baseline - 20, 38, 18);
            $this->Image(public_path('assets/images/vec_1.png'), 96, $baseline - 20, 2, 18);
            $this->Image(public_path('assets/images/vec_2.png'), 100, $baseline - 20, 2, 18);
            $this->Image(public_path('assets/images/jalint_kan.png'), 110, $baseline - 20, 15, 18);
            $this->Image(public_path('assets/images/lkh.png'), 135, $baseline - 20, 18, 18);
            $this->Image(public_path('assets/images/kemen.png'), 165, $baseline - 20, 20, 20);
            $this->Image(public_path('assets/images/rec_2.png'), 190, $baseline - 20, 4, 18);
            $this->Image(public_path('assets/images/rec_1.png'), 194, $baseline - 20, 19, 18);

            $this->SetDrawColor(0, 150, 75);
            $this->SetLineWidth(0.8);
            $this->Line(10, $baseline + 2, 200, $baseline + 2);

            $this->Ln(20);
        }
    }

    // Fungsi Watermark (Tetap sama, tapi ganti font ke DejaVu jika perlu Unicode)
    public function Watermark($text)
    {
        $this->SetFont('DejaVu', 'B', 50);
        $this->SetTextColor(230, 230, 230);
        $this->StartTransform();
        $this->Rotate(45, 50, 150);
        $this->Text(50, 150, $text);
        $this->StopTransform();
        $this->SetTextColor(0);
    }

    // Rotasi & Transformasi (Tetap sama)
    public $angle = 0;

    public function Rotate($angle, $x = -1, $y = -1)
    { /* Kode Anda sebelumnya */
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
        if ($this->showHeaderFooter) {
            // Naikkan footer, jangan mepet
            $this->SetY(-20);

            $this->SetFillColor(0, 150, 75);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('DejaVu', '', 8);

            $alamat = 'Jl. Nusa Indah I No. 59 E-F Kelurahan Rawasari Kecamatan Alam Barajo Kota Jambi, Kode Pos 36125 | +6282-3123-4995';
            $hal = ' | Halaman '.$this->PageNo().' dari {nb}';

            // Background full width
            $this->Cell(0, 12, '', 0, 1, 'C', true);

            // Tulis teks di atas background
            $this->SetY(-20);
            $this->MultiCell(
                0,
                4,
                $alamat.$hal,
                0,
                'C'
            );
        }
    }
}
