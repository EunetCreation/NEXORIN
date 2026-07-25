<?php

namespace Tests\Unit;

use App\Services\Sld\SldSmartRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SldSmartRouterTest extends TestCase
{
    private SldSmartRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = app(SldSmartRouter::class);
    }

    public function test_router_produces_a_deterministic_orthogonal_obstacle_free_path(): void
    {
        $source = ['id' => 'source', 'x' => 20, 'y' => 60, 'side' => 'right'];
        $target = ['id' => 'target', 'x' => 220, 'y' => 60, 'side' => 'left'];
        $obstacles = [['id' => 'middle', 'left' => 90, 'top' => 20, 'right' => 150, 'bottom' => 100]];

        $first = $this->router->route($source, $target, $obstacles, [], 'ac_power', 18, 12, 10);
        $second = $this->router->route($source, $target, $obstacles, [], 'ac_power', 18, 12, 10);

        self::assertSame($first, $second);
        self::assertSame(SldSmartRouter::VERSION, $first['version']);
        self::assertSame(SldSmartRouter::CONTRACT, $first['contract']);
        self::assertSame(SldSmartRouter::ENGINE, $first['strategy']);
        self::assertTrue($first['orthogonal']);
        self::assertTrue($first['terminal_direction_ok']);
        self::assertMatchesRegularExpression('/^rt-[0-9a-f]{8}$/', $first['signature']);
        self::assertTrue($first['obstacle_free']);
        self::assertSame(0, $first['clearance_conflicts']);
        self::assertGreaterThanOrEqual(2, $first['bends']);
        $this->assertOrthogonal($first['points']);
        self::assertFalse($this->intersectsExpandedRect($first['points'], $obstacles[0], 18));
    }

    public function test_router_avoids_crossing_an_existing_wire_when_a_detour_exists(): void
    {
        $existing = [[
            'a' => ['x' => 110, 'y' => 20],
            'b' => ['x' => 110, 'y' => 100],
            'network' => 'communication',
            'wireId' => 'COM-01',
        ]];

        $route = $this->router->route(
            ['id' => 'source', 'x' => 20, 'y' => 60, 'side' => 'right'],
            ['id' => 'target', 'x' => 220, 'y' => 60, 'side' => 'left'],
            [],
            $existing,
            'ac_power',
            12,
            12,
            10,
        );

        self::assertSame(0, $route['crossings']);
        self::assertGreaterThan(200, $route['length']);
        $this->assertOrthogonal($route['points']);
    }

    #[DataProvider('clearanceProfiles')]
    public function test_router_respects_each_clearance_profile(float $clearance, float $spacing): void
    {
        $obstacle = ['id' => 'middle', 'left' => 90, 'top' => 20, 'right' => 150, 'bottom' => 100];
        $route = $this->router->route(
            ['id' => 'source', 'x' => 20, 'y' => 60, 'side' => 'right'],
            ['id' => 'target', 'x' => 220, 'y' => 60, 'side' => 'left'],
            [$obstacle],
            [],
            'ac_power',
            $clearance,
            $spacing,
            10,
        );

        self::assertFalse($this->intersectsExpandedRect($route['points'], $obstacle, $clearance));
        self::assertSame(0, $route['clearance_conflicts']);
    }

    public static function clearanceProfiles(): array
    {
        return [
            'compact' => [12.0, 9.0],
            'standard' => [18.0, 12.0],
            'generous' => [26.0, 16.0],
        ];
    }

    public function test_impossible_corridor_uses_a_visible_fallback_and_reports_the_conflict(): void
    {
        $route = $this->router->route(
            ['id' => 'source', 'x' => 20, 'y' => 60, 'side' => 'right'],
            ['id' => 'target', 'x' => 220, 'y' => 60, 'side' => 'left'],
            [['id' => 'wall', 'left' => 40, 'top' => -500, 'right' => 200, 'bottom' => 500]],
            [],
            'ac_power',
            12,
            12,
            10,
        );

        self::assertSame('perimeter-fallback', $route['strategy']);
        self::assertFalse($route['obstacle_free']);
        self::assertGreaterThan(0, $route['clearance_conflicts']);
        $this->assertOrthogonal($route['points']);
    }

    private function assertOrthogonal(array $points): void
    {
        self::assertGreaterThanOrEqual(2, count($points));
        for ($index = 0; $index < count($points) - 1; $index++) {
            $a = $points[$index];
            $b = $points[$index + 1];
            self::assertTrue(
                abs((float) $a['x'] - (float) $b['x']) < .001
                || abs((float) $a['y'] - (float) $b['y']) < .001,
                "Segment {$index} is diagonal.",
            );
        }
    }

    private function intersectsExpandedRect(array $points, array $rect, float $clearance): bool
    {
        $left = (float) $rect['left'] - $clearance;
        $right = (float) $rect['right'] + $clearance;
        $top = (float) $rect['top'] - $clearance;
        $bottom = (float) $rect['bottom'] + $clearance;

        for ($index = 0; $index < count($points) - 1; $index++) {
            $a = $points[$index];
            $b = $points[$index + 1];
            if (abs((float) $a['x'] - (float) $b['x']) < .001) {
                $x = (float) $a['x'];
                if ($x <= $left + .001 || $x >= $right - .001) continue;
                if (max(min((float) $a['y'], (float) $b['y']), $top) < min(max((float) $a['y'], (float) $b['y']), $bottom) - .001) return true;
            } elseif (abs((float) $a['y'] - (float) $b['y']) < .001) {
                $y = (float) $a['y'];
                if ($y <= $top + .001 || $y >= $bottom - .001) continue;
                if (max(min((float) $a['x'], (float) $b['x']), $left) < min(max((float) $a['x'], (float) $b['x']), $right) - .001) return true;
            } else {
                return true;
            }
        }

        return false;
    }
}
