<?php

namespace App\Controller;

use App\Service\TbmDeparturesService;
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
    public function departures(TbmDeparturesService $tbm): JsonResponse
    {
        return $this->json([
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'slides' => $tbm->getDepartures(),
        ]);
    }
}
