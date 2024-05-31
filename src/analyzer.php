<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SavinMikhail\PrRiskHighLighter\Highlighter;

$highlighter = new Highlighter($argc, $argv);
$highlighter->review();
