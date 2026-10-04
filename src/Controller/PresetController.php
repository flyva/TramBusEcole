<?php

namespace App\Controller;

use App\Entity\Preset;
use App\Entity\Setting;
use App\Form\PresetType;
use App\Repository\PresetRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/presets')]
class PresetController extends AbstractController
{
    #[Route('', name: 'admin_preset_index', methods: ['GET'])]
    public function index(PresetRepository $presetRepository, SettingRepository $settingRepository): Response
    {
        $setting = $settingRepository->getOrCreate();

        return $this->render('admin/presets/index.html.twig', [
            'presets' => $presetRepository->findAllOrdered(),
            'setting' => $setting,
            'activeOverride' => $setting->getActiveOverride(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
        ]);
    }

    #[Route('/new', name: 'admin_preset_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $preset = new Preset();
        $form = $this->createForm(PresetType::class, $preset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($preset);
            $em->flush();
            $this->addFlash('success', 'Destination ajoutée.');

            return $this->redirectToRoute('admin_preset_index');
        }

        return $this->render('admin/presets/form.html.twig', [
            'form' => $form,
            'preset' => $preset,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_preset_edit', methods: ['GET', 'POST'])]
    public function edit(Preset $preset, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PresetType::class, $preset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Destination mise à jour.');

            return $this->redirectToRoute('admin_preset_index');
        }

        return $this->render('admin/presets/form.html.twig', [
            'form' => $form,
            'preset' => $preset,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_preset_delete', methods: ['POST'])]
    public function delete(Preset $preset, EntityManagerInterface $em, SettingRepository $settingRepository): Response
    {
        $setting = $settingRepository->getOrCreate();
        if ($setting->getOverridePreset() === $preset) {
            $setting->setOverridePreset(null)->setOverrideUntil(null);
        }

        $em->remove($preset);
        $em->flush();
        $this->addFlash('success', 'Destination supprimée.');

        return $this->redirectToRoute('admin_preset_index');
    }

    #[Route('/{id}/activate-now', name: 'admin_preset_activate', methods: ['POST'])]
    public function activateNow(Preset $preset, Request $request, EntityManagerInterface $em): Response
    {
        $hours = max(1, min(12, (int) $request->request->get('hours', 2)));

        $setting = $em->getRepository(Setting::class)->findOneBy([]) ?? new Setting();
        if ($setting->getId() === null) {
            $em->persist($setting);
        }

        $setting->setOverridePreset($preset);
        $setting->setOverrideUntil(new \DateTimeImmutable("+{$hours} hours", new \DateTimeZone('UTC')));
        $em->flush();

        $this->addFlash('success', sprintf('"%s" affiché pendant %dh.', $preset->getName(), $hours));

        return $this->redirectToRoute('admin_preset_index');
    }

    #[Route('/override/clear', name: 'admin_preset_override_clear', methods: ['POST'])]
    public function clearOverride(SettingRepository $settingRepository, EntityManagerInterface $em): Response
    {
        $setting = $settingRepository->getOrCreate();
        $setting->setOverridePreset(null)->setOverrideUntil(null);
        $em->flush();

        $this->addFlash('success', 'Affichage forcé désactivé, retour au planning normal.');

        return $this->redirectToRoute('admin_preset_index');
    }
}
