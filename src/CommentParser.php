<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

class CommentParser
{
    public function parseComments(string $comments): array
    {
        $parsedComments = [];
        $pattern = '/\[line (\d+)\] - (.+?)(?=(?:\[line \d+\] -)|$)/s';

        if (preg_match_all($pattern, $comments, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $parsedComments[(int)$match[1]] = trim($match[2]);
            }
        }

        return $parsedComments;
    }
}
