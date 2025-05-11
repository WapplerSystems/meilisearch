<?php
declare(strict_types=1);

namespace WapplerSystems\Meilisearch\Command;


use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;

#[AsCommand(
    name: 'meilisearch:tasks',
    description: 'Show meilisearch tasks',
)]
class TasksCommand extends Command
{


    public function __construct(
        readonly ConnectionManager $connectionManager,
        readonly SiteRepository $siteRepository,
    ) {
        parent::__construct();
    }

    /**
     * Defines the allowed options for this command
     *
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setDescription('Show tasks')
            ->addArgument(
                'siteIdentifier',
                InputArgument::REQUIRED,
                'Site identifier'
            );
    }

    /**
     * Geocode all records
     *
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $siteIdentifier = (string)$input->getArgument('siteIdentifier');

        try {
            $site = $this->siteRepository->getSiteByIdentifier($siteIdentifier);
        } catch (SiteNotFoundException $e) {
            $output->writeln('Site ' . $siteIdentifier . ' not found');
            return Command::FAILURE;
        }

        $connections = $this->connectionManager->getConnectionsBySite($site);

        /** @var MeilisearchConnection $connection */
        $connection = $connections[1];

        $client = $connection->getService()->getClient();

        $tasks = $client->getTasks();
        $output->writeln('Tasks:');

        $rows = [];
        foreach ($tasks as $task) {
            $rows[] = [
                $task['uid'],
                $task['batchUid'],
                $task['indexUid'],
                $task['status'],
                $task['type'],
                $task['canceledBy'],
                '',
                $task['error'],
                $task['duration'],
                $task['enqueuedAt'],
                $task['startedAt'],
                $task['finishedAt'],
            ];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['uid', 'batchUid', 'indexUid', 'status', 'type', 'canceledBy', 'details', 'error', 'duration', 'enqueuedAt', 'startedAt', 'finishedAt'])
            ->setVertical()
            ->setRows($rows)
        ;
        $table->render();

        return Command::SUCCESS;
    }


}
