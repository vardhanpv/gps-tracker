<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocationLog;
use Illuminate\Http\Request;

class ZoneVisitReportController extends Controller
{
    private const MAXIMUM_GPS_GAP_SECONDS = 300;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
            'radius' => [
                'required',
                'numeric',
                'gt:0',
                'max:50000',
            ],
        ]);

        $startOfDay = \Carbon\Carbon::createFromFormat(
            'Y-m-d',
            $validated['date']
        )->startOfDay();

        $endOfDay = $startOfDay->copy()->addDay();

        $logs = LocationLog::with('representative')
            ->where('recorded_at', '>=', $startOfDay)
            ->where('recorded_at', '<', $endOfDay)
            ->orderBy('representative_id')
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('representative_id');

        $visits = [];
        $centerLat = (float) $validated['latitude'];
        $centerLon = (float) $validated['longitude'];
        $radius = (float) $validated['radius'];

        foreach ($logs as $representativeLogs) {
            $inside = [];
            $previousLog = null;

            foreach ($representativeLogs as $log) {
                $distance = $this->distanceMeters(
                    $centerLat,
                    $centerLon,
                    (float) $log->latitude,
                    (float) $log->longitude
                );

                $isInside = $distance <= $radius;

                if ($previousLog !== null && count($inside) > 0) {
                    $timeGap = $previousLog->recorded_at
                        ->diffInSeconds($log->recorded_at);

                    if ($timeGap > self::MAXIMUM_GPS_GAP_SECONDS) {
                        $this->addVisitIfQualified($visits, $inside);
                        $inside = [];
                    }
                }

                if ($isInside) {
                    $inside[] = $log;
                } else {
                    $this->addVisitIfQualified($visits, $inside);
                    $inside = [];
                }

                $previousLog = $log;
            }

            $this->addVisitIfQualified($visits, $inside);
        }

        return response()->json([
            'date' => $validated['date'],
            'zone' => [
                'latitude' => $centerLat,
                'longitude' => $centerLon,
                'radius_meters' => $radius,
            ],
            'maximum_gps_gap_minutes' =>
                self::MAXIMUM_GPS_GAP_SECONDS / 60,
            'count' => count($visits),
            'data' => $visits,
        ]);
    }

    private function addVisitIfQualified(
        array &$visits,
        array $group
    ): void {
        if (count($group) < 2) {
            return;
        }

        $start = $group[0]->recorded_at;
        $end = $group[count($group) - 1]->recorded_at;
        $duration = $start->diffInSeconds($end);

        $representative = $group[0]->representative;

        $visits[] = [
            'representative_id' => $group[0]->representative_id,
            'representative_name' => $representative?->name,
            'entry_time' => $start->toDateTimeString(),
            'exit_time' => $end->toDateTimeString(),
            'duration_minutes' => round($duration / 60, 2),
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
