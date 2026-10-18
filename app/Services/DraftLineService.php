<?php

namespace App\Services;

class DraftLineService
{
    /**
     * Calculate dynamic vertical draft lines for each target page based on text layout.
     *
     * @param  array{pages: array<int, array>}  $layoutData
     * @param  array<string, mixed>  $config
     * @return array<int, array{
     *     page_number: int,
     *     width: float,
     *     height: float,
     *     is_enabled: bool,
     *     segments: array<int, array{
     *         id: string,
     *         x: float,
     *         y_start: float,
     *         y_end: float,
     *         line_count: int,
     *         text_preview: string,
     *         type: string
     *     }>
     * }>
     */
    public function calculateDraftLines(array $layoutData, array $config): array
    {
        $enabled = (bool) ($config['enabled'] ?? true);
        if (! $enabled || empty($layoutData['pages'])) {
            return [];
        }

        $totalPages = count($layoutData['pages']);
        $targetPagesRule = $config['target_pages'] ?? 'all_pages';
        $customPagesStr = $config['custom_pages'] ?? '';
        $activePages = $this->resolveActivePages($targetPagesRule, $customPagesStr, $totalPages);

        $offset = (float) ($config['offset'] ?? 8.0);
        $verticalPadding = (float) ($config['padding_vertical'] ?? 1.0);
        $paragraphThreshold = (float) ($config['paragraph_threshold'] ?? 1.6);
        $ignoreHeadersFooters = (bool) ($config['ignore_headers_footers'] ?? true);

        $result = [];

        foreach ($layoutData['pages'] as $pageNo => $pageData) {
            $isPageActive = in_array((int) $pageNo, $activePages, true);

            $pageWidth = (float) ($pageData['width'] ?? 595.28);
            $pageHeight = (float) ($pageData['height'] ?? 841.89);

            if (! $isPageActive) {
                $result[$pageNo] = [
                    'page_number' => (int) $pageNo,
                    'width' => $pageWidth,
                    'height' => $pageHeight,
                    'is_enabled' => false,
                    'segments' => [],
                ];

                continue;
            }

            // Extract valid lines for drafting
            $lines = [];
            foreach ($pageData['blocks'] ?? [] as $block) {
                foreach ($block['lines'] ?? [] as $line) {
                    if ($ignoreHeadersFooters && (! empty($line['is_header']) || ! empty($line['is_footer']))) {
                        continue;
                    }
                    if (trim($line['text'] ?? '') === '') {
                        continue;
                    }
                    $lines[] = $line;
                }
            }

            // Sort lines top to bottom, then left to right
            usort($lines, function ($a, $b) {
                if (abs($a['yMin'] - $b['yMin']) > 3.0) {
                    return ($a['yMin'] < $b['yMin']) ? -1 : 1;
                }

                return ($a['xMin'] < $b['xMin']) ? -1 : 1;
            });

            // Group lines into paragraphs / list items
            $groups = $this->groupLinesIntoParagraphs($lines, $paragraphThreshold);

            // Generate draft line segments
            $segments = [];
            $segIndex = 0;

            foreach ($groups as $group) {
                if (empty($group['lines'])) {
                    continue;
                }

                $minX = null;
                $minY = null;
                $maxY = null;
                $firstText = '';

                foreach ($group['lines'] as $gLine) {
                    $lX = (float) $gLine['xMin'];
                    $lY1 = (float) $gLine['yMin'];
                    $lY2 = (float) $gLine['yMax'];

                    if ($minX === null || $lX < $minX) {
                        $minX = $lX;
                    }
                    if ($minY === null || $lY1 < $minY) {
                        $minY = $lY1;
                    }
                    if ($maxY === null || $lY2 > $maxY) {
                        $maxY = $lY2;
                    }
                    if ($firstText === '') {
                        $firstText = $gLine['text'];
                    }
                }

                if ($minX === null || $minY === null || $maxY === null) {
                    continue;
                }

                // Place line to the left of the text block
                $lineX = max(4.0, $minX - $offset);
                $lineYStart = max(0.0, $minY - $verticalPadding);
                $lineYEnd = min($pageHeight, $maxY + $verticalPadding);

                // Sanity check: do not draw line if start >= end
                if ($lineYEnd - $lineYStart < 2.0) {
                    continue;
                }

                $segments[] = [
                    'id' => "p{$pageNo}_seg_{$segIndex}",
                    'x' => round($lineX, 2),
                    'y_start' => round($lineYStart, 2),
                    'y_end' => round($lineYEnd, 2),
                    'line_count' => count($group['lines']),
                    'text_preview' => mb_substr($firstText, 0, 45).(mb_strlen($firstText) > 45 ? '...' : ''),
                    'type' => $group['type'],
                ];

                $segIndex++;
            }

            $result[$pageNo] = [
                'page_number' => (int) $pageNo,
                'width' => $pageWidth,
                'height' => $pageHeight,
                'is_enabled' => true,
                'segments' => $segments,
            ];
        }

        return $result;
    }

