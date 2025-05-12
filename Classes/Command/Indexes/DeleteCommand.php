<?php
declare(strict_types=1);

namespace WapplerSystems\Meilisearch\Command\Indexes;


use Meilisearch\Exceptions\ApiException;
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
    name: 'meilisearch:indexes:delete',
    description: 'Delete index from Meilisearch',
)]
class DeleteCommand extends Command
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
            ->setDescription('Delete index from Meilisearch')
            ->addArgument(
                'indexId',
                InputArgument::REQUIRED,
                'Index ID to be deleted'
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

        $indexId = (string)$input->getArgument('indexId');
        try {
            $pageIndex = $client->getIndex($indexId);
            $client->deleteIndex($indexId);
        } catch (ApiException $e) {
            $output->writeln('Index ' . $indexId . ' not found');
            return Command::FAILURE;
        }

        $output->writeln('Index ' . $indexId . ' has been deleted');

        return Command::SUCCESS;
    }


}
