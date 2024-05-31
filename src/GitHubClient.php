<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

use function in_array;
use function json_decode;
use function print_r;

use const PHP_EOL;

final class GitHubClient
{
    private const BASE_URL = 'https://api.github.com/repos/';
    private const API_VERSION = 'application/vnd.github.v3+json';
    private const DIFF_API_VERSION = 'application/vnd.github.v3.diff';
    private const BOT_ID = 41898282;

    private string $state;

    public function __construct(
        private readonly Client $client,
        private readonly string $githubToken,
        private readonly string $repoFullName,
        private readonly string $pullNumber
    ) {
    }

    /**
     * @throws GuzzleException
     */
    public function getPendingReview(): ?int
    {
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber/reviews";
        $reviews = json_decode($this->githubApiRequest($url), true);
        echo 'fetching existing reviews...' . PHP_EOL;
        foreach ($reviews as $review) {
            echo 'existing review is: ' . print_r($review, true) . PHP_EOL;
            if (
                (in_array($review['state'], ['PENDING', 'COMMENTED'], true))
                && $review['user']['id'] === self::BOT_ID
            ) {
                echo 'comments will be added to existing review ' . $review['id'] . PHP_EOL;
                $this->state = $review['state'];
                return $review['id'];  // Return the first pending review found for the bot user
            }
        }
        return null;  // No pending reviews for the bot
    }

    /**
     * @throws GuzzleException
     */
    public function getReviewId(): int
    {
        $existingReviewId = $this->getPendingReview();
        return $existingReviewId !== null ? $existingReviewId : $this->startReview();
    }

    /**
     * @throws Exception|GuzzleException
     */
    private function githubApiRequest(
        string $url,
        string $method = 'GET',
        array $data = [],
        string $acceptHeader = self::API_VERSION,
    ): string {
        $headers = [
            'Authorization' => 'Bearer ' . $this->githubToken,
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
        } catch (Exception $e) {
            echo "Request error: " . $e->getMessage() . PHP_EOL;
            if ($e instanceof GuzzleException) {
                if ($e->hasResponse()) {
                    echo "Response: " . $e->getResponse()->getBody() . PHP_EOL;
                }
            }
            throw $e;
        }
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function getPullRequestCommitId(): string
    {
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber";
        $response = $this->githubApiRequest($url);
        $responseArray = json_decode($response, true);
        return $responseArray['head']['sha']; // The latest commit SHA on the pull request
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function getPullRequestDiff(): string
    {
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber";
        return $this->githubApiRequest(
            $url,
            acceptHeader: self::DIFF_API_VERSION
        );
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    private function startReview(): int
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

    /**
     * @throws Exception
     * @throws GuzzleException
     */
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
        echo 'about to send comment with data: ' . PHP_EOL;
        print_r($data);
        try {
            $this->githubApiRequest($url, 'POST', $data, 'application/vnd.github+json');
            echo 'comment posted successfully' . PHP_EOL;
            //we want to catch exception here to try to submit other comments
        } catch (Exception) {
            echo 'failed to post comment';
            //doing nothing here
        }
    }

    private function shouldSubmitReview(): bool
    {
        return $this->state === 'PENDING';
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function submitReview(
        int $reviewId,
    ): void {
        if (! $this->shouldSubmitReview()) {
            return;
        }
        $url = self::BASE_URL . "$this->repoFullName/pulls/$this->pullNumber/reviews/$reviewId/events";
        $data = [
            'event' => 'COMMENT',
            'body' => 'Please address the review comments.'
        ];
        $this->githubApiRequest($url, 'POST', $data, 'application/vnd.github+json');
        echo "Review submitted successfully.\n";
    }
}
