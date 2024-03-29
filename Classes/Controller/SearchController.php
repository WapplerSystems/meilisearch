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
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\Event\Search\AfterFrequentlySearchHasBeenExecutedEvent;
use WapplerSystems\Meilisearch\Event\Search\BeforeSearchFormIsShownEvent;
use WapplerSystems\Meilisearch\Event\Search\BeforeSearchResultIsShownEvent;
use WapplerSystems\Meilisearch\Mvc\Variable\MeilisearchVariableProvider;
use WapplerSystems\Meilisearch\Pagination\ResultsPagination;
use WapplerSystems\Meilisearch\Pagination\ResultsPaginator;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchUnavailableException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\View\AbstractTemplateView;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Class SearchController
 *
 * @property AbstractTemplateView $view {@link AbstractTemplateView} is used in this scope. Line required by PhpStan.
 */
class SearchController extends AbstractBaseController
{
    /**
     * Provide search query in extbase arguments.
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();
        $this->mapGlobalQueryStringWhenEnabled();
    }

    protected function mapGlobalQueryStringWhenEnabled(): void
    {
        $query = GeneralUtility::_GET('q');

        $useGlobalQueryString = $query !== null && !$this->typoScriptConfiguration->getSearchIgnoreGlobalQParameter();
        if ($useGlobalQueryString) {
            $this->request = $this->request->withArgument('q', $query);
        }
    }

    public function initializeView(ViewInterface $view): void
    {
        if ($view instanceof TemplateView) {
            $variableProvider = GeneralUtility::makeInstance(MeilisearchVariableProvider::class);
            $variableProvider->setSource($view->getRenderingContext()->getVariableProvider()->getSource());
            $view->getRenderingContext()->setVariableProvider($variableProvider);
            $view->getRenderingContext()->getVariableProvider()->add(
                'typoScriptConfiguration',
                $this->typoScriptConfiguration
            );

            $customTemplate = $this->getCustomTemplateFromConfiguration();
            if ($customTemplate === '') {
                return;
            }

            if (str_contains($customTemplate, 'EXT:')) {
                $view->setTemplatePathAndFilename($customTemplate);
            } else {
                $view->setTemplate($customTemplate);
            }
        }
    }

    protected function getCustomTemplateFromConfiguration(): string
    {
        $templateKey = str_replace('Action', '', $this->actionMethodName);
        return $this->typoScriptConfiguration->getViewTemplateByFileKey($templateKey);
    }

    /**
     * Results
     *
     * @throws InvalidFacetPackageException
     *
     * @noinspection PhpUnused Is used by plugin.
     */
    public function resultsAction(): ResponseInterface
    {
        if ($this->searchService === null) {
            return $this->handleMeilisearchUnavailable();
        }

        try {
            $arguments = $this->request->getArguments();
            $pageId = $this->typoScriptFrontendController->getRequestedId();
            $languageId = $this->typoScriptFrontendController->getLanguage()->getLanguageId();
            $searchRequest = $this->getSearchRequestBuilder()->buildForSearch($arguments, $pageId, $languageId);

            $searchResultSet = $this->searchService->search($searchRequest);

            // we pass the search result set to the controller context, to have the possibility
            // to access it without passing it from partial to partial
            $this->view->getRenderingContext()->getVariableProvider()->add('searchResultSet', $searchResultSet);

            $currentPage = $this->request->hasArgument('page') ? (int)$this->request->getArgument('page') : 1;

            // prevent currentPage < 1 (i.e for GET request like &tx_meilisearch[page]=0)
            if ($currentPage < 1) {
                $currentPage = 1;
            }

            $itemsPerPage = ($searchResultSet->getUsedResultsPerPage() ?: $this->typoScriptConfiguration->getSearchResultsPerPage());
            $paginator = GeneralUtility::makeInstance(ResultsPaginator::class, $searchResultSet, $currentPage, $itemsPerPage);
            $pagination = GeneralUtility::makeInstance(ResultsPagination::class, $paginator);
            $pagination->setMaxPageNumbers($this->typoScriptConfiguration->getMaxPaginatorLinks());

            /** @var BeforeSearchResultIsShownEvent $afterSearchEvent */
            $afterSearchEvent = $this->eventDispatcher->dispatch(
                new BeforeSearchResultIsShownEvent(
                    $searchResultSet,
                    $this->getAdditionalFilters(),
                    $this->typoScriptConfiguration->getSearchPluginNamespace(),
                    $arguments,
                    $pagination,
                    $currentPage
                )
            );

            $values = [
                'additionalFilters' => $afterSearchEvent->getAdditionalFilters(),
                'resultSet' => $afterSearchEvent->getResultSet(),
                'pluginNamespace' => $afterSearchEvent->getPluginNamespace(),
                'arguments' => $afterSearchEvent->getArguments(),
                'pagination' => $afterSearchEvent->getPagination(),
                'currentPage' => $afterSearchEvent->getCurrentPage(),
                'additionalVariables' => $afterSearchEvent->getAdditionalVariables(),
            ];

            $this->view->assignMultiple($values);
        } catch (MeilisearchUnavailableException) {
            return $this->handleMeilisearchUnavailable();
        }
        return $this->htmlResponse();
    }

