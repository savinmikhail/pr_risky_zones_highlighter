<?php

declare(strict_types=1);

namespace SavinMikhail\Tests\PrRiskHighLighter;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SavinMikhail\PrRiskHighLighter\DiffParser;

final class DiffParserTest extends TestCase
{
    private readonly DiffParser $diffParser;

    protected function setUp(): void
    {
        $this->diffParser = new DiffParser();
    }

    public function testParseSimpleDiff()
    {
        $diff = file_get_contents(__DIR__ . '/diffs.txt');

        $result = $this->diffParser->parseDiff($diff);
        $this->assertEquals($this->expectedDiff(), $result);
    }

    public function testNormalizeFilePath()
    {
        $path = 'b/test.php';
        $expected = 'test.php';

        $reflector = new ReflectionClass(DiffParser::class);
        $method = $reflector->getMethod('normalizeFilePath');
        $method->setAccessible(true);

        $result = $method->invoke($this->diffParser, $path);
        $this->assertEquals($expected, $result);
    }

    private function expectedDiff(): array
    {
        return
            [
//                '.github/workflows/main.yml' => [
//                        [
//                            'line' => 9,
//                            'type' => 'context',
//                            'diffPosition' => 1,
//                            'text' => '    runs-on: ubuntu-latest'
//                        ],
//                        [
//                            'line' => 10,
//                            'type' => 'remove',
//                            'text' => '-      uses: actions/checkout@v2'
//                        ],
//                        [
//                            'line' => 11,
//                            'type' => 'remove',
//                            'text' => '-    - name: Run custom action'
//                        ],
//                        [
//                            'line' => 12,
//                            'type' => 'remove',
//                            'text' => '-      uses: savinmikhail/pr_risky_zones_highlighter@v0.1.0'
//                        ],
//                        [
//                            'line' => 9,
//                            'type' => 'add',
//                            'text' => '+    - name: Run Risk Analysis'
//                        ],
//                        [
//                            'line' => 10,
//                            'type' => 'add',
//                            'text' => '+      uses: savinmikhail/pr_risky_zones_highlighter@1.2.0'
//                        ],
//                        [
//                            'line' => 11,
//                            'type' => 'add',
//                            'text' => '+      with:'
//                        ],
//                        [
//                            'line' => 12,
//                            'type' => 'add',
//                            'text' => '+        gpt_api_key: ${{ secrets.GPT_API_KEY }}'
//                        ],
//                        [
//                        'line' => 13,
//                        'type' => 'add',
//                        'text' => '+        gpt_url: "https://api.proxyapi.ru/openai"'
//                        ],
//                        [
//                        'line' => 14,
//                        'type' => 'add',
//                        'text' => '+        github_token: ${{ secrets.GITHUB_TOKEN }}'
//                        ],
//                        [
//                        'line' => 15,
//                        'type' => 'add',
//                        'text' => '+        repo_full_name: ${{ github.repository }}'
//                        ],
//                        [
//                        'line' => 16,
//                        'type' => 'add',
//                        'text' => '+        pull_number: ${{ github.event.pull_request.number }}'
//                        ],
//                        [
//                        'line' => 17,
//                        'type' => 'add',
//                        'text' => '+        max_comments: 2'
//                        ]
//                ],
                '.gitignore' => [
                    [
                        'line' => 0,
                        'text' => '.idea',
                        'type' => 'add',
                        'diffPosition' => 1
                    ]
                ],
                'test.php' => [
                    [
                        'line' => 1,
                        'text' => '<?php',
                        'type' => 'context',
                        'diffPosition' => 1,
                    ],
                    [
                        'line' => 2,
                        'text' => '',
                        'type' => 'context',
                        'diffPosition' => 2,
                    ],
                    [
                        'line' => 3,
                        'text' => 'declare(strict_types=1);',
                        'type' => 'add',
                        'diffPosition' => 3,
                    ],
                    [
                        'line' => 4,
                        'text' => '',
                        'type' => 'add',
                        'diffPosition' => 4,
                    ],
                    [
                        'line' => 5,
                        'text' => '$env = $_ENV;',
                        'type' => 'context',
                        'diffPosition' => 5,
                    ],
                ]
        ];
    }
}
