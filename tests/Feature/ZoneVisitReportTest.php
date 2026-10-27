<?php

namespace Tests\Feature;

use App\Models\LocationLog;
use App\Models\Representative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneVisitReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_a_zone_visit(): void
    {
        $representative = Representative::create([
            'name' => 'Zone Test Representative',
            'employee_code' => 'ZONE001',
        ]);

        foreach ([0, 5, 10, 15, 20] as $minutes) {
            LocationLog::create([
                'representative_id' => $representative->id,
                'latitude' => 11.2588,
                'longitude' => 75.7804,
                'recorded_at' => now()->startOfDay()
                    ->addHours(9)
                    ->addMinutes($minutes),
            ]);
        }

        $response = $this->getJson(
            '/api/reports/zone-visits'
            . '?date=' . now()->toDateString()
            . '&latitude=11.2588'
            . '&longitude=75.7804'
            . '&radius=100'
        );

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath(
                'data.0.representative_name',
                'Zone Test Representative'
            )
            ->assertJsonPath('data.0.duration_minutes', 20)
            ->assertJsonPath('data.0.location_points', 5);
    }

    public function test_it_rejects_invalid_zone_coordinates(): void
    {
        $response = $this->getJson(
            '/api/reports/zone-visits'
            . '?date=' . now()->toDateString()
            . '&latitude=200'
            . '&longitude=75.7804'
            . '&radius=100'
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }


    public function test_it_splits_visits_when_there_is_a_long_gps_gap(): void
    {
        $representative = Representative::create([
            'name' => 'GPS Gap Representative',
            'employee_code' => 'ZONE002',
        ]);

        // First visit: 09:00 to 09:10
        // Second visit: 10:00 to 10:10
        foreach ([0, 5, 10, 60, 65, 70] as $minutes) {
            LocationLog::create([
                'representative_id' => $representative->id,
                'latitude' => 11.2588,
                'longitude' => 75.7804,
                'recorded_at' => now()->startOfDay()
                    ->addHours(9)
                    ->addMinutes($minutes),
            ]);
        }

        $response = $this->getJson(
            '/api/reports/zone-visits'
            . '?date=' . now()->toDateString()
            . '&latitude=11.2588'
            . '&longitude=75.7804'
            . '&radius=100'
        );

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.0.entry_time', now()->startOfDay()
                ->addHours(9)->toDateTimeString())
            ->assertJsonPath('data.0.exit_time', now()->startOfDay()
                ->addHours(9)->addMinutes(10)->toDateTimeString())
            ->assertJsonPath('data.1.entry_time', now()->startOfDay()
                ->addHours(10)->toDateTimeString())
            ->assertJsonPath('data.1.exit_time', now()->startOfDay()
                ->addHours(10)->addMinutes(10)->toDateTimeString());
    }

    
    public function test_it_splits_visits_when_representative_leaves_the_zone(): void
    {
        $representative = Representative::create([
            'name' => 'Zone Return Representative',
            'employee_code' => 'ZONE003',
        ]);

        $points = [
            [0, 11.2588, 75.7804],
            [5, 11.2588, 75.7804],
            [10, 11.2588, 75.7804],
            [15, 11.2620, 75.7804], // Outside the 100 m zone
            [20, 11.2588, 75.7804],
            [25, 11.2588, 75.7804],
            [30, 11.2588, 75.7804],
        ];

        foreach ($points as [$minutes, $latitude, $longitude]) {
            LocationLog::create([
                'representative_id' => $representative->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'recorded_at' => now()->startOfDay()
                    ->addHours(9)
                    ->addMinutes($minutes),
            ]);
        }

        $response = $this->getJson(
            '/api/reports/zone-visits'
            . '?date=' . now()->toDateString()
            . '&latitude=11.2588'
            . '&longitude=75.7804'
            . '&radius=100'
        );

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.0.duration_minutes', 10)
            ->assertJsonPath('data.0.location_points', 3)
            ->assertJsonPath('data.1.duration_minutes', 10)
            ->assertJsonPath('data.1.location_points', 3);
    }

}
