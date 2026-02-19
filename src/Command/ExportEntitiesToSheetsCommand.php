<?php

namespace Gponty\EntityToGoogleSheetsBundle\Command;

use Gponty\EntityToGoogleSheetsBundle\Service\EntityReader;
use Gponty\EntityToGoogleSheetsBundle\Service\GoogleSheetsExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export-entities-to-sheets',
    description: 'Exporte toutes les entités Doctrine vers Google Sheets'
)]
class ExportEntitiesToSheetsCommand extends Command
{
    public function __construct(
        private readonly EntityReader $entityReader,
        private readonly GoogleSheetsExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Export des entités vers Google Sheets');

        $io->section('Lecture des entités Doctrine...');
        $entities = $this->entityReader->getAllEntities();

        if (empty($entities)) {
            $io->warning('Aucune entité trouvée.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('%d entité(s) trouvée(s).', count($entities)));

        $io->section('Export vers Google Sheets...');
        $io->progressStart(count($entities) + 1);

        $this->exporter->export($entities);

        $io->progressFinish();
        $io->success('Export terminé avec succès ! 🎉');

        return Command::SUCCESS;
    }
}