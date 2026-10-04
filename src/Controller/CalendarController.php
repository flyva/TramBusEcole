<?php

namespace App\Controller;

use App\Entity\CalendarDay;
use App\Repository\CalendarDayRepository;
use App\Repository\PresetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/calendar')]
class CalendarController extends AbstractController
{
    #[Route('', name: 'admin_calendar', methods: ['GET'])]
    public function index(Request $request, CalendarDayRepository $calendarDayRepository, PresetRepository $presetRepository): Response
    {
        $today = new \DateTimeImmutable('today');
        $year = (int) $request->query->get('year', $today->format('Y'));
        $month = (int) $request->query->get('month', $today->format('n'));

        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $prev = $monthStart->modify('-1 month');
        $next = $monthStart->modify('+1 month');

        // Monday-first grid covering the full weeks the month spans.
        $gridStart = $monthStart->modify('monday this week');
        if ($gridStart > $monthStart) {
            $gridStart = $gridStart->modify('-1 week');
        }

        $days = [];
        $cursor = $gridStart;
        $assigned = $calendarDayRepository->findForMonth($year, $month);
        // Also fetch the days bleeding into adjacent months shown in the grid.
        $assignedPrev = $calendarDayRepository->findForMonth((int) $prev->format('Y'), (int) $prev->format('n'));
        $assignedNext = $calendarDayRepository->findForMonth((int) $next->format('Y'), (int) $next->format('n'));
        $allAssigned = $assigned + $assignedPrev + $assignedNext;

        for ($i = 0; $i < 42; $i++) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'date' => $cursor,
                'inMonth' => (int) $cursor->format('n') === $month,
                'isToday' => $key === $today->format('Y-m-d'),
                'calendarDay' => $allAssigned[$key] ?? null,
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $frenchMonths = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];

