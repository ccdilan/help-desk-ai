<?php

namespace App\Services\Ai;

/**
 * Heuristic structural chunker for PHP / JS / TS / Vue sources.
 *
 * Files are split at logical declaration boundaries (classes, methods,
 * functions, top-level constants, Vue SFC blocks). Very small segments are
 * merged with their predecessor and oversized segments are split into
 * overlapping line windows.
 */
class CodeChunker
{
    /**
     * Segments smaller than this many lines are merged into the previous one.
     */
    private const MIN_LINES = 6;

    /**
     * Window size and overlap used when splitting oversized segments.
     */
    private const WINDOW_LINES = 100;

    private const WINDOW_OVERLAP = 15;

    /**
     * Split source code into logical chunks.
     *
     * @return list<string>
     */
    public function chunk(string $content): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];

        if ($lines === [] || trim($content) === '') {
            return [];
        }

        $boundaries = $this->boundaries($lines);
        $segments = $this->segments($lines, $boundaries);

        return array_values(array_filter(
            array_map(fn (array $segment): string => trim(implode("\n", $segment)), $segments),
            fn (string $chunk): bool => $chunk !== '',
        ));
    }

    /**
     * Determine the starting line indices of logical blocks.
     *
     * @param  list<string>  $lines
     * @return list<int>
     */
    private function boundaries(array $lines): array
    {
        $patterns = [
            '#^(abstract\s+|final\s+|readonly\s+)?(class|interface|trait|enum)\s#',
            '#^\s*(public|protected|private)\s+(static\s+)?(readonly\s+)?function\s#',
            '#^function\s#',
            '#^(export\s+)?(default\s+)?(async\s+)?(function|class)\s#',
            '#^(export\s+)?(const|let|var)\s+\w+\s*=\s*(async\s+)?(function|\()#',
            '#^<script|^<template|^<style#',
        ];

        $boundaries = [0];

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line) === 1) {
                    $boundaries[] = $index;

                    break;
                }
            }
        }

        return array_values(array_unique($boundaries));
    }

    /**
     * Slice lines into segments and normalize their sizes.
     *
     * @param  list<string>  $lines
     * @param  list<int>  $boundaries
     * @return list<list<string>>
     */
    private function segments(array $lines, array $boundaries): array
    {
        $raw = [];

        for ($i = 0; $i < count($boundaries); $i++) {
            $start = $boundaries[$i];
            $end = ($boundaries[$i + 1] ?? count($lines)) - 1;
            $raw[] = array_slice($lines, $start, $end - $start + 1);
        }

        $merged = [];

        foreach ($raw as $segment) {
            $isSmall = count($segment) < self::MIN_LINES;
            $isSfcBlock = str_starts_with((string) $segment[0], '<');

            if ($isSmall && ! $isSfcBlock && $merged !== []) {
                $merged[count($merged) - 1] = [
                    ...$merged[count($merged) - 1],
                    ...$segment,
                ];

                continue;
            }

            $merged[] = $segment;
        }

        $result = [];

        foreach ($merged as $segment) {
            if (count($segment) <= self::WINDOW_LINES) {
                $result[] = $segment;

                continue;
            }

            for ($offset = 0; $offset < count($segment); $offset += self::WINDOW_LINES - self::WINDOW_OVERLAP) {
                $window = array_slice($segment, $offset, self::WINDOW_LINES);

                if ($window !== []) {
                    $result[] = $window;
                }

                if ($offset + self::WINDOW_LINES >= count($segment)) {
                    break;
                }
            }
        }

        return $result;
    }
}
