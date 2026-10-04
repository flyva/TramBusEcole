<?php

namespace App\Controller;

use App\Entity\ScreenOffPeriod;
use App\Entity\Slide;
use App\Form\ScreenOffPeriodType;
use App\Form\SlideUploadType;
use App\Repository\ScreenOffPeriodRepository;
use App\Repository\SlideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(ScreenOffPeriodRepository $screenOffPeriodRepository, SlideRepository $slideRepository): Response
    {
        $periodForm = $this->createForm(ScreenOffPeriodType::class, new ScreenOffPeriod());
        $slideForm = $this->createForm(SlideUploadType::class);

        return $this->render('admin/dashboard.html.twig', [
            'periodForm' => $periodForm,
            'periods' => $screenOffPeriodRepository->findAllOrdered(),
            'slideForm' => $slideForm,
            'slides' => $slideRepository->findAllOrdered(),
        ]);
    }

    #[Route('/screen-off', name: 'admin_screen_off_new', methods: ['POST'])]
    public function addScreenOffPeriod(Request $request, EntityManagerInterface $em): Response
    {
        $period = new ScreenOffPeriod();
        $form = $this->createForm(ScreenOffPeriodType::class, $period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($period);
            $em->flush();
            $this->addFlash('success', 'Plage ajoutée.');
        } else {
            $this->addFlash('error', 'Plage invalide.');
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/screen-off/{id}/toggle', name: 'admin_screen_off_toggle', methods: ['POST'])]
    public function toggleScreenOffPeriod(ScreenOffPeriod $period, EntityManagerInterface $em): Response
    {
        $period->setEnabled(!$period->isEnabled());
        $em->flush();

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/screen-off/{id}/delete', name: 'admin_screen_off_delete', methods: ['POST'])]
    public function deleteScreenOffPeriod(ScreenOffPeriod $period, EntityManagerInterface $em): Response
    {
        $em->remove($period);
        $em->flush();

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/slides', name: 'admin_slide_upload', methods: ['POST'])]
    public function uploadSlide(Request $request, EntityManagerInterface $em, SlideRepository $slideRepository, SluggerInterface $slugger): Response
    {
        $slide = new Slide();
        $form = $this->createForm(SlideUploadType::class, $slide);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            $safeName = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
            $newFilename = sprintf('%s-%s.%s', $safeName, uniqid(), $imageFile->guessExtension());

            try {
                $imageFile->move($this->getParameter('kernel.project_dir').'/public/uploads/slides', $newFilename);
            } catch (FileException) {
                $this->addFlash('error', "Échec de l'upload.");

                return $this->redirectToRoute('admin_dashboard');
            }

            $slide->setFilename($newFilename);
            $maxPosition = 0;
            foreach ($slideRepository->findAllOrdered() as $existing) {
                $maxPosition = max($maxPosition, $existing->getPosition());
            }
            $slide->setPosition($maxPosition + 1);

            $em->persist($slide);
            $em->flush();
            $this->addFlash('success', 'Image ajoutée.');
        } else {
            $this->addFlash('error', "Impossible d'ajouter cette image.");
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/slides/{id}/toggle', name: 'admin_slide_toggle', methods: ['POST'])]
    public function toggleSlide(Slide $slide, EntityManagerInterface $em): Response
    {
        $slide->setActive(!$slide->isActive());
        $em->flush();

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/slides/{id}/delete', name: 'admin_slide_delete', methods: ['POST'])]
    public function deleteSlide(Slide $slide, EntityManagerInterface $em): Response
    {
        $path = $this->getParameter('kernel.project_dir').'/public/uploads/slides/'.$slide->getFilename();
        if (is_file($path)) {
            @unlink($path);
        }

        $em->remove($slide);
        $em->flush();

        return $this->redirectToRoute('admin_dashboard');
    }
}
