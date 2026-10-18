<?php

namespace Tests\Unit;

use App\Services\StampPlacementService;
use PHPUnit\Framework\TestCase;

class StampPlacementServiceTest extends TestCase
{
    protected StampPlacementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StampPlacementService;
    }

    public function test_produces_mandatory_three_elements_stamp_dimensions(): void
    {
        $config = [
            'notary_name' => 'ROBERT, S.H., M.Kn.',
            'city_title' => 'NOTARIS KOTA DENPASAR',
            'color' => '#DC2626',
            'font_size_name' => 11.0,
            'font_size_city' => 9.0,
            'line_thickness' => 1.2,
            'line_length' => 180.0,
            'element_spacing' => 4.0,
            'target_pages' => 'first_page',
        ];

        $pagesData = [
            1 => ['width' => 595.28, 'height' => 841.89, 'orientation' => 'P'],
        ];

        $result = $this->service->calculatePlacements($config, $pagesData);

        $this->assertEquals('ROBERT, S.H., M.Kn.', $result['stamp_dimensions']['notary_name']);
        $this->assertEquals('NOTARIS KOTA DENPASAR', $result['stamp_dimensions']['city_title']);
        $this->assertEquals('#DC2626', $result['stamp_dimensions']['color']);
        $this->assertEquals(180.0, $result['stamp_dimensions']['width']);
        $this->assertGreaterThan(25.0, $result['stamp_dimensions']['height']);
    }

    public function test_clamps_coordinates_within_page_bounds(): void
    {
        $config = [
            'preset' => 'custom',
            'x_percent' => 99.0, // Deliberately exceeding right edge
            'y_percent' => 99.0, // Deliberately exceeding bottom edge
            'line_length' => 180.0,
            'target_pages' => 'first_page',
        ];

        $pagesData = [
            1 => ['width' => 595.28, 'height' => 841.89, 'orientation' => 'P'],
        ];

        $result = $this->service->calculatePlacements($config, $pagesData);
        $placement = $result['placements'][1];

        // x + stampWidth must be <= pageWidth - 10
        $this->assertLessThanOrEqual(round(595.28 - 10.0, 2), round($placement['x'] + 180.0, 2), 'Stempel tidak boleh melebihi batas kanan halaman.');
        // y + stampHeight must be <= pageHeight - 10
        $this->assertLessThanOrEqual(round(841.89 - 10.0, 2), round($placement['y'] + $result['stamp_dimensions']['height'], 2), 'Stempel tidak boleh melebihi batas bawah halaman.');
    }

    public function test_handles_landscape_orientation_and_custom_page_sizes(): void
    {
        $config = [
            'preset' => 'bottom_left',
            'target_pages' => 'all_pages',
        ];

        // A4 Landscape: 841.89 width x 595.28 height
        $pagesData = [
            1 => ['width' => 841.89, 'height' => 595.28, 'orientation' => 'L'],
        ];

        $result = $this->service->calculatePlacements($config, $pagesData);
        $placement = $result['placements'][1];

        $this->assertTrue($placement['is_active']);
        $this->assertGreaterThan(0, $placement['x']);
        $this->assertLessThan(841.89, $placement['x']);
        $this->assertLessThan(595.28, $placement['y'] + $result['stamp_dimensions']['height']);
    }

    public function test_respects_target_pages_selection(): void
    {
        $config = [
            'target_pages' => 'first_page',
        ];

        $pagesData = [
            1 => ['width' => 595.28, 'height' => 841.89, 'orientation' => 'P'],
            2 => ['width' => 595.28, 'height' => 841.89, 'orientation' => 'P'],
            3 => ['width' => 595.28, 'height' => 841.89, 'orientation' => 'P'],
        ];

        $result = $this->service->calculatePlacements($config, $pagesData);

        $this->assertTrue($result['placements'][1]['is_active'], 'Halaman 1 harus aktif.');
        $this->assertFalse($result['placements'][2]['is_active'], 'Halaman 2 tidak boleh aktif jika target first_page.');
        $this->assertFalse($result['placements'][3]['is_active'], 'Halaman 3 tidak boleh aktif jika target first_page.');
    }
}
