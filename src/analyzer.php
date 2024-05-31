<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use SavinMikhail\PrRiskHighLighter\Highlighter;

$highlighter = new Highlighter($argc, $argv, new Client());
try {
    $highlighter->review();
} catch (Exception $e) {
    exit(1);
}
