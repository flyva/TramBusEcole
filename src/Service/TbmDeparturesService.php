<?php

namespace App\Service;

use App\Service\Tbm\TbmApiClient;

/**
 * Builds the main departures board (one "slide" per line: tram, then each
 * bus line) for the "Lycée Václav Havel" stop, using the shared TbmApiClient.
 *
 * Returns one "slide" per line (tram, then bus Lianes 5, ...), each with a
 * fixed left/right column per direction of travel. The destination shown for
 * each departure is whatever TBM reports for that specific trip (e.g. some
 * northbound trams stop at "Parc des Expositions" before continuing to
 * "Cracovie") - both simply appear as separate rows in the same column,
 * since they're the same direction of travel.
 */
class TbmDeparturesService
{
    /**
     * Physical stop platforms located at "Lycée Václav Havel" (Bègles), paired
     * by "group" so each line always renders as a stable 2-column slide.
     */
    private const STOPS = [
        ['gid' => 4004, 'mode' => 'TRAM', 'group' => 'tram'],
        ['gid' => 4005, 'mode' => 'TRAM', 'group' => 'tram'],
        ['gid' => 2601, 'mode' => 'BUS', 'group' => 'bus-5'],
        ['gid' => 606, 'mode' => 'BUS', 'group' => 'bus-5'],
        ['gid' => 2568, 'mode' => 'BUS', 'group' => 'bus-23'],
        ['gid' => 2567, 'mode' => 'BUS', 'group' => 'bus-23'],
        ['gid' => 2845, 'mode' => 'BUS', 'group' => 'bus-89'],
        ['gid' => 3060, 'mode' => 'BUS', 'group' => 'bus-89'],
    ];

    private const MAX_PER_COLUMN = 3;

    /**
     * TBM line id per group, used to fetch traffic alerts independently of
     * whichever passages happen to be found live.
     */
    private const LIGNE_ID_BY_GROUP = [
        'tram' => 61, // Tram C
        'bus-5' => 5,
        'bus-23' => 21,
        'bus-89' => 109,
    ];

    public function __construct(private readonly TbmApiClient $tbm)
    {
    }

    /**
     * @return array<int, array{title: string, mode: string, columns: array<int, array{gid: int, passages: array}>}>
     */
    public function getDepartures(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $rawByGid = [];
        $allCoursIds = [];

        foreach (self::STOPS as $stop) {
            $records = $this->tbm->fetchUpcomingAtStop($stop['gid'], $now, self::MAX_PER_COLUMN);
            $rawByGid[$stop['gid']] = $records;
            foreach ($records as $record) {
                $allCoursIds[$record['coursId']] = true;
            }
        }

        $courseInfo = $this->tbm->resolveCourses(array_keys($allCoursIds));

        $destGids = [];
        foreach ($courseInfo as $course) {
            if ($course['destGid'] !== null) {
                $destGids[$course['destGid']] = true;
            }
        }
        $destNames = $this->tbm->resolveStopNames(array_keys($destGids));

        $alertsByLigne = $this->tbm->fetchAlerts(array_values(self::LIGNE_ID_BY_GROUP));

        // Group stops by their pairing key, keeping STOPS order for stable left/right columns.
        $groups = [];
        foreach (self::STOPS as $stop) {
            $groups[$stop['group']]['mode'] = $stop['mode'];
            $groups[$stop['group']]['gids'][] = $stop['gid'];
        }

        $slides = [];
        foreach ($groups as $groupKey => $group) {
            $columns = [];
            $ligneLabel = null;

            foreach ($group['gids'] as $gid) {
                $passages = [];
                foreach ($rawByGid[$gid] as $record) {
                    $course = $courseInfo[$record['coursId']] ?? null;
                    if ($ligneLabel === null && ($course['ligne'] ?? null) !== null) {
                        $ligneLabel = $course['ligne'];
                    }

                    $passages[] = [
                        'destination' => $course['destGid'] !== null ? ($destNames[$course['destGid']] ?? null) : null,
                        'heure' => $record['time']->format('H:i'),
                        'attenteMinutes' => max(0, (int) ceil(($record['time']->getTimestamp() - $now->getTimestamp()) / 60)),
                    ];
                }

                $columns[] = [
                    'gid' => $gid,
                    'passages' => array_slice($passages, 0, self::MAX_PER_COLUMN),
                ];
            }

            $modeName = $group['mode'] === 'TRAM' ? 'Tram' : 'Bus';
            $alreadyPrefixed = $ligneLabel !== null && str_starts_with(strtolower($ligneLabel), strtolower($modeName));
            $title = $ligneLabel !== null ? ($alreadyPrefixed ? $ligneLabel : "$modeName $ligneLabel") : $modeName;
            $slides[] = [
                'type' => 'departures',
                'title' => $title,
                'mode' => $group['mode'],
                'columns' => $columns,
                'alerts' => $alertsByLigne[self::LIGNE_ID_BY_GROUP[$groupKey]] ?? [],
            ];
        }

        return $slides;
    }
}
