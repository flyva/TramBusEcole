<?php

namespace App\Controller;

use App\Repository\PresetRepository;
use App\Repository\ScreenOffPeriodRepository;
use App\Repository\SettingRepository;
use App\Repository\SlideRepository;
use App\Service\ItineraryBuilder;
use App\Service\TbmDeparturesService;
use App\Service\VcubService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DeparturesController extends AbstractController
{
    /** Coordinates of "Lycée Václav Havel", used as the itinerary's starting point. */
    private const ORIGIN_LAT = 44.786049;
    private const ORIGIN_LNG = -0.564053;

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('departures/index.html.twig');
    }

    #[Route('/api/departures', name: 'api_departures', methods: ['GET'])]
    public function departures(
        TbmDeparturesService $tbm,
        SlideRepository $slideRepository,
        SettingRepository $settingRepository,
        PresetRepository $presetRepository,
        ItineraryBuilder $itineraryBuilder,
        VcubService $vcubService,
        ScreenOffPeriodRepository $screenOffPeriodRepository,
    ): JsonResponse {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $localNow = $now->setTimezone(new \DateTimeZone('Europe/Paris'));
        $setting = $settingRepository->getOrCreate();

        $screenOn = true;
        foreach ($screenOffPeriodRepository->findEnabled() as $period) {
            if ($period->contains($localNow->format('H:i'))) {
                $screenOn = false;
                break;
            }
        }

        if (!$screenOn) {
            return $this->json([
                'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'screenOn' => false,
                'activePreset' => null,
                'slides' => [],
            ]);
        }

        // Override is an absolute instant (timezone-agnostic to compare); the
        // weekly schedule is expressed in local wall-clock time.
        $activePreset = $setting->getActiveOverride($now) ?? $presetRepository->findScheduledForNow($localNow);

        if ($activePreset !== null) {
            $slides = [$itineraryBuilder->build($activePreset, self::ORIGIN_LAT, self::ORIGIN_LNG)];
        } else {
            $slides = $tbm->getDepartures();

            if ($bikesSlide = $vcubService->buildSlide(self::ORIGIN_LAT, self::ORIGIN_LNG)) {
                $slides[] = $bikesSlide;
            }
        }

        foreach ($slideRepository->findActiveOrdered() as $slide) {
            $slides[] = [
                'type' => 'image',
                'imageUrl' => '/uploads/slides/'.$slide->getFilename(),
                'caption' => $slide->getCaption(),
            ];
        }

        return $this->json([
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'screenOn' => true,
            'activePreset' => $activePreset?->getName(),
            'slides' => $slides,
        ]);
    }
}
