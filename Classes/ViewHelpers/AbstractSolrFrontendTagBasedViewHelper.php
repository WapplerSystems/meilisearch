<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\Meilisearch\ViewHelpers;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;

/**
 * Class AbstractSolrFrontendTagBasedViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractSolrFrontendTagBasedViewHelper extends AbstractSolrTagBasedViewHelper
{
    protected function getTypoScriptConfiguration(): TypoScriptConfiguration
    {
        return $this->renderingContext->getVariableProvider()->get('typoScriptConfiguration');
    }

    protected function getSearchResultSet(): ?SearchResultSet
    {
        return $this->renderingContext->getVariableProvider()->get('searchResultSet');
    }
}
