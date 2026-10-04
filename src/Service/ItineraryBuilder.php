<?php

namespace App\Service;

use App\Entity\Preset;
use App\Service\Tbm\TbmApiClient;

/**
 * Builds the "itinerary" slide for a Preset: the best TBM line to board near
 * HOME to reach the preset's address (preferring a line that serves both
 * ends directly, then skipping lines with a serious active alert in favour
 * of the next nearest one), a driving time (TomTom), a rough transit time
 * estimate, a Maps QR code, and a couple of other nearby-home lines for
 * context.
 */
class ItineraryBuilder
{
    private const SEARCH_RADIUS_METERS = 400;
    private const MAX_STOPS_CONSIDERED = 6;
    private const SERIOUS_ALERT_SEVERITY = 2;

    public function __construct(
        private readonly TbmApiClient $tbm,
        private readonly TomTomService $tomTom,
        private readonly TransitTimeEstimator $transitEstimator,
    ) {
    }

    /**
     * @return array{
     *     type: string, presetName: string, address: string, qrCodeUrl: string,
     *     drivingMinutes: ?int, transitEstimateMinutes: int,
     *     primary: ?array, nearby: array
     * }
     */
    public function build(Preset $preset, float $originLat, float $originLng): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // Candidates are stops near HOME (that's where you'd actually board) - not
        // near the destination, otherwise the "best line" ends up being whatever
        // happens to stop next to the destination, regardless of where you start from.
        $stops = $this->tbm->findStopsNear($originLat, $originLng, self::SEARCH_RADIUS_METERS);
        $stops = array_slice($stops, 0, self::MAX_STOPS_CONSIDERED);

        $rawByGid = [];
        $allCoursIds = [];
        foreach ($stops as $stop) {
            $records = $this->tbm->fetchUpcomingAtStop($stop['gid'], $now, 2);
            $rawByGid[$stop['gid']] = $records;
            foreach ($records as $record) {
                $allCoursIds[$record['coursId']] = true;
            }
        }

        $courseInfo = $this->tbm->resolveCourses(array_keys($allCoursIds));
        $destLigneIds = $this->findLigneIdsServing($preset->getLat(), $preset->getLng());

        $destGids = [];
        $ligneIds = [];
        foreach ($courseInfo as $course) {
            if ($course['destGid'] !== null) {
                $destGids[$course['destGid']] = true;
            }
            if ($course['ligneId'] !== null) {
                $ligneIds[$course['ligneId']] = true;
            }
        }
        $destNames = $this->tbm->resolveStopNames(array_keys($destGids));
        $alertsByLigne = $this->tbm->fetchAlerts(array_keys($ligneIds));

        // Build one candidate per physical stop that actually has upcoming passages.
        $candidates = [];
        foreach ($stops as $stop) {
            $records = $rawByGid[$stop['gid']] ?? [];
            if ($records === []) {
                continue;
            }

            $passages = [];
            $ligneId = null;
            $ligneLabel = null;
            foreach ($records as $record) {
                $course = $courseInfo[$record['coursId']] ?? null;
                $ligneId ??= $course['ligneId'] ?? null;
                $ligneLabel ??= $course['ligne'] ?? null;
                $passages[] = [
                    'destination' => $course['destGid'] !== null ? ($destNames[$course['destGid']] ?? null) : null,
                    'heure' => $record['time']->format('H:i'),
                    'attenteMinutes' => max(0, (int) ceil(($record['time']->getTimestamp() - $now->getTimestamp()) / 60)),
                ];
            }

            $alerts = $ligneId !== null ? ($alertsByLigne[$ligneId] ?? []) : [];
            $maxSeverity = array_reduce(
                $alerts,
                fn (int $carry, array $alert) => max($carry, TbmApiClient::severityRank($alert['severite'])),
                0,
            );

            $candidates[] = [
                'stopLibelle' => $stop['libelle'],
                'vehicule' => $stop['vehicule'],
                'ligne' => $ligneLabel,
                'passages' => $passages,
                'alerts' => $alerts,
                'maxSeverity' => $maxSeverity,
                'nextWaitMinutes' => $passages[0]['attenteMinutes'] ?? PHP_INT_MAX,
                // Whether this line (from home) also stops near the destination, i.e.
                // it plausibly gets you there without changing lines.
                'direct' => $ligneId !== null && isset($destLigneIds[$ligneId]),
            ];
        }

        // Tram by default - it's faster and more frequent - but fall back to bus
        // when the tram option has a serious active disruption. Among same-priority
        // candidates, prefer one that also serves the destination directly (no
        // transfer needed), then the soonest departure.
        usort($candidates, function (array $a, array $b) {
            $aOk = $a['maxSeverity'] < self::SERIOUS_ALERT_SEVERITY;
            $bOk = $b['maxSeverity'] < self::SERIOUS_ALERT_SEVERITY;
            if ($aOk !== $bOk) {
                return $aOk ? -1 : 1;
            }

            $aTram = $a['vehicule'] === 'TRAM';
            $bTram = $b['vehicule'] === 'TRAM';
            if ($aTram !== $bTram) {
                return $aTram ? -1 : 1;
            }

            if ($a['direct'] !== $b['direct']) {
                return $a['direct'] ? -1 : 1;
            }

            return $a['nextWaitMinutes'] <=> $b['nextWaitMinutes'];
        });

        $primary = $candidates[0] ?? null;
        $nearby = array_slice($candidates, 1, 2);

        $drivingMinutes = $this->tomTom->getDrivingMinutes($originLat, $originLng, $preset->getLat(), $preset->getLng());
        $transitEstimateMinutes = $this->transitEstimator->estimateMinutes($originLat, $originLng, $preset->getLat(), $preset->getLng());

        return [
            'type' => 'itinerary',
            'presetName' => $preset->getName(),
            'address' => $preset->getAddress(),
            'qrCodeUrl' => $this->buildQrCodeUrl($preset),
            'drivingMinutes' => $drivingMinutes,
            'transitEstimateMinutes' => $transitEstimateMinutes,
            'primary' => $primary,
            'nearby' => $nearby,
        ];
    }

    /**
     * Line ids with at least one upcoming departure from a stop near the
     * given point, used to tell whether a line found near home also
     * plausibly reaches the destination directly.
     *
     * @return array<int, true>
     */
    private function findLigneIdsServing(float $lat, float $lng): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $stops = array_slice($this->tbm->findStopsNear($lat, $lng, self::SEARCH_RADIUS_METERS), 0, self::MAX_STOPS_CONSIDERED);

        $coursIds = [];
        foreach ($stops as $stop) {
            foreach ($this->tbm->fetchUpcomingAtStop($stop['gid'], $now, 1) as $record) {
                $coursIds[$record['coursId']] = true;
            }
        }

        $ligneIds = [];
        foreach ($this->tbm->resolveCourses(array_keys($coursIds)) as $course) {
            if ($course['ligneId'] !== null) {
                $ligneIds[$course['ligneId']] = true;
            }
        }

        return $ligneIds;
    }

    private function buildQrCodeUrl(Preset $preset): string
    {
        $mapsUrl = sprintf(
            'https://www.google.com/maps/dir/?api=1&destination=%F,%F',
            $preset->getLat(),
            $preset->getLng(),
        );

        return 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data='.urlencode($mapsUrl);
    }
}
