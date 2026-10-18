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
}
