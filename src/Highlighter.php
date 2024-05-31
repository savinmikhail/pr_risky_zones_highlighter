<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use function print_r;

use const PHP_INT_MAX;

final readonly class Highlighter
{
    public function __construct(
        private int $argc,
        private array $argv,
        private CLient $client
    ) {
    }

    private function validateInput(): void
    {
        if ($this->argc < 6) {
            throw new \InvalidArgumentException("Insufficient arguments provided.");
        }

        foreach ($this->argv as $key => $arg) {
            //the max comment arg is not required
            if ($key === 6) {
                continue;
            }
            if (empty($arg)) {
                throw new \InvalidArgumentException("Empty argument N" . ($key) . " provided.");
            }
        }
    }

    private function getGptApiKey(): string
    {
        return $this->argv[1];
    }

    private function getGptUrl(): string
    {
        return $this->argv[2];
    }
    private function getGitHubToken(): string
    {
        return $this->argv[3];
    }
    private function getRepoFullName(): string
    {
        return $this->argv[4];
    }
    private function getPullNumber(): string
    {
        return $this->argv[5];
    }
    private function getMaxComments(): int
    {
        return $this->argv[6] ? intval($this->argv[6]) : PHP_INT_MAX;
    }

    /**
     * @throws GuzzleException
     */
    public function review(): void
    {
        $this->validateInput();

        $githubClient = new GitHubClient(
            $this->client,
            $this->getGitHubToken(),
            $this->getRepoFullName(),
            $this->getPullNumber()
        );

        $diffs = $githubClient->getPullRequestDiff();
        echo "\nFetched diffs are:\n" . print_r($diffs, true);

        $parser = new DiffParser();
        $parsedDiffs = $parser->parseDiff($diffs);
        echo "\n" . print_r($parsedDiffs, true);

        $chatGPTAnalyzer = new ChatGPTAnalyzer($this->client, $this->getGptApiKey(), $this->getGptUrl());
        $analysis = $chatGPTAnalyzer->analyzeCodeWithChatGPT($parsedDiffs, $this->getMaxComments());
        echo "\nChatGPT analysis is:\n" . print_r($analysis, true);

        $reviewId = $githubClient->getReviewId();

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
