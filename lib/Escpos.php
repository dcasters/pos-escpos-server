<?php

namespace Twf\Pps;

use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class Escpos
{
    public $printer;
    public $char_per_line = 42;

    public function load($printer)
    {
        if ($printer->connection_type == 'network') {
            set_time_limit(30);
            $connector = new NetworkPrintConnector($printer->ip_address, $printer->port);
        } elseif ($printer->connection_type == 'linux') {
            $connector = new FilePrintConnector($printer->path);
        } else {
            $connector = new WindowsPrintConnector($printer->path);
        }

        $this->char_per_line = $printer->char_per_line;
        $profile = CapabilityProfile::load($printer->capability_profile);
        $this->printer = new Printer($connector, $profile);
    }

    public function print_invoice($data)
    {
        /* ---------- LOGO (58mm scaled to ~125px) ---------- */
        if (!empty($data->logo)) {
            echo '> Processing logo: ' . $data->logo . "\n";
            try {
                $logo = $this->download_image($data->logo, 125); // 320 px, tetap dithering
                if ($logo && file_exists($logo)) {
                    $this->printer->setJustification(Printer::JUSTIFY_CENTER);
                    $this->print_bitmap_raw($logo);
                    $this->printer->feed(1);
                    echo "> Logo printed\n";
                }
            } catch (\Exception $e) {
                echo '> Logo error: ' . $e->getMessage() . "\n";
            }
        }

        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->setEmphasis(true);
        $this->printer->setTextSize(1, 1);
        if (!empty($data->header_text)) {
            $this->printer->text(strip_tags($data->header_text) . "\n");
        }
        $this->printer->setEmphasis(false);

        if (!empty($data->display_name)) {
            $this->printer->text($data->display_name . "\n");
        }
        if (!empty($data->address)) {
            $this->printer->text($data->address . "\n");
        }

        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        $this->printer->text($this->drawLine());

        $this->printer->text(rtrim($this->columnify($data->invoice_no_prefix ?? 'Invoice No.', $data->invoice_no ?? '', 55, 45, 0, 0)));
        $this->printer->feed();

        $this->printer->text(rtrim($this->columnify($data->date_label ?? 'Tgl', $data->invoice_date ?? '', 55, 45, 0, 0)));
        $this->printer->feed();

        $cashier = $data->cashier_name
            ?? $data->cashier
            ?? $data->sales_person
            ?? $data->created_by
            ?? $data->user
            ?? '';
        if (!empty($cashier)) {
            $this->printer->text(rtrim($this->columnify($data->sales_person_label ?? 'Kasir', trim($cashier), 55, 45, 0, 0)));
            $this->printer->feed();
        }

        $this->printer->setEmphasis(true);
        $this->printer->text(($data->customer_label ?? 'Anggota') . "\n");
        $this->printer->setEmphasis(false);

        if (!empty($data->customer_name)) { $this->printer->text(trim($data->customer_name) . "\n"); }



        if (!empty($data->customer_mobile)) { $this->printer->text("Mobile: " . $data->customer_mobile . "\n"); }

        $this->printer->text($this->drawLine());

        if (!empty($data->lines)) {
            $i = 1;
            foreach ($data->lines as $line) {
                $line = (array) $line;
                $title = "#{$i}. " . trim(($line['name'] ?? '') . ' ' . ($line['variation'] ?? ''));
                $qty   = (string)($line['quantity'] ?? '1');
                $price = (string)($line['unit_price_exc_tax'] ?? ($line['unit_price'] ?? '0'));
                $total = (string)($line['line_total'] ?? '0');

                // Judul + total kanan
                $this->printer->text($this->columnify($title, $total, 70, 30, 0, 0));
                // Baris qty x price
                $this->printer->text("  " . $qty . " x " . $price . "\n");
                $this->printer->feed(1);
                $i++;
            }
            $this->printer->text($this->drawLine());
        }

        if (!empty($data->subtotal)) {
            $this->printer->text($this->columnify($data->subtotal_label ?? 'Subtotal:', $data->subtotal, 60, 40, 0, 0));
        }
        if (!empty($data->total)) {
            $this->printer->setEmphasis(true);
            $this->printer->text($this->columnify($data->total_label ?? 'Total:', $data->total, 60, 40, 0, 0));
            $this->printer->setEmphasis(false);
        }

        // Payment methods
        if (!empty($data->payments) && is_array($data->payments)) {
            foreach ($data->payments as $p) {
                $method = $p->method ?? '';
                $amount = $p->amount ?? '';
                if ($method && $amount) {
                    $this->printer->text($this->columnify($method . ':', $amount, 60, 40, 0, 0));
                }
            }
        } elseif (!empty($data->total_paid)) {
            $this->printer->text($this->columnify($data->total_paid_label ?? 'Total Paid', $data->total_paid, 60, 40, 0, 0));
        }

        if (isset($data->total_due)) {
            $this->printer->text($this->columnify($data->total_due_label ?? 'Total Due', (string)$data->total_due, 60, 40, 0, 0));
        }

        $this->printer->text($this->drawLine());

        /* ---------- FOOTER ---------- */
        if (!empty($data->footer_text)) {
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text(strip_tags($data->footer_text) . "\n");
        }

        $this->printer->feed();
        $this->printer->cut();

        if (!empty($data->cash_drawer)) {
            $this->printer->pulse();
        }

        $this->printer->close();
    }

    public function open_drawer()
    {
        $this->printer->pulse();
        $this->printer->close();
    }

    public function drawLine()
    {
        return str_repeat('-', $this->char_per_line - 1) . "\n";
    }

    public function columnify($leftCol, $rightCol, $leftWidthPercent, $rightWidthPercent, $space = 2, $remove_for_space = 0)
    {
        $char_per_line = $this->char_per_line - $remove_for_space;
        $leftWidth  = (int) floor($char_per_line * $leftWidthPercent / 100);
        $rightWidth = (int) floor($char_per_line * $rightWidthPercent / 100);

        $leftWrapped  = wordwrap((string)$leftCol,  $leftWidth,  "\n", true);
        $rightWrapped = wordwrap((string)$rightCol, $rightWidth, "\n", true);

        $leftLines  = explode("\n", $leftWrapped);
        $rightLines = explode("\n", $rightWrapped);
        $allLines   = [];

        $max = max(count($leftLines), count($rightLines));
        for ($i = 0; $i < $max; $i++) {
            $leftPart  = str_pad($leftLines[$i]  ?? '', $leftWidth,  ' ');
            $rightRaw  = $rightLines[$i] ?? '';
            $rightPart = str_pad($rightRaw, $rightWidth, ' ', STR_PAD_LEFT); // rata kanan
            $allLines[] = $leftPart . str_repeat(' ', $space) . $rightPart;
        }
        return implode("\n", $allLines) . "\n";
    }

    private function print_bitmap_raw($imagePath)
    {
        $img = @imagecreatefrompng($imagePath);
        if (!$img) { echo '> Cannot load PNG for raw print' . "\n"; return false; }

        $width  = imagesx($img);
        $height = imagesy($img);
        echo '> Printing raw bitmap: ' . $width . 'x' . $height . "\n";

        for ($y = 0; $y < $height; $y += 24) {
            // ESC * 33 nL nH
            $this->printer->getPrintConnector()->write(chr(0x1B) . chr(0x2A) . chr(33));
            $this->printer->getPrintConnector()->write(chr($width % 256));
            $this->printer->getPrintConnector()->write(chr((int)($width / 256)));

            for ($x = 0; $x < $width; $x++) {
                for ($k = 0; $k < 3; $k++) {
                    $byte = 0;
                    for ($b = 0; $b < 8; $b++) {
                        $yy = $y + ($k * 8) + $b;
                        if ($yy < $height) {
                            $rgb  = imagecolorat($img, $x, $yy);
                            $r = ($rgb >> 16) & 0xFF;
                            $g = ($rgb >> 8) & 0xFF;
                            $bb =  $rgb        & 0xFF;
                            $gray = ($r + $g + $bb) / 3;
                            if ($gray < 128) { $byte |= (1 << (7 - $b)); }
                        }
                    }
                    $this->printer->getPrintConnector()->write(chr($byte));
                }
            }
            $this->printer->getPrintConnector()->write("\n");
        }

        imagedestroy($img);
        return true;
    }


    public function download_image($url, $targetWidth = 384)
    {
        $file    = basename($url);
        $logoDir = dirname(__FILE__) . '/../logos/';

        if (!file_exists($logoDir)) { mkdir($logoDir, 0777, true); }

        $stem   = pathinfo($file, PATHINFO_FILENAME);
        $outPng = $logoDir . $stem . ".w{$targetWidth}.png";

        echo '> Target: ' . basename($outPng) . "\n";
        if (file_exists($outPng)) {
            echo '> Cached (' . filesize($outPng) . " bytes)\n";
            return $outPng;
        }

        $tmp = $logoDir . $file;
        $bin = @file_get_contents($url);
        if ($bin === false) { echo "> Download failed\n"; return false; }
        file_put_contents($tmp, $bin);
        echo "> Downloaded (" . strlen($bin) . " bytes)\n";

        if (!extension_loaded('gd')) { @unlink($tmp); echo "> GD not loaded\n"; return false; }

        $src = @imagecreatefrompng($tmp);
        if (!$src) { @unlink($tmp); echo "> Cannot read source PNG\n"; return false; }

        $w = imagesx($src); $h = imagesy($src);

        $newW = $targetWidth;
        $newH = (int) round(($h / $w) * $newW);
        echo "> Resize: {$w}x{$h} -> {$newW}x{$newH}\n";

        $resized = imagecreatetruecolor($newW, $newH);
        $white   = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        if (function_exists('imagegammacorrect')) { imagegammacorrect($resized, 1.0, 0.85); }
        imagefilter($resized, IMG_FILTER_CONTRAST, -40);

        $bw = $this->ditherFloydSteinberg($resized);
        @imagepng($bw, $outPng, 0);

        imagedestroy($src);
        imagedestroy($resized);
        imagedestroy($bw);
        @unlink($tmp);

        if (file_exists($outPng)) {
            echo '> Saved (' . filesize($outPng) . " bytes)\n";
            chmod($outPng, 0644);
            return $outPng;
        }

        echo "> Save failed\n";
        return false;
    }

    /**
     * Dithering Floyd–Steinberg
     */
    private function ditherFloydSteinberg($im)
    {
        $w = imagesx($im); $h = imagesy($im);

        $Y = [];
        for ($y = 0; $y < $h; $y++) {
            $row = [];
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b =  $rgb        & 0xFF;
                $row[] = 0.299*$r + 0.587*$g + 0.114*$b;
            }
            $Y[] = $row;
        }

        $out   = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($out, 255, 255, 255);
        $black = imagecolorallocate($out, 0, 0, 0);
        imagefill($out, 0, 0, $white);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $old = $Y[$y][$x];
                $new = ($old < 128) ? 0 : 255;
                $err = $old - $new;

                imagesetpixel($out, $x, $y, $new === 0 ? $black : $white);

                if ($x + 1 < $w)    $Y[$y][$x + 1]     += $err * 7/16;
                if ($y + 1 < $h) {
                    if ($x > 0)     $Y[$y + 1][$x - 1] += $err * 3/16;
                                     $Y[$y + 1][$x]     += $err * 5/16;
                    if ($x + 1 < $w) $Y[$y + 1][$x + 1] += $err * 1/16;
                }
            }
        }
        return $out;
    }
}
