<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use SavinMikhail\PrRiskHighLighter\CommentParser;
use SavinMikhail\PrRiskHighLighter\Highlighter;

// Expected arguments: GPT API key, GPT URL, GitHub Token, Repository Full Name, Pull Number
if ($argc < 6) {
    echo "Insufficient arguments provided.\n";
    exit(1);
}

foreach ($argv as $key => $arg) {
    if (empty($arg)) {
        echo "Empty argument N" . ($key + 1) . " provided.\n";
        exit(1);
    }
}

$gptApiKey = $argv[1];
$gptUrl = $argv[2];
$githubToken = $argv[3];
$repoFullName = $argv[4];
$pullNumber = $argv[5];

// Main workflow
$highlighter = new Highlighter(new Client(), $githubToken);
$diffs = $highlighter->getPullRequestDiff($repoFullName, $pullNumber);
echo "\nFetched diffs are:\n";
print_r($diffs);

$files = $highlighter->parseDiff($diffs);
echo "\n";
print_r($files);

$analysis = $highlighter->analyzeCodeWithChatGPT($files, $gptApiKey, $gptUrl);
echo "\nChatGPT analysis is:\n";
print_r($analysis);

$reviewId = $highlighter->startReview($repoFullName, $pullNumber);
$parser = new CommentParser();

foreach ($analysis as $file => $comments) {
    $parsed = $parser->parseComments($comments);

    foreach ($parsed as $line => $comment) {
        $highlighter->addReviewComment(
            $repoFullName,
            $pullNumber,
            $reviewId,
            $line,
            $file,
            $comment,
        );
    }
}

$highlighter->submitReview($repoFullName, $pullNumber, $reviewId);
