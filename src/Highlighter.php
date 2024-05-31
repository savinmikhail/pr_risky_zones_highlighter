<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

use function array_map;
use function array_slice;
use function explode;
use function implode;
use function json_decode;
use function min;
use function print_r;
use function str_starts_with;
use function substr;

use const PHP_EOL;

final readonly class Highlighter
{
    private const BASE_URL = "https://api.github.com/repos/";
    private const API_VERSION = "application/vnd.github.v3+json";
    private const DIFF_API_VERSION = "application/vnd.github.v3.diff";

    public function __construct(private Client $client, private string $githubToken)
    {
    }

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
                exit(1);
            }
        }
    }

    public function getPullRequestCommitId(string $repoFullName, string $pullNumber): string
    {
        $url = self::BASE_URL . "$repoFullName/pulls/$pullNumber";
        $response = $this->githubApiRequest($url);
        $responseArray = json_decode($response, true);
        return $responseArray['head']['sha']; // The latest commit SHA on the pull request
    }

    public function getPullRequestDiff(string $repoFullName, string $pullNumber): string
    {
        $url = self::BASE_URL . "$repoFullName/pulls/$pullNumber";
        return $this->githubApiRequest(
            $url,
            acceptHeader: self::DIFF_API_VERSION
        );
    }

    public function analyzeCodeWithChatGPT(
        array $files,
        string $gptApiKey,
        string $gptUrl,
        int $maxComments
    ): array {
        $responses = [];
        $remainingComments = $maxComments; // Initialize with the total allowed comments

        foreach ($files as $file => $data) {
            if ($remainingComments <= 0) {
                break; // Stop processing if no more comments are allowed
            }

            $changesText = $this->formatChanges($data);
            $systemPrompt = $this->createSystemPrompt($remainingComments);

            $postData = $this->createPostData($systemPrompt, $file, $changesText);

            try {
                $content = $this->getPostResponse($postData, $gptApiKey, $gptUrl);
                $comments = $this->processComments($content, $remainingComments);

                $responses[$file] = implode(PHP_EOL, $comments);
                $remainingComments -= count($comments);

                echo "Successfully got review from ChatGPT.\n";
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage() . PHP_EOL;
                exit(1);
            }
        }
        return $responses;
    }

    private function formatChanges(array $data): string
    {
        return implode(PHP_EOL, array_map(static function (array $change): string {
            return "[line {$change['line']}] {$change['text']}";
        }, $data));
    }

    private function createSystemPrompt(int $remainingComments): string
    {
        return "You are a senior developer. Review the code differences from a pull request for potential 
            vulnerabilities, bugs, or poor design. 
            Do not mention what is good. 
            Only risky parts must be commented. 
            You MUST provide at most $remainingComments comments, so choose the most riskiest parts of the code. 
            Use the format: '[line X] - Comment'. 
            Each comment must start from a new line. 
            You can use GitHub markdown syntax.";
    }

    private function createPostData(string $systemPrompt, string $file, string $changesText): array
    {
        return [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => "Analyze changes to $file:\n" . $changesText]
            ],
            'temperature' => 1.0,
            'max_tokens' => 4000,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ];
    }

    /**
     * @throws GuzzleException
     */
    private function getPostResponse(array $postData, string $gptApiKey, string $gptUrl): string
    {
        $response = $this->client->post("$gptUrl/v1/chat/completions", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $gptApiKey,
            ],
            'json' => $postData,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(
                'Failed to get review from ChatGPT. Status code: ' . $response->getStatusCode()
            );
        }

        $responseArray = json_decode((string) $response->getBody(), true);
        return $responseArray['choices'][0]['message']['content'];
    }

    private function processComments(string $content, int $remainingComments): array
    {
        $comments = explode(PHP_EOL, $content);
        return array_slice($comments, 0, min(count($comments), $remainingComments));
    }

    public function parseDiff(string $diff): array
    {
        $parser = new Parser();
        $diffs = $parser->parse($diff);
        $files = [];
        foreach ($diffs as $diff) {
            $currentFile = $diff->to();
            if (str_starts_with($currentFile, 'b/')) {
                $currentFile = substr($currentFile, 2);
            }
            $files[$currentFile] = [];

            foreach ($diff->chunks() as $chunk) {
                $currentPosition = $chunk->start();
                $diffPosition = 1; // Start from 1

                // Extract the hunk header for the diff
                $diffHunk = "@@ -"
                    . $chunk->start()
                    . ","
                    . $chunk->startRange()
                    . " +"
                    . $chunk->end()
                    . ","
                    . $chunk->endRange()
                    . " @@\n";
                foreach ($chunk->lines() as $line) {
                    $diffHunk .= $line->content() . PHP_EOL;
                }

                foreach ($chunk->lines() as $line) {
                    $type = $line->type();
                    $lineType = $type === Line::ADDED ? 'add' : ($type === Line::UNCHANGED ? 'context' : 'remove');

                    if ($type !== Line::REMOVED) {
                        $files[$currentFile][] = [
                            'line' => $currentPosition,
                            'text' => $line->content(),
                            'type' => $lineType,
                            'diffPosition' => $diffPosition,
                            'diffHunk' => $diffHunk
                        ];
                    }

                    if ($type === Line::UNCHANGED || $type === Line::ADDED) {
                        $currentPosition++;
                    }
                    $diffPosition++;
                }
            }
        }
        return $files;
    }

    public function startReview(string $repoFullName, string $pullNumber): int
    {
        $url = self::BASE_URL . "$repoFullName/pulls/$pullNumber/reviews";
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
        string $repoFullName,
        string $pullNumber,
        string $commitId,
        string $body,
        string $path,
        int $position,
        string $diffHunk
    ): void {
        $url = self::BASE_URL . "$repoFullName/pulls/$pullNumber/comments";
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
        string $repoFullName,
        string $pullNumber,
        int $reviewId,
    ): void {
        $url = self::BASE_URL . "$repoFullName/pulls/$pullNumber/reviews/$reviewId/events";
        $data = [
            'event' => 'COMMENT',
            'body' => 'Please address the review comments.'
        ];
        $this->githubApiRequest($url, 'POST', $data, 'application/vnd.github+json');
        echo "Review submitted successfully.\n";
    }
}
