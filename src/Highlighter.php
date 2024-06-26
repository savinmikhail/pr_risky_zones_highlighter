<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

use const PHP_EOL;

final readonly class Highlighter
{
    public function __construct(private Client $client, private string $githubToken)
    {
    }

    public function getPullRequestCommitId(string $repoFullName, string $pullNumber): string
    {
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber";

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'token ' . $this->githubToken,
                    'User-Agent' => 'PHP Script',
                    'Accept' => 'application/vnd.github.v3+json'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(
                    'Failed to get pull request details. Status code: ' . $response->getStatusCode()
                );
            }

            $responseArray = json_decode((string) $response->getBody(), true);
            return $responseArray['head']['sha']; // The latest commit SHA on the pull request
        } catch (RequestException $e) {
            echo "Request error: " . $e->getMessage() . "\n";
            if ($e->hasResponse()) {
                echo "Response: " . $e->getResponse()->getBody() . "\n";
            }
            exit(1);
        } catch (GuzzleException $e) {
            echo "Request error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    public function getPullRequestDiff(string $repoFullName, string $pullNumber): string
    {
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber";

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'token ' . $this->githubToken,
                    'User-Agent' => 'PHP Script',
                    'Accept' => 'application/vnd.github.v3.diff'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(
                    'Failed to get PR differences. Status code: ' . $response->getStatusCode()
                );
            }

            echo "Successfully get PR differences.\n";
            return (string) $response->getBody();
        } catch (RequestException $e) {
            echo "Request error: " . $e->getMessage() . "\n";
            if ($e->hasResponse()) {
                echo "Response: " . $e->getResponse()->getBody() . "\n";
            }
            exit(1);
        } catch (GuzzleException $e) {
            echo "Request error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    public function analyzeCodeWithChatGPT(array $files, string $gptApiKey, string $gptUrl): array
    {
        $responses = [];
        foreach ($files as $file => $data) {
            $changesText = implode("\n", array_map(static function (array $change): string {
                return "[line {$change['line']}] {$change['text']}";
            }, $data));

            $postData = [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        "role" => "system",
                        "content" => "You are a senior developer. 
                        You will receive the code differences from pull request.
                        Review the changes for potential vulnerabilities, bugs or poor design.
                        Do not say what is good. Only risky parts must be commented.
                        You MUST provide the answer like: 
                        '[line 3] - The addition of `declare(strict_types=1)` may lead to errors in previous code, 
                        which worked with php type conversion'
                        '[line 10] - The usage of dd() in production environment may lead to code exposition'
                        Other way I wouldn't be able to parse your response.
                        You can use GitHub markdown syntax."
                    ],
                    [
                        "role" => "user",
                        "content" => "Analyze changes to $file:\n" . $changesText
                    ],
                ],
                'temperature' => 1.0,
                'max_tokens' => 4000,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ];

            try {
                $response = $this->client->post("$gptUrl/v1/chat/completions", [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $gptApiKey,
                    ],
                    'json' => $postData,
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException(
                        'Failed to get review from ChatGPT. Status code: ' . $response->getStatusCode()
                    );
                }

                $responseArray = json_decode((string) $response->getBody(), true);
                echo "Successfully get review from ChatGPT.\n";
                $responses[$file] = $responseArray['choices'][0]['message']['content'];
            } catch (RequestException $e) {
                echo "Request error: " . $e->getMessage() . "\n";
                if ($e->hasResponse()) {
                    echo "Response: " . $e->getResponse()->getBody() . "\n";
                }
                exit(1);
            }
        }
        return $responses;
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
                    $diffHunk .= $line->content() . "\n";
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
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber/reviews";
        $data = [
            'body' => 'Starting review',
        ];

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'token ' . $this->githubToken,
                    'User-Agent' => 'PHP Script',
                    'Content-Type' => 'application/json'
                ],
                'json' => $data,
            ]);

            if ($response->getStatusCode() !== 200) { // 200 OK for creating a review
                throw new \RuntimeException(
                    'Failed to start review. HTTP status: ' . $response->getStatusCode()
                );
            }
            echo 'Started review successfully';
            $responseArray = json_decode((string) $response->getBody(), true);
            return $responseArray['id'];  // Review ID to use for adding comments
        } catch (RequestException $e) {
            echo "Request error: " . $e->getMessage() . "\n";
            if ($e->hasResponse()) {
                echo "Response: " . $e->getResponse()->getBody() . "\n";
            }
            exit(1);
        }
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
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber/comments";
        $data = [
            'body' => $body,
            'commit_id' => $commitId,
            'path' => $path,
            'diff_hunk' => $diffHunk,
            'position' => $position,
        ];
        echo 'send comment with data: ' . PHP_EOL;
        print_r($data);
        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->githubToken,
                    'User-Agent' => 'PHP Script',
                    'Content-Type' => 'application/json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'Accept' => 'application/vnd.github+json'
                ],
                'json' => $data,
            ]);

            if ($response->getStatusCode() !== 201) {
                throw new \RuntimeException(
                    'Failed to add review comment. HTTP status: ' . $response->getStatusCode()
                );
            }
            echo 'Added comment successfully' . PHP_EOL;
        } catch (RequestException $e) {
            echo "Request error: " . $e->getMessage() . "\n";
            if ($e->hasResponse()) {
                echo "Response: " . $e->getResponse()->getBody() . "\n";
            }
            //do not fail the job, keep going
        }
    }

    public function submitReview(
        string $repoFullName,
        string $pullNumber,
        int $reviewId,
    ): void {
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber/reviews/$reviewId/events";
        $data = [
            'event' => 'COMMENT',
            'body' => 'Please address the review comments.'
        ];

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'token ' . $this->githubToken,
                    'User-Agent' => 'PHP Script',
                    'Content-Type' => 'application/json'
                ],
                'json' => $data,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(
                    'Failed to submit review. HTTP status: ' . $response->getStatusCode()
                );
            }

            echo "Review submitted successfully.\n";
        } catch (RequestException $e) {
            echo "Request error: " . $e->getMessage() . "\n";
            if ($e->hasResponse()) {
                echo "Response: " . $e->getResponse()->getBody() . "\n";
            }
            exit(1);
        }
    }
}
