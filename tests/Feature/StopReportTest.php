<?php

namespace Tests\Feature;

use App\Models\LocationLog;
use App\Models\Representative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StopReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_a_qualified_stop(): void
    {
        $representative = Representative::create([
            'name' => 'Automated Test Representative',
            'employee_code' => 'AUTO001',
        ]);

        foreach ([0, 5, 10, 15, 20] as $minutes) {
            LocationLog::create([
                'representative_id' => $representative->id,
                'latitude' => 11.2588,
                'longitude' => 75.7804,
                'recorded_at' => now()->startOfDay()->addHours(9)->addMinutes($minutes),
            ]);
        }

        $response = $this->getJson(
            '/api/reports/stops?date=' . now()->toDateString()
        );

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath(
                'data.0.representative_name',
                'Automated Test Representative'
            )
            ->assertJsonPath('data.0.duration_minutes', 20)
            ->assertJsonPath('data.0.location_points', 5);
    }

    public function test_it_rejects_an_invalid_date(): void
    {
        $response = $this->getJson(
            '/api/reports/stops?date=invalid'
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    
    public function test_it_does_not_combine_stops_across_a_long_gps_gap(): void
    {
        $representative = Representative::create([
            'name' => 'Stop Gap Representative',
            'employee_code' => 'AUTO002',
        ]);

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
            '/api/reports/stops?date=' . now()->toDateString()
        );

        $response->assertOk()
            ->assertJsonPath('count', 0);
    }

}
