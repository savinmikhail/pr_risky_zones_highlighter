<?php

$gptApiKey = $argv[1];
file_put_contents('comment.txt', "Risky zones identified...");

function getPullRequestDiff($repoFullName, $pullNumber, $githubToken) {
    $url = "https://api.github.com/repos/$repoFullName/pulls/$pullNumber";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $githubToken,
        'User-Agent: PHP Script'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function analyzeCodeWithChatGPT($code, $gptApiKey) {
    $postData = json_encode(['prompt' => "Analyze this code: $code", 'max_tokens' => 150]);

    $ch = curl_init('https://api.openai.com/v1/engines/chatgpt/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $gptApiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function postCommentToPullRequest($repoFullName, $pullNumber, $comment, $githubToken) {
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
    curl_close($ch);
    return $response;
}

