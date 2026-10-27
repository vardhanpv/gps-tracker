<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocationLog;
use Illuminate\Http\Request;

class StopReportController extends Controller
{
    private const STOP_RADIUS_METERS = 50;
    private const MINIMUM_DURATION_SECONDS = 900;
    private const MAXIMUM_GPS_GAP_SECONDS = 300;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'representative_id' => [
                'nullable',
                'integer',
                'exists:representatives,id',
            ],
        ]);

        $query = LocationLog::with('representative')
            ->whereDate('recorded_at', $validated['date'])
            ->orderBy('representative_id')
            ->orderBy('recorded_at');

        if (!empty($validated['representative_id'])) {
            $query->where(
                'representative_id',
                $validated['representative_id']
            );
        }

        $logs = $query->get()->groupBy('representative_id');
        $stops = [];

        foreach ($logs as $representativeLogs) {
            $group = [];
            $anchor = null;
            $previousLog = null;

            foreach ($representativeLogs as $log) {
                if ($anchor === null) {
                    $anchor = $log;
                    $previousLog = $log;
                    $group = [$log];
                    continue;
                }

                $timeGap = $previousLog->recorded_at
                    ->diffInSeconds($log->recorded_at);

                $distance = $this->distanceMeters(
                    (float) $anchor->latitude,
                    (float) $anchor->longitude,
                    (float) $log->latitude,
                    (float) $log->longitude
                );

                if (
                    $timeGap <= self::MAXIMUM_GPS_GAP_SECONDS
                    && $distance <= self::STOP_RADIUS_METERS
                ) {
                    $group[] = $log;
                } else {
                    $this->addStopIfQualified($stops, $group);

                    $anchor = $log;
                    $group = [$log];
                }

                $previousLog = $log;
            }

            $this->addStopIfQualified($stops, $group);
        }

        return response()->json([
            'date' => $validated['date'],
            'stop_radius_meters' => self::STOP_RADIUS_METERS,
            'minimum_duration_minutes' =>
                self::MINIMUM_DURATION_SECONDS / 60,
            'maximum_gps_gap_minutes' =>
                self::MAXIMUM_GPS_GAP_SECONDS / 60,
            'count' => count($stops),
            'data' => $stops,
        ]);
    }

    private function addStopIfQualified(
        array &$stops,
        array $group
    ): void {
        if (count($group) < 2) {
            return;
        }

        $start = $group[0]->recorded_at;
        $end = $group[count($group) - 1]->recorded_at;

        $duration = $start->diffInSeconds($end);

        if ($duration < self::MINIMUM_DURATION_SECONDS) {
            return;
        }

        $representative = $group[0]->representative;

        $stops[] = [
            'representative_id' => $group[0]->representative_id,
            'representative_name' => $representative?->name,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
            'duration_minutes' => round($duration / 60, 2),
            'latitude' => (float) $group[0]->latitude,
            'longitude' => (float) $group[0]->longitude,
            'location_points' => count($group),
        ];
    }

    private function distanceMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(
            sqrt($a),
            sqrt(max(0, 1 - $a))
        );
    }
}
