<?php

namespace App\Command;

use App\Repository\ScreenOffPeriodRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Turns the physical screen on or off (via `xset dpms`) depending on the
 * configured off-periods. Meant to be run every minute from cron:
 *
 *   * * * * * php /path/to/bin/console app:screen-scheduler
 */
#[AsCommand(name: 'app:screen-scheduler', description: "Applique les plages d'extinction de l'écran")]
class ScreenSchedulerCommand extends Command
{
    public function __construct(private readonly ScreenOffPeriodRepository $screenOffPeriodRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $periods = $this->screenOffPeriodRepository->findEnabled();

        if ($periods === []) {
            $io->comment('Aucune plage configurée, écran toujours allumé.');
            $shouldBeOn = true;
        } else {
            $now = (new \DateTimeImmutable())->format('H:i');
            $shouldBeOn = true;
            foreach ($periods as $period) {
                if ($period->contains($now)) {
                    $shouldBeOn = false;
                    break;
                }
            }
        }

        $process = new Process([
            'xset',
            'dpms', 'force', $shouldBeOn ? 'on' : 'off',
        ], null, ['DISPLAY' => ':0']);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error('Échec de la commande xset : '.$process->getErrorOutput());

            return Command::FAILURE;
        }

        $io->comment(sprintf('Écran %s.', $shouldBeOn ? 'allumé' : 'éteint'));

        return Command::SUCCESS;
    }
}
