<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches upcoming bus/tram departures from Bordeaux Métropole's open data
 * SAEIV feed (opendata.bordeaux-metropole.fr) for the "Lycée Václav Havel" stop.
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
    private const API_URL = 'https://opendata.bordeaux-metropole.fr/api/records/1.0/search/';

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

    public function __construct(private readonly HttpClientInterface $httpClient)
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
            $records = $this->fetchUpcoming($stop['gid'], $now);
            $rawByGid[$stop['gid']] = $records;
            foreach ($records as $record) {
                $allCoursIds[$record['coursId']] = true;
            }
        }

        $courseInfo = $this->resolveCourses(array_keys($allCoursIds));

        $destGids = [];
        foreach ($courseInfo as $course) {
            if ($course['destGid'] !== null) {
                $destGids[$course['destGid']] = true;
            }
        }
        $destNames = $this->resolveStopNames(array_keys($destGids));

        // Group stops by their pairing key, keeping STOPS order for stable left/right columns.
        $groups = [];
        foreach (self::STOPS as $stop) {
            $groups[$stop['group']]['mode'] = $stop['mode'];
            $groups[$stop['group']]['gids'][] = $stop['gid'];
        }

        $slides = [];
        foreach ($groups as $group) {
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
                'title' => $title,
                'mode' => $group['mode'],
                'columns' => $columns,
            ];
        }

        return $slides;
    }

    /**
     * @return array<int, array{coursId: int, time: \DateTimeImmutable}>
     */
    private function fetchUpcoming(int $stopGid, \DateTimeImmutable $now): array
    {
        $response = $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'dataset' => 'sv_horai_a',
                'q' => sprintf('rs_sv_arret_p=%d', $stopGid),
                'rows' => 100,
            ],
        ]);

        $data = $response->toArray(false);
        $records = [];

        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            $timeString = $fields['hor_real'] ?? $fields['hor_estime'] ?? $fields['hor_app'] ?? $fields['hor_theo'] ?? null;
            if ($timeString === null || !isset($fields['rs_sv_cours_a'])) {
                continue;
            }

            $time = new \DateTimeImmutable($timeString);
            if ($time < $now->modify('-1 minute')) {
                continue;
            }

            $records[] = [
                'coursId' => (int) $fields['rs_sv_cours_a'],
                'time' => $time,
            ];
        }

        usort($records, fn (array $a, array $b) => $a['time'] <=> $b['time']);

        return array_slice($records, 0, self::MAX_PER_COLUMN);
    }

    /**
     * @param int[] $coursIds
     * @return array<int, array{ligne: ?string, destGid: ?int}>
     */
    private function resolveCourses(array $coursIds): array
    {
        if ($coursIds === []) {
            return [];
        }

        $query = implode(' OR ', array_map(fn (int $id) => "bm_gid=$id", $coursIds));

        $response = $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'dataset' => 'sv_cours_a',
                'q' => $query,
                'rows' => count($coursIds),
            ],
        ]);

        $data = $response->toArray(false);
        $ligneIds = [];
        $courses = [];

        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            $ligneId = $fields['bm_rs_sv_ligne_a'] ?? null;
            $courses[(int) $fields['bm_gid']] = [
                'ligneId' => $ligneId,
                'destGid' => $fields['bm_rg_sv_arret_p_nd'] ?? null,
            ];
            if ($ligneId !== null) {
                $ligneIds[$ligneId] = true;
            }
        }

        $ligneLabels = $this->resolveLignes(array_keys($ligneIds));

        $result = [];
        foreach ($courses as $coursId => $course) {
            $result[$coursId] = [
                'ligne' => $course['ligneId'] !== null ? ($ligneLabels[$course['ligneId']] ?? null) : null,
                'destGid' => $course['destGid'],
            ];
        }

        return $result;
    }

    /**
     * @param int[] $ligneIds
     * @return array<int, string>
     */
    private function resolveLignes(array $ligneIds): array
    {
        if ($ligneIds === []) {
            return [];
        }

        $query = implode(' OR ', array_map(fn (int $id) => "bm_gid=$id", $ligneIds));

        $response = $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'dataset' => 'sv_ligne_a',
                'q' => $query,
                'rows' => count($ligneIds),
            ],
        ]);

        $data = $response->toArray(false);
        $labels = [];
        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            $labels[(int) $fields['bm_gid']] = $fields['bm_libelle'];
        }

        return $labels;
    }

    /**
     * @param int[] $gids
     * @return array<int, string>
     */
    private function resolveStopNames(array $gids): array
    {
        if ($gids === []) {
            return [];
        }

        $query = implode(' OR ', array_map(fn (int $id) => "gid=$id", $gids));

        $response = $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'dataset' => 'sv_arret_p',
                'q' => $query,
                'rows' => count($gids),
            ],
        ]);

        $data = $response->toArray(false);
        $names = [];
        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            $names[(int) $fields['gid']] = $fields['libelle'];
        }

        return $names;
    }
}
