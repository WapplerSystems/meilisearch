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

namespace WapplerSystems\Meilisearch\Controller;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\InvalidFacetPackageException;
use WapplerSystems\Meilisearch\Domain\Search\Suggest\SuggestService;
use WapplerSystems\Meilisearch\NoMeilisearchConnectionFoundException;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchUnavailableException;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SuggestController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @copyright (c) 2017 Timo Hund <timo.hund@dkd.de>
 */
class SuggestController extends AbstractBaseController
{
    /**
     * This method creates a suggest json response that can be used in a suggest layer.
     *
     * @throws DBALException
     * @throws InvalidFacetPackageException
     * @throws NoMeilisearchConnectionFoundException
     *
     * @noinspection PhpUnused
     */
    public function suggestAction(string $queryString, ?string $callback = null, ?array $additionalFilters = []): ResponseInterface
    {
        // Get suggestions
        $rawQuery = htmlspecialchars(mb_strtolower(trim($queryString)));

        if ($this->searchService === null) {
            return $this->handleMeilisearchUnavailable();
        }

        try {
            /** @var SuggestService $suggestService */
            $suggestService = GeneralUtility::makeInstance(
                SuggestService::class,
                $this->typoScriptFrontendController,
                $this->searchService,
                $this->typoScriptConfiguration
            );

            $additionalFilters = is_array($additionalFilters) ? array_map('htmlspecialchars', $additionalFilters) : [];
            $pageId = $this->typoScriptFrontendController->getRequestedId();
            $languageId = $this->typoScriptFrontendController->getLanguage()->getLanguageId();
            $arguments = $this->request->getArguments();

            $searchRequest = $this->getSearchRequestBuilder()->buildForSuggest($arguments, $rawQuery, $pageId, $languageId);
            $result = $suggestService->getSuggestions($searchRequest, $additionalFilters);
        } catch (MeilisearchUnavailableException) {
            return $this->handleMeilisearchUnavailable();
        }
        if ($callback) {
            return $this->htmlResponse(htmlspecialchars($callback) . '(' . json_encode($result, JSON_UNESCAPED_SLASHES) . ')');
        }
        return $this->htmlResponse(json_encode($result, JSON_UNESCAPED_SLASHES));
    }

    private function handleMeilisearchUnavailable(): ResponseInterface
    {
        $this->logMeilisearchUnavailable();
        $result = ['status' => false];
        return $this->htmlResponse(json_encode($result, JSON_UNESCAPED_SLASHES))->withStatus(503, self::STATUS_503_MESSAGE);
    }
}
