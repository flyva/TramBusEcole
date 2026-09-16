<?php

namespace App\Command;

use App\Repository\SettingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Turns the physical screen on or off (via `xset dpms`) depending on the
 * configured schedule. Meant to be run every minute from cron:
 *
 *   * * * * * php /path/to/bin/console app:screen-scheduler
 */
#[AsCommand(name: 'app:screen-scheduler', description: "Applique l'horaire d'extinction de l'écran")]
class ScreenSchedulerCommand extends Command
{
    public function __construct(private readonly SettingRepository $settingRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $setting = $this->settingRepository->getOrCreate();

        if (!$setting->isScheduleEnabled()) {
            $io->comment('Horaire désactivé, rien à faire.');

            return Command::SUCCESS;
        }

        $now = (new \DateTimeImmutable())->format('H:i');
        $shouldBeOn = $this->isWithinSchedule($now, $setting->getScreenOnTime(), $setting->getScreenOffTime());

        $process = new Process([
            'xset',
            'dpms', 'force', $shouldBeOn ? 'on' : 'off',
        ], null, ['DISPLAY' => ':0']);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error('Échec de la commande xset : '.$process->getErrorOutput());

            return Command::FAILURE;
        }

        $io->comment(sprintf('Écran %s (heure: %s, plage: %s-%s).', $shouldBeOn ? 'allumé' : 'éteint', $now, $setting->getScreenOnTime(), $setting->getScreenOffTime()));

        return Command::SUCCESS;
    }

    private function isWithinSchedule(string $now, string $onTime, string $offTime): bool
    {
        if ($onTime <= $offTime) {
            // Normal same-day window, e.g. 07:00 -> 22:00.
            return $now >= $onTime && $now < $offTime;
        }

        // Overnight window, e.g. 22:00 -> 07:00.
        return $now >= $onTime || $now < $offTime;
    }
}
