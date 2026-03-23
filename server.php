<?php

require_once __DIR__.'/vendor/autoload.php';

use Twf\Pps\Escpos;

try {

    echo '> Starting server...', "\n";

    $websocket = new Hoa\Websocket\Server(
        new Hoa\Socket\Server('ws://127.0.0.1:6441')
    );

    $websocket->on('open', function (Hoa\Event\Bucket $bucket) {
        echo '> Connected', "\n";

    });

    $websocket->on('message', function (Hoa\Event\Bucket $bucket) {
        $data = $bucket->getData();
        $rdata = json_decode($data['message']);
        echo '> Received request ', $data['message'], "\n";

        if ($rdata === null || !isset($rdata->type)) {
            echo '> Invalid or malformed JSON received', "\n";
            return;
        }

        if ($rdata->type === 'check-status') {

            $bucket->getSource()->send('Server is running at <br><span>ws://localhost:4661</span>');

            return;

        } elseif ($rdata->type === 'open-cashdrawer') {

            echo '> Opening cash drawer ', "\n";

            if (! isset($rdata->printer_config) || empty($rdata->printer_config)) {

                $receipt_printer = get_receipt_printer();
                echo '> Trying receipt printer '.$receipt_printer->title, "\n";
                try {
                    $escpos = new Escpos();
                    $escpos->load($receipt_printer);
                    $escpos->open_drawer();
                    echo '> Opened', "\n";
                } catch (Exception $e) {
                    echo '> Error occurred, unable to open cash drawer. ', $e->getMessage(), "\n";
                }

            } else {

                try {
                    $escpos = new Escpos();
                    $escpos->load($rdata->printer_config);
                    $escpos->open_drawer();
                    echo '> Opened', "\n";
                } catch (Exception $e) {
                    echo '> Error occurred, unable to open cash drawer. ', $e->getMessage(), "\n";
                }

            }

            return;

        } elseif ($rdata->type === 'print-img') {

            echo '> Printing img', "\n";
            if (! isset($rdata->printer_config) || empty($rdata->printer_config)) {

                $receipt_printer = get_receipt_printer();
                echo '> Trying receipt printer '.$receipt_printer->title, "\n";
                try {
                    $escpos = new Escpos();
                    $escpos->load($receipt_printer);
                    $escpos->printImg($rdata->data->text);
                    echo '> Printed', "\n";
                } catch (Exception $e) {
                    echo '> Error occurred, unable to print. ', $e->getMessage(), "\n";
                }

            } else {

                try {
                    $escpos = new Escpos();
                    $escpos->load($rdata->printer_config);
                    $escpos->printImg($rdata->data->text);
                    echo '> Printed', "\n";
                } catch (Exception $e) {
                    echo '> Error occurred, unable to print. ', $e->getMessage(), "\n";
                }

            }

        } elseif ($rdata->type === 'print-data') {

            echo '> Printing ', "\n";
            $rdata->data = json_decode($rdata->data);
            if (! isset($rdata->printer_config) || empty($rdata->printer_config)) {

                $receipt_printer = get_receipt_printer();
                echo '> Trying receipt printer '.$receipt_printer->title, "\n";
                try {
                    $escpos = new Escpos();
                    $escpos->load($receipt_printer);
                    $escpos->print_data($rdata->data);
                    echo '> Printed', "\n";
                } catch (Exception $e) {
                    echo '> Error occurred, unable to print. ', $e->getMessage(), "\n";
                }

            } else {

                try {
                    $escpos = new Escpos();
                    $escpos->load($rdata->printer_config);
                    $escpos->print_invoice($rdata->data);
                    echo '> Printed', "\n";
                } catch (Exception $e) {
                    echo '> Error occurred, unable to print. ', $e->getMessage(), "\n";
                }

            }

        } elseif ($rdata->type === 'print-receipt') {

            echo '> Printing ', "\n";
            if (! isset($rdata->printer_config) || empty($rdata->printer_config)) {

                echo '> No printer data received, trying to get local printers', "\n";
                $printers = get_printers();

                if (isset($rdata->data->order) && ! empty($rdata->data->order)) {

                    $order_printers = get_order_printers();
                    foreach ($printers as $printer) {
                        if (in_array($printer->id, $order_printers)) {
                            echo '> Trying order printer '.$printer->title, "\n";
                            try {
                                $escpos = new Escpos();
                                $escpos->load($printer);
                                $escpos->printData($rdata->data);
                                echo '> Printed', "\n";
                            } catch (Exception $e) {
                                echo '> Error occurred, unable to print. ', $e->getMessage(), "\n";
                            }
                        }
                    }

                } else {

                    $receipt_printer = get_receipt_printer();
                    echo '> Trying receipt printer '.$receipt_printer->title, "\n";
                    try {
                        $escpos = new Escpos();
                        $escpos->load($receipt_printer);
                        $escpos->print_invoice($rdata->data);
                        echo '> Printed', "\n";
                    } catch (Exception $e) {
                        echo '> Error occurred, unable to print. ', $e->getMessage(), "\n";
                    }

                }

            } else {

                try {
                    $escpos = new Escpos();
                    $escpos->load($rdata->printer_config);
                    $escpos->print_invoice($rdata->data);
                    echo '> Printed', "\n";
                } catch (Exception $e) {
                    echo '> Error occurred, unable to print. ', $e->getMessage(), "\n";
                }

            }

            return;

        } else {
            echo '> Unkonwn type ', $rdata->type, "\n";
        }

    });

    $websocket->on('close', function (Hoa\Event\Bucket $bucket) {
        echo '> Disconnected', "\n";

    });

    try {
        echo '> Server started', "\n";
        $websocket->run();
    } catch (Exception $e) {
        echo '> Error occurred, server stopped. ', $e->getMessage(), "\n";
    }

} catch (Exception $e) {
    echo '> Error: ', $e->getMessage(), "\n";
}

function read_database(): object
{
    $file = file_get_contents('database/data.json');
    $data = ($file !== false) ? json_decode($file) : null;

    if (empty($data) || !is_object($data)) {
        $default = new \stdClass();
        $default->printers = [];
        $default->order_printers = [];
        $default->receipt_printer = '';
        return $default;
    }

    return $data;
}

function get_printers(): array
{
    $data = read_database();

    return isset($data->printers) && is_array($data->printers) ? $data->printers : [];
}

function get_receipt_printer(): ?object
{
    $printers = get_printers();
    $receipt_printer = get_receipt_printer_id();
    foreach ($printers as $printer) {
        if (is_object($printer) && isset($printer->id) && $printer->id === $receipt_printer) {
            return $printer;
        }
    }
    return null;
}

function get_receipt_printer_id(): string
{
    $data = read_database();

    return ! empty($data->receipt_printer) ? $data->receipt_printer : '';
}

function get_order_printers(): array
{
    $data = read_database();

    return empty($data->order_printers) ? [] : (array)$data->order_printers;
}
