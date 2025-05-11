<?php

declare(strict_types=1);


namespace WapplerSystems\Meilisearch\ViewHelpers\Backend;

use Meilisearch\Endpoints\Indexes;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 */
class IndexPropertyViewHelper extends AbstractViewHelper
{
    /**
     *
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('index', \Meilisearch\Endpoints\Indexes::class, '', true);
        $this->registerArgument('propertyName', 'string', '', true);
    }


    public function render()
    {

        /** @var Indexes $index */
        $index = $this->arguments['index'];
        $propertyName = $this->arguments['propertyName'];

        DebugUtility::debug($index->all()->count());

    }

}