        return $this->render('admin/calendar/index.html.twig', [
            'monthLabel' => $frenchMonths[$month].' '.$year,
            'monthStart' => $monthStart,
            'prevYear' => (int) $prev->format('Y'),
            'prevMonth' => (int) $prev->format('n'),
            'nextYear' => (int) $next->format('Y'),
            'nextMonth' => (int) $next->format('n'),
            'days' => $days,
            'presets' => $presetRepository->findAllOrdered(),
        ]);
    }

    #[Route('/{date}', name: 'admin_calendar_day', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['GET', 'POST'])]
    public function day(
        string $date,
        Request $request,
        CalendarDayRepository $calendarDayRepository,
        PresetRepository $presetRepository,
        EntityManagerInterface $em,
    ): Response {
        $dateObj = new \DateTimeImmutable($date);
        $calendarDay = $calendarDayRepository->findOneByDate($dateObj);

        if ($request->isMethod('POST')) {
            $presetId = $request->request->get('preset_id');

            if ($presetId === '' || $presetId === null) {
                if ($calendarDay !== null) {
                    $em->remove($calendarDay);
                    $em->flush();
                }
                $this->addFlash('success', 'Jour effacé.');
            } else {
                $preset = $presetRepository->find((int) $presetId);
                if ($preset === null) {
                    $this->addFlash('error', 'Destination introuvable.');

                    return $this->redirectToRoute('admin_calendar_day', ['date' => $date]);
                }

                if ($calendarDay === null) {
                    $calendarDay = (new CalendarDay())->setDate($dateObj);
                    $em->persist($calendarDay);
                }
                $calendarDay->setPreset($preset);
                $em->flush();
                $this->addFlash('success', sprintf('%s assigné au %s.', $preset->getName(), $dateObj->format('d/m/Y')));
            }

            return $this->redirectToRoute('admin_calendar', [
                'year' => $dateObj->format('Y'),
                'month' => (int) $dateObj->format('n'),
            ]);
        }

        $frenchDays = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
        $frenchMonths = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];
        $dateLabel = sprintf(
            '%s %d %s %s',
            $frenchDays[(int) $dateObj->format('N')],
            (int) $dateObj->format('j'),
            $frenchMonths[(int) $dateObj->format('n')],
            $dateObj->format('Y'),
        );

        return $this->render('admin/calendar/day.html.twig', [
            'date' => $dateObj,
            'dateLabel' => $dateLabel,
            'calendarDay' => $calendarDay,
            'presets' => $presetRepository->findAllOrdered(),
        ]);
    }

    #[Route('/assign', name: 'admin_calendar_assign', methods: ['POST'])]
    public function assign(
        Request $request,
        CalendarDayRepository $calendarDayRepository,
        PresetRepository $presetRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];
        $dateStr = $payload['date'] ?? null;
        $presetId = $payload['presetId'] ?? null;

        if (!is_string($dateStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $this->json(['error' => 'Date invalide.'], 400);
        }

        $dateObj = new \DateTimeImmutable($dateStr);
        $calendarDay = $calendarDayRepository->findOneByDate($dateObj);

        if ($presetId === null) {
            if ($calendarDay !== null) {
                $em->remove($calendarDay);
                $em->flush();
            }

            return $this->json(['date' => $dateStr, 'preset' => null]);
        }

        $preset = $presetRepository->find((int) $presetId);
        if ($preset === null) {
            return $this->json(['error' => 'Destination introuvable.'], 404);
        }

        if ($calendarDay === null) {
            $calendarDay = (new CalendarDay())->setDate($dateObj);
            $em->persist($calendarDay);
        }
        $calendarDay->setPreset($preset);
        $em->flush();

        return $this->json(['date' => $dateStr, 'preset' => ['id' => $preset->getId(), 'name' => $preset->getName()]]);
    }

    #[Route('/import/csv', name: 'admin_calendar_import', methods: ['GET', 'POST'])]
    public function import(Request $request, PresetRepository $presetRepository, CalendarDayRepository $calendarDayRepository, EntityManagerInterface $em): Response
    {
        $report = null;

        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $file */
            $file = $request->files->get('csv_file');

            if ($file === null) {
                $this->addFlash('error', 'Choisis un fichier CSV.');

                return $this->redirectToRoute('admin_calendar_import');
            }

            $presetsByName = [];
            foreach ($presetRepository->findAllOrdered() as $preset) {
                $presetsByName[mb_strtolower($preset->getName())] = $preset;
            }

            $imported = 0;
            $errors = [];
            $rowNumber = 0;

            if (($handle = fopen($file->getPathname(), 'r')) !== false) {
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    ++$rowNumber;
                    if (count($row) < 2 || trim($row[0]) === '') {
                        continue;
                    }

                    [$dateStr, $presetName] = [trim($row[0]), trim($row[1])];

                    // createFromFormat() silently overflows out-of-range values
                    // (e.g. "2026-10-99" becomes 2027-01-07) instead of failing,
                    // so round-trip the result and compare to catch that.
                    $dateObj = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr) ?: null;
                    if ($dateObj === false || $dateObj === null || $dateObj->format('Y-m-d') !== $dateStr) {
                        $errors[] = "Ligne $rowNumber : date invalide \"$dateStr\" (attendu AAAA-MM-JJ).";
                        continue;
                    }

                    $preset = $presetsByName[mb_strtolower($presetName)] ?? null;
                    if ($preset === null) {
                        $errors[] = "Ligne $rowNumber : aucune destination nommée \"$presetName\".";
                        continue;
                    }

                    $calendarDay = $calendarDayRepository->findOneByDate($dateObj);
                    if ($calendarDay === null) {
                        $calendarDay = (new CalendarDay())->setDate($dateObj);
                        $em->persist($calendarDay);
                    }
                    $calendarDay->setPreset($preset);
                    ++$imported;
                }
                fclose($handle);
            }

            $em->flush();
            $report = ['imported' => $imported, 'errors' => $errors];
        }

        return $this->render('admin/calendar/import.html.twig', [
            'report' => $report,
            'presets' => $presetRepository->findAllOrdered(),
        ]);
    }
}
