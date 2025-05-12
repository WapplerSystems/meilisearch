<?php
declare(strict_types=1);

namespace WapplerSystems\Meilisearch\Command;


use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;
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
    name: 'meilisearch:facetsearch',
    description: 'Do a facet search',
)]
class FacetSearchCommand extends Command
{


    public function __construct(
        readonly ConnectionManager $connectionManager,
        readonly SiteRepository    $siteRepository,
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
            ->setDescription('Do a facet search')
            ->addArgument(
                'query',
                InputArgument::REQUIRED,
                'Query to search for'
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
        $query = (string)$input->getArgument('query');

        $connections = $this->connectionManager->getConnectionsBySite($site);

        /** @var MeilisearchConnection $connection */
        $connection = $connections[1];

        $client = $connection->getService()->getClient();

        $result = $client->multiSearch([
            (new SearchQuery())
                ->setIndexUid('pages')
                ->setQuery($query)
                ->setShowRankingScore(true),
            (new SearchQuery())
                ->setIndexUid('tt_content')
                ->setQuery($query)
                ->setShowRankingScore(true),
        ],
            (new MultiSearchFederation())
                /*->setFacetsByIndex([
                    'pages' => ['title'],
                    'tt_content' => ['bodytext']]
                )*/
                ->setMergeFacets(['maxValuesPerFacet' => 10])
                ->setLimit(10)
        );

        DebugUtility::debug($result);


        return Command::SUCCESS;
    }


}
