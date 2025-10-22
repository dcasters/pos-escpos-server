<?php

namespace Twf\Pps;

use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\EscposImage;
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
        // Print logo with DETAILED error logging
        if (isset($data->logo) && ! empty($data->logo)) {
            echo '> Processing logo: '.$data->logo."\n";

            try {
                $logo = $this->download_image($data->logo);

                if ($logo && file_exists($logo)) {
                    echo '> Logo file exists: '.$logo."\n";
                    echo '> File size: '.filesize($logo).' bytes'."\n";

                    // Verify image is readable by GD
                    $imgInfo = @getimagesize($logo);
                    if ($imgInfo === false) {
                        echo '> ERROR: getimagesize() failed - invalid image file!'."\n";
                        throw new \Exception('Invalid image format');
                    }

                    echo '> Image type: '.$imgInfo['mime']."\n";
                    echo '> Dimensions: '.$imgInfo[0].'x'.$imgInfo[1]."\n";
                    echo '> Bits: '.(isset($imgInfo['bits']) ? $imgInfo['bits'] : 'unknown')."\n";
                    echo '> Channels: '.(isset($imgInfo['channels']) ? $imgInfo['channels'] : 'unknown')."\n";

                    // Test if we can load with GD
                    $testImg = @imagecreatefrompng($logo);
                    if ($testImg === false) {
                        echo '> ERROR: imagecreatefrompng() failed!'."\n";
                        throw new \Exception('GD cannot read PNG');
                    }
                    imagedestroy($testImg);
                    echo '> GD can read PNG successfully'."\n";

                    // Now try EscposImage
                    $this->printer->setJustification(Printer::JUSTIFY_CENTER);

                    echo '> Attempting EscposImage::load()...'."\n";

                    try {
                        $logoImage = EscposImage::load($logo, false);
                        echo '> EscposImage loaded successfully!'."\n";

                        echo '> Printing with bitImage()...'."\n";
                        $this->printer->bitImage($logoImage);
                        echo '> Logo printed successfully!'."\n";

                    } catch (\Exception $e) {
                        echo '> EscposImage ERROR!'."\n";
                        echo '> Exception class: '.get_class($e)."\n";
                        echo '> Message: '.$e->getMessage()."\n";
                        echo '> File: '.$e->getFile().':'.$e->getLine()."\n";
                        echo '> Stack trace:'."\n";
                        echo $e->getTraceAsString()."\n";
                        throw $e;
                    }

                    $this->printer->feed(1);
                } else {
                    echo '> Logo file not available'."\n";
                }
            } catch (\Exception $e) {
                echo '> Logo error (continuing): '.$e->getMessage()."\n";
            }
        }

        /* Header */
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->setEmphasis(true);
        $this->printer->setTextSize(2, 2);
        if (isset($data->header_text) && ! empty($data->header_text)) {
            $this->printer->text(strip_tags($data->header_text));
            $this->printer->feed();
        }

        /* Shop & Location Name */
        if (isset($data->display_name) && ! empty($data->display_name)) {
            $this->printer->text($data->display_name);
            $this->printer->feed();
        }

        /* Shop Address */
        $this->printer->setTextSize(1, 1);
        if (isset($data->address) && ! empty($data->address)) {
            $this->printer->text($data->address);
            $this->printer->feed(2);
        }

        /* Custom lines */
        if (! empty($data->sub_heading_line1)) {
            $this->printer->text($data->sub_heading_line1);
            $this->printer->feed(1);
        }
        if (! empty($data->sub_heading_line2)) {
            $this->printer->text($data->sub_heading_line2);
            $this->printer->feed(1);
        }
        if (! empty($data->sub_heading_line3)) {
            $this->printer->text($data->sub_heading_line3);
            $this->printer->feed(1);
        }
        if (! empty($data->sub_heading_line4)) {
            $this->printer->text($data->sub_heading_line4);
            $this->printer->feed(1);
        }
        if (! empty($data->sub_heading_line5)) {
            $this->printer->text($data->sub_heading_line5);
            $this->printer->feed(1);
        }

        /* Tax info */
        if (! empty($data->tax_info1) || ! empty($data->tax_info2)) {
            if (! empty($data->tax_info1)) {
                $this->printer->setEmphasis(true);
                $this->printer->text($data->tax_label1);
                $this->printer->setEmphasis(false);
                $this->printer->text($data->tax_info1);
                $this->printer->feed();
            }
            if (! empty($data->tax_info2)) {
                $this->printer->setEmphasis(true);
                $this->printer->text($data->tax_label2);
                $this->printer->setEmphasis(false);
                $this->printer->text($data->tax_info2);
                $this->printer->feed();
            }
        }

        /* Invoice heading */
        if (isset($data->invoice_heading) && ! empty($data->invoice_heading)) {
            $this->printer->setEmphasis(true);
            $this->printer->text($data->invoice_heading);
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);
        }

        $this->printer->setJustification(Printer::JUSTIFY_LEFT);

        /* Invoice info */
        $this->printer->feed(1);
        $invoice_no = $data->invoice_no_prefix.' '.$data->invoice_no;
        $date = $data->date_label.' '.$data->invoice_date;
        $this->printer->text(rtrim($this->columnify($invoice_no, $date, 50, 50, 0, 0)));
        $this->printer->feed();

        /* Customer info */
        if (! empty($data->customer_info) || ! empty($data->client_id)) {
            $customer_info = ! empty($data->customer_info) ? $data->customer_label.' '.$data->customer_info : '';
            $client_id = ! empty($data->client_id) ? $data->client_id_label.' '.$data->client_id : '';
            $this->printer->text(rtrim($this->columnify($customer_info, $client_id, 50, 50, 0, 0)));
            $this->printer->feed();
        }

        /* Products list */
        if (isset($data->lines) && ! empty($data->lines)) {
            $this->printer->text($this->drawLine());
            $string = $this->columnify(
                $this->columnify(
                    $this->columnify($data->table_qty_label, ' '.$data->table_product_label, 10, 40, 0, 0),
                    $data->table_unit_price_label, 50, 25, 0, 0
                ),
                ' '.$data->table_subtotal_label, 75, 25, 0, 0
            );
            $this->printer->setEmphasis(true);
            $this->printer->text(rtrim($string));
            $this->printer->feed();
            $this->printer->setEmphasis(false);
            $this->printer->text($this->drawLine());

            foreach ($data->lines as $line) {
                $line = (array) $line;
                $product = $line['name'].' '.$line['variation'];

                if (! empty($line['sell_line_note'])) {
                    $product .= '('.$line['sell_line_note'].')';
                }
                if (! empty($line['sub_sku'])) {
                    $product .= ', '.$line['sub_sku'];
                }
                if (! empty($line['brand'])) {
                    $product .= ', '.$line['brand'];
                }
                if (! empty($line['cat_code'])) {
                    $product .= ', '.$line['cat_code'];
                }

                $quantity = $line['quantity'];
                $unit_price = $line['unit_price_exc_tax'];
                $line_total = $line['line_total'];

                $string = rtrim($this->columnify(
                    $this->columnify(
                        $this->columnify($quantity, $product, 10, 40, 0, 0),
                        $unit_price, 50, 25, 0, 0
                    ),
                    $line_total, 75, 25, 0, 0
                ));

                $this->printer->text($string);
                $this->printer->feed(2);
            }

            $this->printer->feed();
            $this->printer->text($this->drawLine());
        }

        /* Totals */
        if (isset($data->subtotal) && ! empty($data->subtotal)) {
            $this->printer->text(rtrim($this->columnify($data->subtotal_label, $data->subtotal, 50, 50, 0, 0)));
            $this->printer->feed();
        }
        if (isset($data->discount) && ! empty($data->discount) && $data->discount != 0) {
            $this->printer->text(rtrim($this->columnify($data->discount_label, $data->discount, 50, 50, 0, 0)));
            $this->printer->feed();
        }
        if (isset($data->tax) && ! empty($data->tax) && $data->tax != 0) {
            $this->printer->text(rtrim($this->columnify($data->tax_label, $data->tax, 50, 50, 0, 0)));
            $this->printer->feed();
        }
        if (isset($data->total) && ! empty($data->total)) {
            $this->printer->setEmphasis(true);
            $this->printer->text(rtrim($this->columnify($data->total_label, $data->total, 50, 50, 0, 0)));
            $this->printer->feed();
            $this->printer->setEmphasis(false);
        }

        /* Payments */
        if (! empty($data->payments)) {
            $this->printer->setEmphasis(true);
            $this->printer->text(rtrim($data->total_paid_label));
            $this->printer->feed();
            $this->printer->setEmphasis(false);
            foreach ($data->payments as $payment) {
                $this->printer->text(rtrim($this->columnify($payment->method, $payment->amount, 50, 50, 0, 0)));
                $this->printer->feed();
            }
        } else {
            if (isset($data->total_paid) && ! empty($data->total_paid)) {
                $this->printer->text(rtrim($this->columnify($data->total_paid_label, $data->total_paid, 50, 50, 0, 0)));
                $this->printer->feed();
            }
        }

        if (isset($data->total_due) && ! empty($data->total_due) && $data->total_due != 0) {
            $this->printer->text(rtrim($this->columnify($data->total_due_label, $data->total_due, 50, 50, 0, 0)));
            $this->printer->feed();
        }

        $this->printer->text($this->drawLine());

        /* Tax breakdown */
        if (! empty($data->taxes)) {
            $this->printer->setEmphasis(true);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text($data->tax_label."\n");
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->setEmphasis(false);
            $this->printer->text($this->drawLine());
            foreach ($data->taxes as $key => $value) {
                $this->printer->text(rtrim($this->columnify($key, $value, 50, 45, 0, 0)));
                $this->printer->feed(1);
            }
        }

        $this->printer->text($this->drawLine());

        /* Footer */
        if (isset($data->footer_text) && ! empty($data->footer_text)) {
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->feed(1);
            $this->printer->text(strip_tags($data->footer_text)."\n");
            $this->printer->feed();
        }

        $this->printer->feed();
        $this->printer->cut();

        if (isset($data->cash_drawer) && ! empty($data->cash_drawer)) {
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
        return str_repeat('-', $this->char_per_line - 1)."\n";
    }

    public function columnify($leftCol, $rightCol, $leftWidthPercent, $rightWidthPercent, $space = 2, $remove_for_space = 0)
    {
        $char_per_line = $this->char_per_line - $remove_for_space;
        $leftWidth = $char_per_line * $leftWidthPercent / 100;
        $rightWidth = $char_per_line * $rightWidthPercent / 100;

        $leftWrapped = wordwrap($leftCol, $leftWidth, "\n", true);
        $rightWrapped = wordwrap($rightCol, $rightWidth, "\n", true);

        $leftLines = explode("\n", $leftWrapped);
        $rightLines = explode("\n", $rightWrapped);
        $allLines = [];

        for ($i = 0; $i < max(count($leftLines), count($rightLines)); $i++) {
            $leftPart = str_pad(isset($leftLines[$i]) ? $leftLines[$i] : '', $leftWidth, ' ');
            $rightPart = str_pad(isset($rightLines[$i]) ? $rightLines[$i] : '', $rightWidth, ' ');
            $allLines[] = $leftPart.str_repeat(' ', $space).$rightPart;
        }

        return implode("\n", $allLines)."\n";
    }

    /**
     * Download and convert logo to SIMPLE PNG
     * NO filters, just pure resize
     */
    public function download_image($url)
    {
        $file = basename($url);
        $logo_directory = dirname(__FILE__).'/../logos/';

        if (! file_exists($logo_directory)) {
            mkdir($logo_directory, 0777, true);
        }

        $fileInfo = pathinfo($file);
        $simplePng = $fileInfo['filename'].'.simple.png';
        $logo_image = $logo_directory.$simplePng;

        echo '> Target: '.$simplePng."\n";

        if (file_exists($logo_image)) {
            echo '> Cached ('.filesize($logo_image).' bytes)'."\n";

            return $logo_image;
        }

        $tempPng = $logo_directory.$file;
        echo '> Downloading from: '.$url."\n";

        try {
            $content = @file_get_contents($url);
            if (! $content) {
                echo '> Download failed'."\n";

                return false;
            }

            file_put_contents($tempPng, $content);
            echo '> Downloaded ('.strlen($content).' bytes)'."\n";

            if (! extension_loaded('gd')) {
                echo '> GD not loaded'."\n";
                @unlink($tempPng);

                return false;
            }

            echo '> Creating simple PNG...'."\n";

            $src = @imagecreatefrompng($tempPng);
            if (! $src) {
                echo '> Cannot read source PNG'."\n";
                @unlink($tempPng);

                return false;
            }

            $w = imagesx($src);
            $h = imagesy($src);

            // Resize to 200px width
            $maxW = 200;
            if ($w > $maxW) {
                $newW = $maxW;
                $newH = (int) (($h / $w) * $maxW);
            } else {
                $newW = $w;
                $newH = $h;
            }

            echo '> Resize: '.$w.'x'.$h.' -> '.$newW.'x'.$newH."\n";

            // Create SIMPLE truecolor image
            $dst = imagecreatetruecolor($newW, $newH);

            // White background
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);

            // Simple copy - NO FILTERS AT ALL!
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            // Save with NO compression (fastest, most compatible)
            $ok = @imagepng($dst, $logo_image, 0);

            imagedestroy($src);
            imagedestroy($dst);
            @unlink($tempPng);

            if ($ok && file_exists($logo_image)) {
                $size = filesize($logo_image);
                echo '> Saved ('.$size.' bytes)'."\n";
                chmod($logo_image, 0644);

                return $logo_image;
            }

            echo '> Save failed'."\n";

            return false;

        } catch (\Exception $e) {
            echo '> Exception: '.$e->getMessage()."\n";
            @unlink($tempPng);

            return false;
        }
    }
}
