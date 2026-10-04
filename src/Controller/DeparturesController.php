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
            $slides = [$itineraryBuilder->build($activePreset, $setting->getOriginLat(), $setting->getOriginLng())];
        } else {
            $slides = $tbm->getDepartures();

            if ($bikesSlide = $vcubService->buildSlide($setting->getOriginLat(), $setting->getOriginLng())) {
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
