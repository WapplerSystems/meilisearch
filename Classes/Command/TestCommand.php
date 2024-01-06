<?php
declare(strict_types=1);

namespace WapplerSystems\Meilisearch\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\DebugUtility;
use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;

/**
 */
class TestCommand extends Command
{


    public function __construct(
        readonly ConnectionManager $connectionManager
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

    }

    /**
     * Geocode all records
     *
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {


        $connections = $this->connectionManager->getAllConnections();

        /** @var MeilisearchConnection $connection */
        $connection = $connections[1];

        $client = $connection->getService()->getClient();

        $indexId = 'pages_656';

        //$result = $client->createIndex($indexId, ['primaryKey' => 'uid']);
        //DebugUtility::debug($result);

        $result = $client->index($indexId)->search('boy');
        DebugUtility::debug($result->toArray(), 'search');

        $return = $client->index($indexId)->addDocuments([
            [
                'uid' => 287947,
                'title' => 'Shazam',
                'poster' => 'https://image.tmdb.org/t/p/w1280/xnopI5Xtky18MPhK40cZAGAOVeV.jpg',
                'overview' => 'A boy is given the ability to become an adult superhero in times of need with a single magic word.',
                'release_date' => '2019-03-23'
            ]
        ]);
        DebugUtility::debug($return);

        $tasks = $client->getTasks();
        //DebugUtility::debug($tasks->toArray(),'tasks');


        $indexes = $client->getIndexes();


        return 0;
    }


}
