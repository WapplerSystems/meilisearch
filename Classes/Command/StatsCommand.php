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
use TYPO3\CMS\Core\Utility\DebugUtility;
use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;

#[AsCommand(
    name: 'meilisearch:stats',
    description: 'Show meilisearch stats',
)]
class StatsCommand extends Command
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
            ->setDescription('Show stats')
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

        $stats = $client->stats();
        $output->writeln('Stats:');
        $output->writeln('databaseSize: '. $stats['databaseSize']);
        $output->writeln('usedDatabaseSize: '. $stats['usedDatabaseSize']);
        $output->writeln('lastUpdate: '. $stats['lastUpdate']);
        $output->writeln('Indexes:');

        $rows = [];
        foreach ($stats['indexes'] as $indexName => $index) {
            $fieldDistribution = [];
            $i = 0;
            foreach ($index['fieldDistribution'] as $field => $value) {
                $fieldDistribution[] = $field;
                $i++;
                if ($i > 5) {
                    $fieldDistribution[] = '...';
                    break;
                }
            }
            $rows[] = [
                $indexName,
                $index['numberOfDocuments'],
                $index['rawDocumentDbSize'],
                $index['avgDocumentSize'],
                $index['isIndexing'],
                $index['numberOfEmbeddings'],
                $index['numberOfEmbeddedDocuments'],
                implode(', ', $fieldDistribution),
            ];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['index', 'numberOfDocuments', 'rawDocumentDbSize', 'avgDocumentSize', 'isIndexing', 'numberOfEmbeddings', 'numberOfEmbeddedDocuments', 'fieldDistribution'])
            ->setRows($rows)
        ;
        $table->render();

        return Command::SUCCESS;
    }


}
