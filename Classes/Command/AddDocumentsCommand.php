<?php
declare(strict_types=1);

namespace WapplerSystems\Meilisearch\Command;


use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;

#[AsCommand(
    name: 'meilisearch:addDocuments',
    description: 'Import file to Meilisearch',
)]
class AddDocumentsCommand extends Command
{


    public function __construct(
        readonly ConnectionManager $connectionManager,
        readonly SiteRepository $siteRepository,
    )
    {
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
            ->setDescription('Import file to Meilisearch')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Path to the file to be indexed'
            )
            ->addArgument(
                'indexId',
                InputArgument::REQUIRED,
                'Index ID to be used'
            )
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

        $path = (string)$input->getArgument('path');
        $indexId = (string)$input->getArgument('indexId');
        $siteIdentifier = (string)$input->getArgument('siteIdentifier');
        $output->writeln('Indexing file ' . $path . ' to index ' . $indexId);

        if (!file_exists($path)) {
            $output->writeln('File ' . $path . ' does not exist');
            return Command::FAILURE;
        }
        if (!is_readable($path)) {
            $output->writeln('File ' . $path . ' is not readable');
            return Command::FAILURE;
        }
        if (!is_file($path)) {
            $output->writeln('File ' . $path . ' is not a file');
            return Command::FAILURE;
        }

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

        $indexId = 'pages';

        try {
            $pageIndex = $client->getIndex($indexId);
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            $client->createIndex($indexId, ['primaryKey' => 'uid']);
        }

        $json = json_decode(file_get_contents($path));

        $return = $client->index($indexId)->addDocuments($json);
        $output->writeln('Return from Meilisearch:');
        foreach ($return as $key => $value) {
            $output->writeln($key . ': ' . $value);
        }

        return Command::SUCCESS;
    }


}
