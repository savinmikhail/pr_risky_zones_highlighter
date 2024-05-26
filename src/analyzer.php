<?php

print_r($argv);

// Expected arguments: GPT API key, GitHub Token, Repository Full Name, Pull Number
if ($argc < 5) {
    echo "Insufficient arguments provided.";
    exit(1);
}

foreach ($argv as $key => $arg) {
    if (empty($arg)) {
        echo "Empty argument #" . ($key + 1) . " provided.";
        exit(1);
    }
}

$gptApiKey = $argv[1];
$githubToken = $argv[2];
$repoFullName = $argv[3];
$pullNumber = $argv[4];

// Main workflow
$diff = getPullRequestDiff($repoFullName, $pullNumber, $githubToken);
print_r($diff);
$analysis = analyzeCodeWithChatGPT($diff, $gptApiKey);
print_r($analysis);
postCommentToPullRequest($repoFullName, $pullNumber, $analysis, $githubToken);

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
    curl_close($ch);

    return $response;
}

function analyzeCodeWithChatGPT(string $code, string $gptApiKey): string
{
    $postData = json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                "role" => "system",
                "content" => "You are a senior developer. Review the code for potential vulnerabilities and bugs."
            ],
            [
                "role" => "user",
                "content" => $code
            ],
        ],
        'temperature' => 1.0,
        'max_tokens' => 4000,
        'frequency_penalty' => 0,
        'presence_penalty' => 0,
    ]);

    //for russia use proxy
    $ch = curl_init('https://api.proxyapi.ru/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $gptApiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    curl_close($ch);
    $responseArray = json_decode($response, true);

    return $responseArray['choices'][0]['message']['content'];
}

function postCommentToPullRequest(
    string $repoFullName,
    string $pullNumber,
    string $comment,
    string $githubToken
): void {
    $url = "https://api.github.com/repos/$repoFullName/issues/$pullNumber/comments";
    $data = json_encode(['body' => $comment]);

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
    if ($response === false) {
        echo "Curl error: " . curl_error($ch);
        curl_close($ch);
        exit(1);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode !== 201) {
        echo "Failed to post comment. Status code: $statusCode\n";
        echo "Response: " . $response . "\n";
        exit(1);
    }

    echo "Successfully posted comment.\n";
    print_r(json_decode($response, true));
}
