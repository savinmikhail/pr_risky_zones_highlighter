<?php

namespace SavinMikhail\Tests\PrRiskHighLighter;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use SavinMikhail\PrRiskHighLighter\Highlighter;

class ParseDiffTest extends TestCase
{
    public function testParseDiff()
    {
        $diff = file_get_contents(__DIR__ . '/diffs.txt');

        $expected = [
            'test.php' => [
                ['line' => 1, 'text' => ' <?php', 'type' => 'context'],
                ['line' => 2, 'text' => '', 'type' => 'context'],
                ['line' => 3, 'text' => '+declare(strict_types=1);', 'type' => 'add'],
                ['line' => 4, 'text' => '', 'type' => 'context'],
                ['line' => 5, 'text' => ' $env = $_ENV;', 'type' => 'context'],
            ],
        ];

        $highlighter = new Highlighter(new Client(), 'dummytoken');
        $actual = $highlighter->parseDiff($diff);
        $this->assertEquals($expected, $actual);
    }
}
