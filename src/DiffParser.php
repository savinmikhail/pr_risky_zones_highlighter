<?php

declare(strict_types=1);

namespace SavinMikhail\PrRiskHighLighter;

use SebastianBergmann\Diff\Chunk;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

use function str_starts_with;
use function substr;

use const PHP_EOL;

final readonly class DiffParser
{
    public function parseDiff(string $diff): array
    {
        $parser = new Parser();
        $diffs = $parser->parse($diff);
        $files = [];
        foreach ($diffs as $diff) {
            $currentFile = $this->normalizeFilePath($diff->to());
            $files[$currentFile] = [];

            foreach ($diff->chunks() as $chunk) {
                $this->processChunk($chunk, $files, $currentFile);
            }
        }
        return $files;
    }

    /**
     * @param Line $line
     * @param int $currentPosition
     * @param int $diffPosition
     * @param string $diffHunk
     * @param array $files
     * @param string $currentFile
     */
    public function processLine(
        Line $line,
        int &$currentPosition,
        int &$diffPosition,
        string $diffHunk,
        array &$files,
        string $currentFile
    ): void {
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

    /**
     * @param Chunk $chunk
     * @param array $files
     * @param string $currentFile
     */
    public function processChunk(Chunk $chunk, array &$files, string $currentFile): void
    {
        $currentPosition = $chunk->start();
        $diffPosition = 1; // Start from 1

        // Extract the hunk header for the diff
        $diffHunk = $this->createDiffHunkHeader($chunk);
        foreach ($chunk->lines() as $line) {
            $diffHunk .= $line->content() . PHP_EOL;
        }

        foreach ($chunk->lines() as $line) {
            $this->processLine(
                $line,
                $currentPosition,
                $diffPosition,
                $diffHunk,
                $files,
                $currentFile
            );
        }
    }

    private function normalizeFilePath(string $filePath): string
    {
        return str_starts_with($filePath, 'b/') ? substr($filePath, 2) : $filePath;
    }

    private function createDiffHunkHeader($chunk): string
    {
        return "@@ -"
            . $chunk->start()
            . ","
            . $chunk->startRange()
            . " +"
            . $chunk->end()
            . ","
            . $chunk->endRange()
            . " @@\n";
    }
}