    /**
     * Form
     *
     * @noinspection PhpUnused Is used by plugin.
     */
    public function formAction(): ResponseInterface
    {
        if ($this->searchService === null) {
            return $this->handleMeilisearchUnavailable();
        }

        /** @var BeforeSearchFormIsShownEvent $formEvent */
        $formEvent = $this->eventDispatcher->dispatch(
            new BeforeSearchFormIsShownEvent(
                $this->searchService->getSearch(),
                $this->getAdditionalFilters(),
                $this->typoScriptConfiguration->getSearchPluginNamespace()
            )
        );
        $values = [
            'search' => $formEvent->getSearch(),
            'additionalFilters' => $formEvent->getAdditionalFilters(),
            'pluginNamespace' => $formEvent->getPluginNamespace(),
        ];

        $this->view->assignMultiple($values);
        return $this->htmlResponse();
    }

    /**
     * Frequently Searched
     *
     * @noinspection PhpUnused Is used by plugin.
     */
    public function frequentlySearchedAction(): ResponseInterface
    {
        /** @var SearchResultSet $searchResultSet */
        $searchResultSet = GeneralUtility::makeInstance(SearchResultSet::class);

        $pageId = $this->typoScriptFrontendController->getRequestedId();
        $languageId = $this->typoScriptFrontendController->getLanguage()->getLanguageId();
        $searchRequest = $this->getSearchRequestBuilder()->buildForFrequentSearches($pageId, $languageId);
        $searchResultSet->setUsedSearchRequest($searchRequest);

        $this->view->getRenderingContext()->getVariableProvider()->add('searchResultSet', $searchResultSet);

        /** @var AfterFrequentlySearchHasBeenExecutedEvent $afterFrequentlySearchedEvent*/
        $afterFrequentlySearchedEvent = $this->eventDispatcher->dispatch(
            new AfterFrequentlySearchHasBeenExecutedEvent(
                $searchResultSet,
                $this->getAdditionalFilters()
            )
        );
        $values = [
            'additionalFilters' => $afterFrequentlySearchedEvent->getAdditionalFilters(),
            'resultSet' => $afterFrequentlySearchedEvent->getResultSet(),
        ];
        $this->view->assignMultiple($values);
        return $this->htmlResponse();
    }

    /**
     * This action allows to render a detailView with data from meilisearch.
     *
     * @noinspection PhpUnused Is used by plugin.
     */
    public function detailAction(string $documentId = ''): ResponseInterface
    {
        if ($this->searchService === null) {
            return $this->handleMeilisearchUnavailable();
        }

        try {
            $document = $this->searchService->getDocumentById($documentId);
            $this->view->assign('document', $document);
        } catch (MeilisearchUnavailableException) {
            return $this->handleMeilisearchUnavailable();
        }
        return $this->htmlResponse();
    }

    /**
     * Rendered when no search is available.
     *
     * @noinspection PhpUnused Is used by {@link self::handleMeilisearchUnavailable()}
     */
    public function meilisearchNotAvailableAction(): ResponseInterface
    {
        return $this->htmlResponse()
            ->withStatus(503, self::STATUS_503_MESSAGE);
    }

    /**
     * Called when the meilisearch server is unavailable.
     */
    protected function handleMeilisearchUnavailable(): ResponseInterface
    {
        parent::logMeilisearchUnavailable();
        return new ForwardResponse('meilisearchNotAvailable');
    }

    /**
     * This method can be overwritten to add additionalFilters for the auto-suggest.
     * By default, suggest controller will apply the configured filters from the typoscript configuration.
     */
    protected function getAdditionalFilters(): array
    {
        return [];
    }
}
