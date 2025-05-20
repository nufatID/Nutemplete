<?php

namespace Nufat\Nutemplete;

class NuQrcode
{
    public function qrcode($text = "https://bungtemin.net")
    {
        require_once __DIR__ . '/phpqrcode/qrlib.php';

        $size = 12; // Ukuran gambar QR code
        $errorCorrectionLevel = "L"; // Level koreksi kesalahan (L, M, Q, H)
        $margin = 1; // Margin di sekeliling QR code
        $moduleSize = 1; // Ukuran modul QR code

        // Menggunakan nilai default jika $text kosong
        $text = empty($text) ? "https://bungtemin.net" : $text;

        \QRcode::png(
            $text,
            false,
            $errorCorrectionLevel,
            $size,
            $margin,
            false,
            0xffffff,
            0x000000,
            $moduleSize
        );
    }
}
