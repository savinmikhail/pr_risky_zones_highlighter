<?php

use PHPUnit\Framework\TestCase;

class ParseDiffTest extends TestCase
{
    public function testParseDiff()
    {
        $diff = file_get_contents(__DIR__.'/diffs.txt');

        $expected = [
            '.github/workflows/main.yml' => [
                ['line' => 9, 'text' => '     runs-on: ubuntu-latest', 'type' => 'context'],
                ['line' => 12, 'text' => '     steps:', 'type' => 'context'],
                ['line' => 13, 'text' => '-    - name: Checkout code', 'type' => 'context'],
                ['line' => 14, 'text' => '-      uses: actions/checkout@v2', 'type' => 'context'],
                ['line' => 15, 'text' => '', 'type' => 'context'],
                ['line' => 16, 'text' => '-    - name: Run custom action', 'type' => 'context'],
                ['line' => 17, 'text' => '-      uses: savinmikhail/pr_risky_zones_highlighter@v0.1.0', 'type' => 'context'],
                ['line' => 18, 'text' => '+    - name: Run Risk Analysis', 'type' => 'add'],
                ['line' => 19, 'text' => '+      uses: savinmikhail/pr_risky_zones_highlighter@0.1.x', 'type' => 'add'],
                ['line' => 20, 'text' => '       with:', 'type' => 'context'],
                ['line' => 21, 'text' => '         gpt_api_key: ${{ secrets.GPT_API_KEY }}', 'type' => 'context'],
                ['line' => 22, 'text' => '+        gpt_url: \'https://api.proxyapi.ru/openai\'', 'type' => 'add'],
                ['line' => 23, 'text' => '+        github_token: ${{ secrets.GITHUB_TOKEN }}', 'type' => 'add'],
                ['line' => 24, 'text' => '+        repo_full_name: ${{ github.repository }}', 'type' => 'add'],
                ['line' => 25, 'text' => '+        pull_number: ${{ github.event.pull_request.number }}', 'type' => 'add'],
            ],
            'test.php' => [
                ['line' => 1, 'text' => ' <?php', 'type' => 'context'],
                ['line' => 2, 'text' => '', 'type' => 'context'],
                ['line' => 3, 'text' => '+declare(strict_types=1);', 'type' => 'add'],
                ['line' => 4, 'text' => '', 'type' => 'context'],
                ['line' => 5, 'text' => ' $env = $_ENV;', 'type' => 'context'],
            ],
        ];

        $actual = parseDiff($diff);
        $this->assertEquals($expected, $actual);
    }
}
