<?php

namespace Tests\Unit;

use App\Services\DraftLineService;
use PHPUnit\Framework\TestCase;

class DraftLineServiceTest extends TestCase
{
    protected DraftLineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DraftLineService;
    }

    public function test_resolves_active_pages_correctly(): void
    {
        $all = $this->service->resolveActivePages('all_pages', '', 5);
        $this->assertEquals([1, 2, 3, 4, 5], $all);

        $first = $this->service->resolveActivePages('first_page', '', 5);
        $this->assertEquals([1], $first);

        $custom = $this->service->resolveActivePages('custom', '1, 3, 4-5', 5);
        $this->assertEquals([1, 3, 4, 5], $custom);
    }

    public function test_groups_multiline_paragraphs_into_single_line_segment(): void
    {
        $layoutData = [
            'pages' => [
                1 => [
                    'page_number' => 1,
                    'width' => 595.28,
                    'height' => 841.89,
                    'blocks' => [
                        [
                            'lines' => [
                                ['xMin' => 50.0, 'yMin' => 100.0, 'xMax' => 500.0, 'yMax' => 112.0, 'text' => 'Baris pertama paragraf pembuka akta notaris.', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 50.0, 'yMin' => 115.0, 'xMax' => 480.0, 'yMax' => 127.0, 'text' => 'Baris kedua masih dalam paragraf yang sama.', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 50.0, 'yMin' => 130.0, 'xMax' => 420.0, 'yMax' => 142.0, 'text' => 'Baris ketiga penutup kalimat paragraf ini.', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $config = [
            'enabled' => true,
            'color' => '#DC2626',
            'thickness' => 1.2,
            'offset' => 8.0,
            'paragraph_threshold' => 1.6,
            'target_pages' => 'all_pages',
        ];

        $result = $this->service->calculateDraftLines($layoutData, $config);

        $this->assertArrayHasKey(1, $result);
        $segments = $result[1]['segments'];
        $this->assertCount(1, $segments, 'Tiga baris dalam paragraf yang sama harus digabung menjadi satu segmen garis.');

        $seg = $segments[0];
        $this->assertEquals(42.0, $seg['x'], 'X garis harus berada di xMin - offset (50 - 8 = 42).');
        $this->assertEquals(3, $seg['line_count']);
        $this->assertLessThanOrEqual(100.0, $seg['y_start']);
        $this->assertGreaterThanOrEqual(142.0, $seg['y_end']);
    }

    public function test_breaks_lines_between_distinct_paragraphs_and_numbered_items(): void
    {
        $layoutData = [
            'pages' => [
                1 => [
                    'page_number' => 1,
                    'width' => 595.28,
                    'height' => 841.89,
                    'blocks' => [
                        [
                            'lines' => [
                                ['xMin' => 50.0, 'yMin' => 100.0, 'xMax' => 500.0, 'yMax' => 112.0, 'text' => 'Paragraf pembuka.', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                                // Large gap -> separate paragraph
                                ['xMin' => 50.0, 'yMin' => 160.0, 'xMax' => 450.0, 'yMax' => 172.0, 'text' => '1. Tuan A, berdomisili di Denpasar.', 'is_list_item' => true, 'is_header' => false, 'is_footer' => false],
                                // Numbered item 2 -> separate segment
                                ['xMin' => 50.0, 'yMin' => 180.0, 'xMax' => 450.0, 'yMax' => 192.0, 'text' => '2. Nyonya B, berdomisili di Denpasar.', 'is_list_item' => true, 'is_header' => false, 'is_footer' => false],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $config = [
            'enabled' => true,
            'offset' => 8.0,
            'paragraph_threshold' => 1.5,
            'target_pages' => 'all_pages',
        ];

        $result = $this->service->calculateDraftLines($layoutData, $config);
        $segments = $result[1]['segments'];

        $this->assertCount(3, $segments, 'Paragraf pembuka dan dua item bernomor harus dipisah menjadi 3 segmen garis terpisah.');
        $this->assertEquals('paragraph', $segments[0]['type']);
        $this->assertEquals('list_item', $segments[1]['type']);
        $this->assertEquals('list_item', $segments[2]['type']);
    }

    public function test_excludes_headers_and_footers_when_configured(): void
    {
        $layoutData = [
            'pages' => [
                1 => [
                    'page_number' => 1,
                    'width' => 595.28,
                    'height' => 841.89,
                    'blocks' => [
                        [
                            'lines' => [
                                ['xMin' => 500.0, 'yMin' => 30.0, 'xMax' => 550.0, 'yMax' => 40.0, 'text' => 'Halaman 1', 'is_list_item' => false, 'is_header' => true, 'is_footer' => false],
                                ['xMin' => 50.0, 'yMin' => 100.0, 'xMax' => 500.0, 'yMax' => 112.0, 'text' => 'Isi akta notaris sebenarnya.', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 250.0, 'yMin' => 820.0, 'xMax' => 350.0, 'yMax' => 830.0, 'text' => 'Paraf Notaris', 'is_list_item' => false, 'is_header' => false, 'is_footer' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $config = [
            'enabled' => true,
            'ignore_headers_footers' => true,
            'target_pages' => 'all_pages',
        ];

        $result = $this->service->calculateDraftLines($layoutData, $config);
        $segments = $result[1]['segments'];

        $this->assertCount(1, $segments, 'Header dan footer harus diabaikan sehingga hanya ada 1 segmen garis.');
        $this->assertEquals('Isi akta notaris sebenarnya.', $segments[0]['text_preview']);
    }

    public function test_does_not_exclude_list_items_at_bottom_of_page(): void
    {
        $layoutData = [
            'pages' => [
                1 => [
                    'page_number' => 1,
                    'width' => 595.28,
                    'height' => 841.89,
                    'blocks' => [
                        [
                            'lines' => [
                                ['xMin' => 60.0, 'yMin' => 700.0, 'xMax' => 150.0, 'yMax' => 712.0, 'text' => '• HTML', 'is_list_item' => true, 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 60.0, 'yMin' => 795.0, 'xMax' => 150.0, 'yMax' => 807.0, 'text' => '• CSS', 'is_list_item' => true, 'is_header' => false, 'is_footer' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $config = [
            'enabled' => true,
            'ignore_headers_footers' => true,
            'target_pages' => 'all_pages',
        ];

        $result = $this->service->calculateDraftLines($layoutData, $config);
        $segments = $result[1]['segments'];

        $this->assertCount(2, $segments, 'Item list di bagian bawah halaman seperti CSS tidak boleh diabaikan.');
    }

    public function test_does_not_draw_lines_on_blank_page(): void
    {
        $layoutData = [
            'pages' => [
                1 => [
                    'page_number' => 1,
                    'width' => 595.28,
                    'height' => 841.89,
                    'blocks' => [],
                ],
            ],
        ];

        $config = ['enabled' => true, 'target_pages' => 'all_pages'];
        $result = $this->service->calculateDraftLines($layoutData, $config);

        $this->assertEmpty($result[1]['segments'], 'Halaman kosong tidak boleh memiliki segmen garis draf.');
    }

    public function test_multi_column_layout_produces_separate_segments(): void
    {
        $layoutData = [
            'pages' => [
                1 => [
                    'page_number' => 1,
                    'width' => 595.28,
                    'height' => 841.89,
                    'blocks' => [
                        [
                            'lines' => [
                                // Column 1
                                ['xMin' => 50.0, 'yMin' => 100.0, 'xMax' => 200.0, 'yMax' => 112.0, 'text' => 'Next JS', 'is_list_item' => true, 'list_prefix' => '•', 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 50.0, 'yMin' => 115.0, 'xMax' => 200.0, 'yMax' => 127.0, 'text' => 'Python', 'is_list_item' => true, 'list_prefix' => '•', 'is_header' => false, 'is_footer' => false],
                                // Column 2 (different xMin, similar yMin)
                                ['xMin' => 250.0, 'yMin' => 100.0, 'xMax' => 400.0, 'yMax' => 112.0, 'text' => 'Laravel', 'is_list_item' => true, 'list_prefix' => '•', 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 250.0, 'yMin' => 115.0, 'xMax' => 400.0, 'yMax' => 127.0, 'text' => 'Java', 'is_list_item' => true, 'list_prefix' => '•', 'is_header' => false, 'is_footer' => false],
                                // Column 3
                                ['xMin' => 450.0, 'yMin' => 100.0, 'xMax' => 580.0, 'yMax' => 112.0, 'text' => 'JavaScript', 'is_list_item' => true, 'list_prefix' => '•', 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 450.0, 'yMin' => 115.0, 'xMax' => 580.0, 'yMax' => 127.0, 'text' => 'Linux', 'is_list_item' => true, 'list_prefix' => '•', 'is_header' => false, 'is_footer' => false],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $config = [
            'enabled' => true,
            'offset' => 8.0,
            'paragraph_threshold' => 1.6,
            'target_pages' => 'all_pages',
        ];

        $result = $this->service->calculateDraftLines($layoutData, $config);
        $segments = $result[1]['segments'];

        // Each bullet item in separate columns should produce separate segments (6 items minimum)
        $this->assertGreaterThanOrEqual(6, count($segments), 'Multi-column list items must each have their own draft line segment.');
    }

    public function test_wrapped_list_item_groups_continuation_lines(): void
    {
        $layoutData = [
            'pages' => [
                1 => [
                    'page_number' => 1,
                    'width' => 595.28,
                    'height' => 841.89,
                    'blocks' => [
                        [
                            'lines' => [
                                // Item 1 with text wrapping to next line
                                ['xMin' => 80.0, 'yMin' => 100.0, 'xMax' => 500.0, 'yMax' => 112.0, 'text' => '1. Nona NI NYOMAN AYU ANGGARINA ATMASARI, lahir di', 'is_list_item' => true, 'list_prefix' => '1.', 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 80.0, 'yMin' => 115.0, 'xMax' => 500.0, 'yMax' => 127.0, 'text' => 'Denpasar, pada tanggal 29-07-1997', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 80.0, 'yMin' => 130.0, 'xMax' => 500.0, 'yMax' => 142.0, 'text' => 'bertempat tinggal di Banjar Pasek.', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                                // Item 2
                                ['xMin' => 80.0, 'yMin' => 150.0, 'xMax' => 500.0, 'yMax' => 162.0, 'text' => '2. Tuan I GUSTI NGURAH AGUNG KRISNA DARMADI, lahir', 'is_list_item' => true, 'list_prefix' => '2.', 'is_header' => false, 'is_footer' => false],
                                ['xMin' => 80.0, 'yMin' => 165.0, 'xMax' => 500.0, 'yMax' => 177.0, 'text' => 'di Denpasar, pada tanggal 19-07-1997.', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $config = [
            'enabled' => true,
            'offset' => 8.0,
            'paragraph_threshold' => 1.6,
            'target_pages' => 'all_pages',
        ];

        $result = $this->service->calculateDraftLines($layoutData, $config);
        $segments = $result[1]['segments'];

        // Per user request: list item header and continuation text below it get separate draft lines
        $this->assertCount(4, $segments, 'Two numbered items with text below them should produce 4 draft line segments (header + continuation for each).');
        $this->assertEquals('list_item', $segments[0]['type']);
        $this->assertEquals(1, $segments[0]['line_count'], 'First item header is 1 line.');
        $this->assertEquals('list_continuation', $segments[1]['type']);
        $this->assertEquals(2, $segments[1]['line_count'], 'Text below first item has 2 continuation lines.');
        $this->assertEquals('list_item', $segments[2]['type']);
        $this->assertEquals(1, $segments[2]['line_count'], 'Second item header is 1 line.');
        $this->assertEquals('list_continuation', $segments[3]['type']);
        $this->assertEquals(1, $segments[3]['line_count'], 'Text below second item has 1 continuation line.');
    }
}
