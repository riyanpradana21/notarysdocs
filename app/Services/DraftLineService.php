<?php

namespace App\Services;

class DraftLineService
{
    /**
     * Determine if a line is a document section heading (e.g. "Profil Singkat", "Pendidikan").
     * Headings must never be merged with the body text below them.
     *
     * Rules:
     * - Must start at the main left margin (xMin <= 80 pt) and not be significantly indented from the column minimum.
     * - Must be short (<=6 words, <=50 chars).
     * - Must NOT contain URLs, email, phone numbers, or punctuation that marks body text.
     * - Must match either a known heading keyword OR be Title/UPPER CASE with appropriate surrounding spacing.
     * - If the next line is also a short single-word item at the same X position, it is a list — not a heading.
     *
     * @param  array<string, mixed>       $line
     * @param  float                      $colMinX   Minimum X of the column this line belongs to
     * @param  array<string, mixed>|null  $nextLine
     * @param  array<string, mixed>|null  $prevLine
     */
    protected function isHeadingLine(array $line, float $colMinX, ?array $nextLine = null, ?array $prevLine = null): bool
    {
        $text = trim($line['text'] ?? '');
        if ($text === '') {
            return false;
        }

        // List items are never section headings
        if (! empty($line['is_list_item'])) {
            return false;
        }

        // URLs, email addresses, phone numbers, or social handles are not headings
        if (preg_match('/(@|www\.|\.com|\.id|\.net|\.org|http|github|linkedin|\+?\d{3,})/i', $text)) {
            return false;
        }

        // Section headings appear at the main document left margin (not deeply indented)
        if ((float) $line['xMin'] > 80.0) {
            return false;
        }
        if (((float) $line['xMin'] - $colMinX) > 6.0) {
            return false;
        }

        $wordCount = count(preg_split('/\s+/u', $text));
        $charLen = mb_strlen($text);

        // Headings are concise
        if ($wordCount > 6 || $charLen > 50) {
            return false;
        }

        // Headings do not end with terminal sentence punctuation
        if (preg_match('/[\.,;:–—\-]$/u', $text)) {
            return false;
        }

        // If the next line is also a short 1-2 word item at the same X position, this is a
        // vertical skill/item list — not a section heading
        if ($nextLine !== null) {
            $nextText = trim($nextLine['text'] ?? '');
            $nextWordCount = count(preg_split('/\s+/u', $nextText));
            $verticalGap = (float) ($nextLine['yMin'] - $line['yMax']);
            if ($wordCount <= 2 && $nextWordCount <= 2
                && $verticalGap <= 10.0
                && abs((float) $line['xMin'] - (float) $nextLine['xMin']) < 3.0
            ) {
                return false;
            }
        }

        // Known heading keywords (Indonesian and English)
        $headingKeywords = [
            'profil', 'pendidikan', 'pengalaman', 'kemampuan', 'keahlian', 'tentang',
            'riwayat', 'kontak', 'data pribadi', 'informasi', 'ringkasan', 'keterangan',
            'pasal', 'bab', 'ketentuan', 'akta', 'komparisi', 'tujuan', 'lampiran',
            'portofolio', 'sertifikasi', 'pengantar', 'penutup', 'pembukaan',
            'profile', 'education', 'experience', 'skills', 'summary', 'about',
            'projects', 'contact', 'article', 'section', 'chapter', 'history',
            'portfolio', 'certifications',
        ];

        $lower = mb_strtolower($text);
        foreach ($headingKeywords as $kw) {
            if ($lower === $kw
                || str_starts_with($lower, $kw.' ')
                || str_ends_with($lower, ' '.$kw)
            ) {
                return true;
            }
        }

        // ALL-CAPS short line with 2+ words (e.g. "KEMAMPUAN UTAMA", "PASAL 1", "DATA DIRI")
        if (preg_match('/^[A-Z0-9\s\/\&\+]{3,40}$/u', $text) && preg_match('/[A-Z]/', $text)) {
            if ($wordCount >= 2 || preg_match('/^(PASAL|BAB|LAMPIRAN|PENUTUP|PEMBUKAAN|KOMPARISI|PREMIS|SYARAT)/iu', $text)) {
                return true;
            }
        }

        // Title-Case short line that is visually taller than the following body text, or
        // has a significant whitespace gap before the next line
        if ($nextLine !== null) {
            $lineH = (float) ($line['yMax'] - $line['yMin']);
            $nextH = (float) ($nextLine['yMax'] - $nextLine['yMin']);
            $gap = (float) ($nextLine['yMin'] - $line['yMax']);

            if (($lineH > $nextH + 1.0) || $gap >= 6.0) {
                if (preg_match('/^[A-Z][a-zA-Z0-9\s\/\&\+]{2,40}$/u', $text) && $wordCount <= 4) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine if a line belongs to an indented bullet-list block (graphic bullet or hanging indent).
     * Graphic bullets are drawn as vector circles ~8-12 pt to the left of the text xMin and are
     * NOT captured by pdftotext, so the text xMin does not include the bullet marker.
     *
     * @param  array<string, mixed>  $line
     * @param  float                 $colMinX  Minimum X of the column this line belongs to
     */
    protected function isIndentedBulletItem(array $line, float $colMinX): bool
    {
        // Explicit text prefix detected (•, -, 1., a.) → definitely a list item
        if (! empty($line['is_list_item'])) {
            return true;
        }

        // Header area lines (name, contact) are not bullet items
        if ((float) $line['yMin'] < 130.0) {
            return false;
        }

        // Hanging indent 12–35 pt from the column minimum X → indented bullet sub-item
        $indent = (float) $line['xMin'] - $colMinX;
        if ($indent >= 12.0 && $indent <= 35.0) {
            return true;
        }

        // Secondary / tertiary multi-column body (e.g. the 2nd or 3rd skill column at x>100)
        if ($colMinX > 100.0) {
            return true;
        }

        return false;
    }

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
                    if ($ignoreHeadersFooters) {
                        if (! empty($line['is_header'])) {
                            continue;
                        }
                        // Never skip list items or body text even if located near bottom
                        if (! empty($line['is_footer']) && empty($line['is_list_item'])) {
                            continue;
                        }
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
                $hasExplicitMarker = false;

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
                    // Track whether the list marker is a text prefix (•, -, 1.) or a graphic bullet
                    if (! empty($gLine['is_list_item']) && trim($gLine['list_prefix'] ?? '') !== '') {
                        $hasExplicitMarker = true;
                    }
                }

                if ($minX === null || $minY === null || $maxY === null) {
                    continue;
                }

                // Graphic bullet circles (rendered as vector graphics, not text) sit ~10–12 pt
                // to the left of text xMin. Add extra clearance so the draft line does not
                // overlap the bullet. Only apply when the list marker is NOT a text character.
                $isBulletGroup = ! empty($group['is_bullet_list']) || $group['type'] === 'list_item';
                $effectiveOffset = ($isBulletGroup && ! $hasExplicitMarker)
                    ? $offset + 10.0
                    : $offset;

                // Place line dynamically to the left of the actual text block
                $lineX = max(4.0, $minX - $effectiveOffset);
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
     * Group lines into semantic paragraphs, list items, or section headings.
     *
     * Key rules applied for every column:
     * - Section headings always form their own isolated segment (never merged with body text below).
     * - Graphic-bullet list items (indented ≥12 pt from column minimum) are tagged `is_bullet_list`
     *   so the caller can widen the left offset to avoid overlapping the bullet circle.
     * - Continuation lines (wrapped text within the same item) are merged with their parent.
     * - Centered multi-line blocks (contact info, page titles) are NOT split by X-shift.
     * - New list markers, paragraph gaps, and significant indentation changes all trigger a new group.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{type: string, is_bullet_list: bool, lines: list<array<string, mixed>>}>
     */
    protected function groupLinesIntoParagraphs(array $lines, float $paragraphThreshold): array
    {
        if (empty($lines)) {
            return [];
        }

        // Step 1: Split lines into vertical columns when side-by-side text is detected.
        $columns = $this->splitIntoColumns($lines);

        $allGroups = [];

        foreach ($columns as $columnLines) {
            if (empty($columnLines)) {
                continue;
            }

            // The leftmost X in this column defines the margin for heading/indent detection
            $colMinX = min(array_map(fn ($l) => (float) $l['xMin'], $columnLines));

            // Sort column lines top to bottom
            usort($columnLines, fn ($a, $b) => $a['yMin'] <=> $b['yMin']);

            $firstIsHeading = $this->isHeadingLine($columnLines[0], $colMinX, $columnLines[1] ?? null);
            $firstIsBullet = $this->isIndentedBulletItem($columnLines[0], $colMinX);

            $currentGroup = [
                'type' => $firstIsBullet ? 'list_item' : ($firstIsHeading ? 'heading' : 'paragraph'),
                'is_bullet_list' => $firstIsBullet && empty($columnLines[0]['is_list_item']),
                'lines' => [$columnLines[0]],
                'column_min_x' => $colMinX,
            ];

            $prevLine = $columnLines[0];
            $prevIsHeading = $firstIsHeading;

            for ($i = 1; $i < count($columnLines); $i++) {
                $currLine = $columnLines[$i];
                $nextLine = $columnLines[$i + 1] ?? null;

                $prevHeight = max(8.0, (float) ($prevLine['yMax'] - $prevLine['yMin']));
                $verticalGap = (float) ($currLine['yMin'] - $prevLine['yMax']);

                // Check if current line starts a new explicit list item
                $isNewListItem = ! empty($currLine['is_list_item']);

                // If previous line was a list item and current line is not, it is continuation text
                $isListContinuation = (! empty($prevLine['is_list_item']) && ! $isNewListItem);

                // Centered lines (e.g. contact info spanning full width) should NOT trigger an
                // indent-break even when xMin shifts, because centering changes xMin naturally.
                $prevCenter = ((float) $prevLine['xMin'] + (float) $prevLine['xMax']) / 2.0;
                $currCenter = ((float) $currLine['xMin'] + (float) $currLine['xMax']) / 2.0;
                $linesAreCentered = (abs($prevCenter - $currCenter) < 5.0);

                // Indentation shift (> 12 pt) indicates a sub-block — only when lines are NOT centered
                $indentDiff = (float) abs($currLine['xMin'] - $prevLine['xMin']);
                $isSignificantIndent = (! $linesAreCentered && $indentDiff > 12.0 && $verticalGap > 2.0);

                // Paragraph gap threshold: if verticalGap > prevHeight * threshold
                $isParagraphBreak = ($verticalGap > ($prevHeight * $paragraphThreshold));

                // Negative gap indicates overlapping or backward jump (should not merge)
                $isOverlapping = ($verticalGap < -2.0);

                // Heading break: headings must always be their own isolated segment.
                // If the PREVIOUS line was a heading, close it now.
                // If the CURRENT line is a heading, also close the current group.
                $currIsHeading = $this->isHeadingLine($currLine, $colMinX, $nextLine, $prevLine);
                $isHeadingBreak = $prevIsHeading || $currIsHeading;

                if ($isHeadingBreak || $isNewListItem || $isListContinuation || $isSignificantIndent || $isParagraphBreak || $isOverlapping) {
                    $allGroups[] = $currentGroup;

                    $currIsBullet = $this->isIndentedBulletItem($currLine, $colMinX);

                    $currentGroup = [
                        'type' => $currIsBullet ? 'list_item' : ($currIsHeading ? 'heading' : ($isListContinuation ? 'list_continuation' : 'paragraph')),
                        'is_bullet_list' => $currIsBullet && empty($currLine['is_list_item']),
                        'lines' => [$currLine],
                        'column_min_x' => $colMinX,
                    ];
                    $prevIsHeading = $currIsHeading;
                } else {
                    $currentGroup['lines'][] = $currLine;
                }

                $prevLine = $currLine;
            }

            if (! empty($currentGroup['lines'])) {
                $allGroups[] = $currentGroup;
            }
        }

        // Sort all groups by their first line's Y position for consistent rendering
        usort($allGroups, function ($a, $b) {
            $aY = $a['lines'][0]['yMin'] ?? 0;
            $bY = $b['lines'][0]['yMin'] ?? 0;

            if (abs($aY - $bY) > 3.0) {
                return $aY <=> $bY;
            }

            $aX = $a['lines'][0]['xMin'] ?? 0;
            $bX = $b['lines'][0]['xMin'] ?? 0;

            return $aX <=> $bX;
        });

        return $allGroups;
    }

    /**
     * Split lines into vertical columns by analysing true side-by-side text (vertical overlap).
     *
     * Only lines that genuinely share the same vertical row (vOverlap > 3 pt) AND whose
     * xMin values differ by more than 45 pt are considered to belong to separate columns.
     *
     * We deliberately do NOT use an X-gap heuristic here, because that would incorrectly
     * split centered multi-line blocks (page title, contact info) into separate columns.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<list<array<string, mixed>>>
     */
    protected function splitIntoColumns(array $lines): array
    {
        if (count($lines) <= 1) {
            return [$lines];
        }

        // Detect column separators from lines that visually appear side-by-side
        $columnSeparators = [];
        $n = count($lines);

        for ($i = 0; $i < $n; $i++) {
            $a = $lines[$i];
            for ($j = $i + 1; $j < $n; $j++) {
                $b = $lines[$j];

                // Vertical overlap: both lines exist in the same horizontal band
                $vOverlap = min((float) $a['yMax'], (float) $b['yMax']) - max((float) $a['yMin'], (float) $b['yMin']);
                if ($vOverlap > 3.0) {
                    $xDiff = abs((float) $a['xMin'] - (float) $b['xMin']);
                    if ($xDiff > 45.0) {
                        $left = min((float) $a['xMax'], (float) $b['xMax']);
                        $right = max((float) $a['xMin'], (float) $b['xMin']);
                        if ($right > $left) {
                            $columnSeparators[] = ($left + $right) / 2.0;
                        } else {
                            $columnSeparators[] = ((float) min($a['xMin'], $b['xMin']) + (float) max($a['xMin'], $b['xMin'])) / 2.0;
                        }
                    }
                }
            }
        }

        if (empty($columnSeparators)) {
            return [$lines];
        }

        sort($columnSeparators);

        // Deduplicate separators that are within 30 pt of each other
        $uniqueBreaks = [];
        foreach ($columnSeparators as $sep) {
            if (empty($uniqueBreaks) || ($sep - end($uniqueBreaks)) > 30.0) {
                $uniqueBreaks[] = $sep;
            }
        }

        // Assign each line to a column based on its xMin relative to breakpoints
        $numColumns = count($uniqueBreaks) + 1;
        $columns = array_fill(0, $numColumns, []);

        foreach ($lines as $line) {
            $x = (float) $line['xMin'];
            $colIndex = 0;
            foreach ($uniqueBreaks as $bp) {
                if ($x > $bp) {
                    $colIndex++;
                }
            }
            $columns[$colIndex][] = $line;
        }

        // Filter out empty columns
        return array_values(array_filter($columns, fn ($col) => ! empty($col)));
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
