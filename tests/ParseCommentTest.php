<?php

declare(strict_types=1);


namespace SavinMikhail\Tests\PrRiskHighLighter;

use PHPUnit\Framework\TestCase;
use SavinMikhail\PrRiskHighLighter\CommentParser;

class ParseCommentTest extends TestCase
{
    public function testParseComment(): void
    {
        $comment = '[line 16] - It is good practice to use environment variables stored in secrets to store sensitive information like API keys instead of hardcoding them directly into the workflow file. This helps in keeping the sensitive information secure and separate from the codebase. Good implementation of security best practices.';
        $parser = new CommentParser();
        $parced = $parser->parseComment($comment);
        $expected = [
            'line' => 16,
            'comment' => 'It is good practice to use environment variables stored in secrets to store sensitive information like API keys instead of hardcoding them directly into the workflow file. This helps in keeping the sensitive information secure and separate from the codebase. Good implementation of security best practices.'
        ];
        $this->assertEquals($expected, $parced);
    }
}