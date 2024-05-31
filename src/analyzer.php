<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use SavinMikhail\PrRiskHighLighter\CommentParser;
use SavinMikhail\PrRiskHighLighter\Highlighter;
use SebastianBergmann\Diff\Parser;

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
$maxComments = PHP_INT_MAX;
if (isset($argv[6])) {
    $maxComments = $argv[6];
}

// Main workflow
$highlighter = new Highlighter(new Client(), $githubToken);
$diffs = $highlighter->getPullRequestDiff($repoFullName, $pullNumber);
echo "\nFetched diffs are:\n";
print_r($diffs);

$parser = new Parser();
$parsedDiffs = $highlighter->parseDiff($diffs);
echo "\n";
print_r($parsedDiffs);

$analysis = $highlighter->analyzeCodeWithChatGPT($parsedDiffs, $gptApiKey, $gptUrl, $maxComments);
echo "\nChatGPT analysis is:\n";
print_r($analysis);

$reviewId = $highlighter->startReview($repoFullName, $pullNumber);
$parser = new CommentParser();

$commitId = $highlighter->getPullRequestCommitId($repoFullName, $pullNumber);
echo "Commit ID: $commitId\n";

foreach ($analysis as $file => $comments) {
    $parsedComments = $parser->parseComments($comments);

    foreach ($parsedComments as $line => $comment) {
        foreach ($parsedDiffs[$file] as $diff) {
            if ($diff['line'] === $line) {
                $position = $diff['diffPosition'];
                $diffHunk = $diff['diffHunk'];
            }
        }
        $highlighter->addReviewComment(
            repoFullName: $repoFullName,
            pullNumber: $pullNumber,
            commitId: $commitId,
            body: $comment,
            path: $file,
            position: $position,
            diffHunk: $diffHunk
        );
    }
}

$highlighter->submitReview($repoFullName, $pullNumber, $reviewId);
