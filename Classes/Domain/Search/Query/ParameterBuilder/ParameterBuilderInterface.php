<?php

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

namespace WapplerSystems\Meilisearch\Domain\Search\Query\ParameterBuilder;

use WapplerSystems\Meilisearch\Domain\Search\Query\AbstractQueryBuilder;

/**
 * The implementation of ParameterBuilder is responsible to build an array with
 * the query parameter that are needed for meilisearch
 *
 * Interface ParameterProvider
 */
interface ParameterBuilderInterface
{
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder;
}
