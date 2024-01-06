<?php
declare(strict_types=1);

namespace WapplerSystems\Meilisearch\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\Meilisearch\Indexer\IndexerFactory;
use WapplerSystems\Meilisearch\Indexer\Item;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository;

/**
 */
class IndexPageCommand extends Command
{


    public function __construct(
        readonly IndexerFactory $indexerFactory,
        readonly PagesRepository $pagesRepository,
        readonly SiteFinder $siteFinder,
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
            ->setDescription('Index page')
            ->addArgument(
                'pageUid',
                InputArgument::REQUIRED,
                'Page ID to index'
            );
    }

    /**
     * Geocode all records
     *
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $pageUid = (int)$input->getArgument('pageUid');

        $page = $this->pagesRepository->getPage($pageUid);

        $output->writeln('Indexing page ' . $page['title'] . ' (' . $page['uid'] . ')');


        $site = $this->siteFinder->getSiteByPageId($pageUid);


        $item = new Item([
            'uid' => 0,
            'root' => $site->getRootPageId(),
            'item_type' => 'pages',
            'item_uid' => $pageUid,
        ]);

        $indexer = $this->indexerFactory->createIndexerForItem($item);


        $indexer->index($item);

        $output->writeln('Done');


        return 0;
    }


}
