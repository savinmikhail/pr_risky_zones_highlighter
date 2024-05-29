<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

class CommentParser
{
    public function parseComment(string $commentString): array
    {
        // Use a regular expression to extract the line number and comment text
        $pattern = '/\[line (\d+)\] - (.+)/';
        if (preg_match($pattern, $commentString, $matches)) {
            return [
                'line' => (int)$matches[1], // Convert the line number to an integer
                'comment' => $matches[2]   // Extract the comment text
            ];
        }

        // If the pattern does not match, return an empty array or handle the error as needed
        return [];
    }
}