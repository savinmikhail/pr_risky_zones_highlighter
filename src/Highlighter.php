<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

use GuzzleHttp\Client;

use function print_r;

use const PHP_INT_MAX;

final readonly class Highlighter
{
    public function __construct(private int $argc, private array $argv, private CLient $client)
    {
    }

    private function validateInput(): void
    {
        // Expected arguments: GPT API key, GPT URL, GitHub Token, Repository Full Name, Pull Number
        if ($this->argc < 6) {
            echo "Insufficient arguments provided.\n";
            exit(1);
        }

        foreach ($this->argv as $key => $arg) {
            if (empty($arg)) {
                echo "Empty argument N" . ($key + 1) . " provided.\n";
                exit(1);
            }
        }
    }

    public function review(): void
    {
        $this->validateInput();
        $gptApiKey = $this->argv[1];
        $gptUrl = $this->argv[2];
        $githubToken = $this->argv[3];
        $repoFullName = $this->argv[4];
        $pullNumber = $this->argv[5];
        $maxComments = $this->argv[6] ?? PHP_INT_MAX;

        $githubClient = new GitHubClient($this->client, $githubToken, $repoFullName, $pullNumber);
        $diffs = $githubClient->getPullRequestDiff();
        echo "\nFetched diffs are:\n" . print_r($diffs, true);

        $parser = new DiffParser();
        $parsedDiffs = $parser->parseDiff($diffs);
        echo "\n" . print_r($parsedDiffs, true);

        $chatGPTAnalyzer = new ChatGPTAnalyzer($this->client, $gptApiKey, $gptUrl);
        $analysis = $chatGPTAnalyzer->analyzeCodeWithChatGPT($parsedDiffs, $maxComments);
        echo "\nChatGPT analysis is:\n" . print_r($analysis, true);

        $reviewId = $githubClient->startReview();

        $commitId = $githubClient->getPullRequestCommitId();
        echo "Commit ID: $commitId\n";

        $parser = new CommentParser();
        foreach ($analysis as $file => $comments) {
            $parsedComments = $parser->parseComments($comments);

            foreach ($parsedComments as $line => $comment) {
                foreach ($parsedDiffs[$file] as $diff) {
                    if ($diff['line'] === $line) {
                        $position = $diff['diffPosition'];
                        $diffHunk = $diff['diffHunk'];
                    }
                }
                $githubClient->addReviewComment(
                    commitId: $commitId,
                    body: $comment,
                    path: $file,
                    position: $position,
                    diffHunk: $diffHunk
                );
            }
        }

        $githubClient->submitReview($reviewId);
    }
}
