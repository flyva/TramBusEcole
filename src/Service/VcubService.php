<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Nearby VCub (Bordeaux bike-share) station availability, built as an extra
 * slide mixed into the normal departures board rotation.
 */
class VcubService
{
    private const API_URL = 'https://opendata.bordeaux-metropole.fr/api/records/1.0/search/';
    private const SEARCH_RADIUS_METERS = 600;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{type: string, title: string, stations: array<int, array{nom: string, velos: int, elec: int, places: int}>}|null
     */
    public function buildSlide(float $lat, float $lng): ?array
    {
        $response = $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'dataset' => 'ci_vcub_p',
                'q' => 'etat=CONNECTEE',
                'geofilter.distance' => sprintf('%F,%F,%d', $lat, $lng, self::SEARCH_RADIUS_METERS),
                'rows' => 4,
            ],
        ]);

        $data = $response->toArray(false);
        $stations = [];

        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            $stations[] = [
                'nom' => $fields['nom'],
                'velos' => (int) ($fields['nbvelos'] ?? 0),
                'elec' => (int) ($fields['nbelec'] ?? 0),
                'places' => (int) ($fields['nbplaces'] ?? 0),
            ];
        }

        if ($stations === []) {
            return null;
        }

        return [
            'type' => 'bikes',
            'title' => 'Vélos VCub',
            'stations' => $stations,
        ];
    }
}
