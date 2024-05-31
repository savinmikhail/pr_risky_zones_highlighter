<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

use function array_map;
use function array_slice;
use function explode;
use function implode;
use function json_decode;
use function min;

use const PHP_EOL;

final readonly class ChatGPTAnalyzer
{
    public function __construct(
        private Client $client,
        private string $gptApiKey,
        private string $gptUrl,
    ) {
    }

    public function analyzeCodeWithChatGPT(
        array $files,
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
                $content = $this->getPostResponse($postData, $this->gptApiKey, $this->gptUrl);
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
}
