<?php

namespace App\Controller;

use App\Entity\Slide;
use App\Form\ScheduleType;
use App\Form\SlideUploadType;
use App\Repository\SettingRepository;
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
    public function dashboard(SettingRepository $settingRepository, SlideRepository $slideRepository): Response
    {
        $scheduleForm = $this->createForm(ScheduleType::class, $settingRepository->getOrCreate());
        $slideForm = $this->createForm(SlideUploadType::class);

        return $this->render('admin/dashboard.html.twig', [
            'scheduleForm' => $scheduleForm,
            'slideForm' => $slideForm,
            'slides' => $slideRepository->findAllOrdered(),
        ]);
    }

    #[Route('/schedule', name: 'admin_schedule', methods: ['POST'])]
    public function schedule(Request $request, SettingRepository $settingRepository, EntityManagerInterface $em): Response
    {
        $setting = $settingRepository->getOrCreate();
        $form = $this->createForm(ScheduleType::class, $setting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Horaires enregistrés.');
        } else {
            $this->addFlash('error', 'Horaires invalides.');
        }

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