    /**
     * Group lines into semantic paragraphs or list items based on vertical proximity,
     * horizontal alignment / indentation, and list prefixes.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{type: string, lines: list<array<string, mixed>>}>
     */
    protected function groupLinesIntoParagraphs(array $lines, float $paragraphThreshold): array
    {
        if (empty($lines)) {
            return [];
        }

        $groups = [];
        $currentGroup = [
            'type' => $lines[0]['is_list_item'] ? 'list_item' : 'paragraph',
            'lines' => [$lines[0]],
        ];

        $prevLine = $lines[0];

        for ($i = 1; $i < count($lines); $i++) {
            $currLine = $lines[$i];

            $prevHeight = max(8.0, (float) ($prevLine['yMax'] - $prevLine['yMin']));
            $verticalGap = (float) ($currLine['yMin'] - $prevLine['yMax']);

            // Indentation difference
            $indentDiff = (float) abs($currLine['xMin'] - $prevLine['xMin']);

            // Check if current line starts a new list item
            $isNewListItem = ! empty($currLine['is_list_item']);

            // Paragraph gap threshold: if verticalGap > prevHeight * threshold
            $isParagraphBreak = ($verticalGap > ($prevHeight * $paragraphThreshold));

            // Significant indentation shift (> 25 pt) indicates a sub-list or separate column
            $isSignificantIndent = ($indentDiff > 25.0 && $verticalGap > 2.0);

            // Negative gap or big horizontal jump (multi-column or side-by-side text)
            $isNewColumn = ($verticalGap < -2.0);

            if ($isNewListItem || $isParagraphBreak || $isSignificantIndent || $isNewColumn) {
                // Close current group and start new group
                $groups[] = $currentGroup;
                $currentGroup = [
                    'type' => $isNewListItem ? 'list_item' : 'paragraph',
                    'lines' => [$currLine],
                ];
            } else {
                // Continuation of existing group
                $currentGroup['lines'][] = $currLine;
            }

            $prevLine = $currLine;
        }

        if (! empty($currentGroup['lines'])) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    /**
     * Resolve target page numbers from rules and custom comma/range string.
     *
     * @return list<int>
     */
    public function resolveActivePages(string $rule, string $customStr, int $totalPages): array
    {
        if ($totalPages <= 0) {
            return [];
        }

        if ($rule === 'all_pages') {
            return range(1, $totalPages);
        }

        if ($rule === 'first_page') {
            return [1];
        }

        if ($rule === 'custom') {
            return $this->parseCustomPageString($customStr, $totalPages);
        }

        return range(1, $totalPages);
    }

    /**
     * Parse page range string (e.g. "1, 3, 5-8, 10").
     *
     * @return list<int>
     */
    public function parseCustomPageString(string $customStr, int $totalPages): array
    {
        $customStr = trim($customStr);
        if ($customStr === '') {
            return range(1, $totalPages);
        }

        $pages = [];
        $parts = explode(',', $customStr);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (strpos($part, '-') !== false) {
                $range = explode('-', $part, 2);
                $start = (int) trim($range[0]);
                $end = (int) trim($range[1]);

                if ($start > 0 && $end >= $start) {
                    for ($p = $start; $p <= min($end, $totalPages); $p++) {
                        $pages[] = $p;
                    }
                }
            } else {
                $p = (int) $part;
                if ($p >= 1 && $p <= $totalPages) {
                    $pages[] = $p;
                }
            }
        }

        $pages = array_values(array_unique($pages));
        sort($pages);

        return empty($pages) ? [1] : $pages;
    }
}
