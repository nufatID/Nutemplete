<?php

declare(strict_types=1);

namespace Nufat\Nutemplete;

class NuQrcode
{
    public function qrcode(string $text = "https://nufat.id"): void
    {
        if (!class_exists('QRcode')) {
            $qrLib = __DIR__ . '/phpqrcode/qrlib.php';
            if (file_exists($qrLib)) {
                require_once $qrLib;
            }
        }

        $size = 12;
        $errorCorrectionLevel = "L";
        $margin = 1;
        $moduleSize = 1;

        $text = empty($text) ? "https://nufat.id" : $text;

        if (class_exists('QRcode')) {
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
}
