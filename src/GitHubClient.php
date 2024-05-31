<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

use function json_decode;
use function print_r;

use const PHP_EOL;

final readonly class GitHubClient
{
    private const BASE_URL = "https://api.github.com/repos/";
    private const API_VERSION = "application/vnd.github.v3+json";
    private const DIFF_API_VERSION = "application/vnd.github.v3.diff";

    public function __construct(
        private Client $client,
        private string $githubToken,
        private string $repoFullName,
        private string $pullNumber
    ) {
    }

    /**
     * @throws Exception
     */
    private function githubApiRequest(
        string $url,
        string $method = 'GET',
        array $data = [],
        string $acceptHeader = self::API_VERSION,
        bool $shouldFail = true
    ): string {
        $headers = [
            'Authorization' => 'token ' . $this->githubToken,
            'User-Agent' => 'PHP Script',
            'Accept' => $acceptHeader,
            'Content-Type' => 'application/json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        $options = ['headers' => $headers];
        if ($method !== 'GET') {
            $options['json'] = $data;
        }

        try {
            $response = $this->client->request($method, $url, $options);

            if ($response->getStatusCode() >= 400) {
                throw new RuntimeException("HTTP error: " . $response->getStatusCode());
            }

            return $response->getBody()->getContents();
        } catch (RequestException | GuzzleException $e) {
            echo "Request error: " . $e->getMessage() . PHP_EOL;
            if ($e->hasResponse()) {
                echo "Response: " . $e->getResponse()->getBody() . PHP_EOL;
            }
            if ($shouldFail) {
                throw new Exception($e->getMessage());
            }
        }
    }

    public function getPullRequestCommitId(): string
    {
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber";
        $response = $this->githubApiRequest($url);
        $responseArray = json_decode($response, true);
        return $responseArray['head']['sha']; // The latest commit SHA on the pull request
    }

    public function getPullRequestDiff(): string
    {
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber";
        return $this->githubApiRequest(
            $url,
            acceptHeader: self::DIFF_API_VERSION
        );
    }
    public function startReview(): int
    {
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber/reviews";
        $data = [
            'body' => 'Starting review',
        ];

        $response = $this->githubApiRequest($url, 'POST', $data);

        $responseArray = json_decode($response, true);
        if (!isset($responseArray['id'])) {
            throw new RuntimeException('Failed to retrieve review ID from response.');
        }

        echo 'Started review successfully.' . PHP_EOL;
        return $responseArray['id'];  // Return the review ID to use for adding comments
    }

    public function addReviewComment(
        string $commitId,
        string $body,
        string $path,
        int $position,
        string $diffHunk
    ): void {
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber/comments";
        $data = [
            'body' => $body,
            'commit_id' => $commitId,
            'path' => $path,
            'diff_hunk' => $diffHunk,
            'position' => $position,
        ];
        echo 'send comment with data: ' . PHP_EOL;
        print_r($data);
        $this->githubApiRequest($url, 'POST', $data, 'application/vnd.github+json', false);
    }

    public function submitReview(
        int $reviewId,
    ): void {
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber/reviews/$reviewId/events";
        $data = [
            'event' => 'COMMENT',
            'body' => 'Please address the review comments.'
        ];
        $this->githubApiRequest($url, 'POST', $data, 'application/vnd.github+json');
        echo "Review submitted successfully.\n";
    }
}
