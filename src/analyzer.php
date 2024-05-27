<?php

declare(strict_types=1);

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
$diffs = getPullRequestDiff($repoFullName, $pullNumber, $githubToken);
echo "\nFetched diffs are:\n";
print_r($diffs);

$files = parseDiff($diffs);
echo "\n";
print_r($files);

$analysis = analyzeCodeWithChatGPT($files, $gptApiKey, $gptUrl);
echo "\nChatGPT analysis is:\n";
print_r($analysis);

$reviewId = startReview($repoFullName, $pullNumber, $githubToken);
foreach ($analysis as $file => $comment) {
    addReviewComment($repoFullName, $pullNumber, $reviewId, $comment, $file, $position, $githubToken);
}

submitReview($repoFullName, $pullNumber, $reviewId, $githubToken);

final class Highlighter
{
    function getPullRequestDiff(string $repoFullName, string $pullNumber, string $githubToken): string
    {
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $githubToken,
            'User-Agent: PHP Script',
            'Accept: application/vnd.github.v3.diff'
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            echo "Curl error: " . curl_error($ch);
            curl_close($ch);
            exit(1);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            echo "Failed to get PR differences. Status code: $statusCode\n";
            echo "Response: " . $response . "\n";
            exit(1);
        }
        echo "Successfully get PR differences.\n";

        return $response;
    }

    function analyzeCodeWithChatGPT(array $files, string $gptApiKey, string $gptUrl): array
    {
        $responses = [];
        foreach ($files as $file => $data) {
            $changesText = implode("\n", $data['changes']);
            $postData = json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        "role" => "system",
                        "content" => "You are a senior developer. 
                You will receive the code differences from pull request.
                Review the changes for potential vulnerabilities, bugs or poor design.
                You can use GitHub markdown syntax."
                    ],
                    [
                        "role" => "user",
                        "content" => "Analyze changes to $file: $changesText"
                    ],
                ],
                'temperature' => 1.0,
                'max_tokens' => 4000,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);

            //for russia use proxy
            $ch = curl_init("$gptUrl/v1/chat/completions");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $gptApiKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

            $response = curl_exec($ch);
            if ($response === false) {
                echo "Curl error: " . curl_error($ch);
                curl_close($ch);
                exit(1);
            }

            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($statusCode !== 200) {
                echo "Failed to get review from ChatGPT. Status code: $statusCode\n";
                echo "Response: " . $response . "\n";
                exit(1);
            }

            $responseArray = json_decode($response, true);
            echo "Successfully get review from ChatGPT.\n";
            $responses[$file] = $responseArray['choices'][0]['message']['content'];
        }
        return $responses;
    }

    function parseDiff(string $diff): array {
        $files = [];
        $lines = explode("\n", $diff);
        $currentFile = null;
        $currentPosition = null;

        foreach ($lines as $line) {
            if (strpos($line, 'diff --git') === 0) {
                $parts = explode(" ", $line);
                $filePath = trim($parts[2], 'a/');  // Assuming 'a/' prefix
                $currentFile = $filePath;
                $files[$currentFile] = [];
            } elseif ($currentFile && strpos($line, '@@') === 0) {
                // Parse hunk header to extract starting line number
                preg_match('/\-(\d+),\d+ \+(\d+),\d+ @@/', $line, $matches);
                $currentPosition = (int)$matches[2];  // Start line of new file
            } elseif ($currentFile) {
                if (substr($line, 0, 1) !== '-') {  // Ignore removals for positions
                    $files[$currentFile][] = [
                        'line' => $currentPosition,
                        'text' => $line,
                        'type' => substr($line, 0, 1) === '+' ? 'add' : 'context'
                    ];
                    if (substr($line, 0, 1) !== '+') {
                        $currentPosition++;  // Increase position for unchanged or added lines
                    }
                }
            }
        }

        return $files;
    }


    function startReview(string $repoFullName, string $pullNumber, string $githubToken): string {
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber/reviews";
        $data = json_encode(['event' => 'PENDING']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $githubToken,
            'User-Agent: PHP Script',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (!$response) {
            echo "Curl error: " . curl_error($ch);
            curl_close($ch);
            exit(1);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            echo "Failed to start review. HTTP status: $statusCode\n";
            exit(1);
        }

        $responseArray = json_decode($response, true);
        return $responseArray['id'];  // Review ID to use for adding comments
    }

    function addReviewComment(
        string $repoFullName,
        string $pullNumber,
        string $reviewId,
        string $body,
        string $path,
        int $position,
        string $githubToken
    ): void {
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber/reviews/$reviewId/comments";
        $data = json_encode([
            'body' => $body,
            'path' => $path,
            'position' => $position
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $githubToken,
            'User-Agent: PHP Script',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (!$response) {
            echo "Curl error: " . curl_error($ch);
            curl_close($ch);
            exit(1);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 201) {
            echo "Failed to add review comment. HTTP status: $statusCode\n";
            exit(1);
        }
    }

    function submitReview(string $repoFullName, string $pullNumber, string $reviewId, string $githubToken): void {
        $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber/reviews/$reviewId/events";
        $data = json_encode(['event' => 'COMMENT']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $githubToken,
            'User-Agent: PHP Script',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (!$response) {
            echo "Curl error: " . curl_error($ch);
            curl_close($ch);
            exit(1);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            echo "Failed to submit review. HTTP status: $statusCode\n";
            exit(1);
        }

        echo "Review submitted successfully.\n";
    }

}
