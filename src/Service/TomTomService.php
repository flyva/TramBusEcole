<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Driving time estimates via the TomTom Routing API.
 */
class TomTomService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(TOMTOM_API_KEY)%')]
        private readonly ?string $apiKey,
    ) {
    }

    /**
     * Returns the driving duration in minutes, or null if unavailable
     * (missing API key, network error, etc).
     */
    public function getDrivingMinutes(float $fromLat, float $fromLng, float $toLat, float $toLng): ?int
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', sprintf(
                'https://api.tomtom.com/routing/1/calculateRoute/%F,%F:%F,%F/json',
                $fromLat,
                $fromLng,
                $toLat,
                $toLng,
            ), [
                'query' => ['key' => $this->apiKey, 'traffic' => 'true'],
                'timeout' => 5,
            ]);

            $data = $response->toArray(false);
            $seconds = $data['routes'][0]['summary']['travelTimeInSeconds'] ?? null;

            return $seconds !== null ? (int) ceil($seconds / 60) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
