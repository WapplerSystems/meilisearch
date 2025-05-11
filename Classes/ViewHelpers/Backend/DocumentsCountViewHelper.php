<?php

declare(strict_types=1);


namespace WapplerSystems\Meilisearch\ViewHelpers\Backend;

use Meilisearch\Endpoints\Indexes;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 */
class DocumentsCountViewHelper extends AbstractViewHelper
{
    /**
     *
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('index', \Meilisearch\Endpoints\Indexes::class, '', true);
    }


    public function render()
    {

        /** @var Indexes $index */
        $index = $this->arguments['index'];

        return $index->all()->count();
    }

}
