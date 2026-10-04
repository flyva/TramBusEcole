<?php

namespace App\Service\Tbm;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Low-level, reusable access to Bordeaux Métropole's open data SAEIV feed
 * (opendata.bordeaux-metropole.fr). Shared by the departures board and the
 * itinerary ("preset destination") feature so neither duplicates the other.
 */
class TbmApiClient
{
    private const API_URL = 'https://opendata.bordeaux-metropole.fr/api/records/1.0/search/';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * Finds physical stop platforms within $radiusMeters of a point.
     *
     * @return array<int, array{gid: int, libelle: string, vehicule: string}>
     */
    public function findStopsNear(float $lat, float $lng, int $radiusMeters, ?string $vehicule = null): array
    {
        $params = [
            'dataset' => 'sv_arret_p',
            'geofilter.distance' => sprintf('%F,%F,%d', $lat, $lng, $radiusMeters),
            'rows' => 50,
        ];
        if ($vehicule !== null) {
            $params['q'] = "vehicule=$vehicule";
        }

        $response = $this->httpClient->request('GET', self::API_URL, ['query' => $params]);

        $data = $response->toArray(false);
        $stops = [];

        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];

            $stops[] = [
                'gid' => (int) $fields['gid'],
                'libelle' => $fields['libelle'],
                'vehicule' => $fields['vehicule'],
            ];
        }

        return $stops;
    }

    /**
     * @return array<int, array{coursId: int, time: \DateTimeImmutable}>
     */
    public function fetchUpcomingAtStop(int $stopGid, \DateTimeImmutable $now, int $limit = 3): array
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

        return array_slice($records, 0, $limit);
    }

    /**
     * @param int[] $coursIds
     * @return array<int, array{ligne: ?string, ligneId: ?int, destGid: ?int}>
     */
    public function resolveCourses(array $coursIds): array
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
                'ligneId' => $course['ligneId'] !== null ? (int) $course['ligneId'] : null,
                'destGid' => $course['destGid'],
            ];
        }

        return $result;
    }

    /**
     * @param int[] $ligneIds
     * @return array<int, string>
     */
    public function resolveLignes(array $ligneIds): array
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
            $labels[(int) $fields['bm_gid']] = $this->simplifyLigneLabel($fields['bm_libelle']);
        }

        return $labels;
    }

    /**
     * TBM labels most bus lines "Principale N" or "Locale N" - simplify to "Liane N".
     */
    public function simplifyLigneLabel(string $label): string
    {
        if (preg_match('/^(Principale|Locale)\s+(\d+)$/i', $label, $matches)) {
            return "Liane {$matches[2]}";
        }

        return $label;
    }

    /**
     * @param int[] $gids
     * @return array<int, string>
     */
    public function resolveStopNames(array $gids): array
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

    /**
     * @param int[] $ligneIds
     * @return array<int, array<int, array{titre: string, severite: string}>>
     */
    public function fetchAlerts(array $ligneIds): array
    {
        if ($ligneIds === []) {
            return [];
        }

        $query = implode(' OR ', array_map(fn (int $id) => "rs_sv_ligne_a=$id", $ligneIds));

        $response = $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'dataset' => 'sv_messa_a',
                'q' => $query,
                'rows' => 50,
            ],
        ]);

        $data = $response->toArray(false);
        $byLigne = [];

        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            $ligneId = $fields['rs_sv_ligne_a'] ?? null;
            if ($ligneId === null) {
                continue;
            }

            $byLigne[(int) $ligneId][] = [
                'titre' => $fields['titre'] ?? ($fields['message'] ?? 'Info trafic'),
                'severite' => $fields['severite'] ?? '1_FAIBLE',
            ];
        }

        return $byLigne;
    }

    public static function severityRank(string $severite): int
    {
        return preg_match('/^(\d+)/', $severite, $m) ? (int) $m[1] : 1;
    }
}
