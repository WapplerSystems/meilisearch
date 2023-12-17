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

namespace WapplerSystems\Meilisearch\ViewHelpers\Debug;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\SearchResult;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\Domain\Search\Score\ScoreCalculationService;
use WapplerSystems\Meilisearch\ViewHelpers\AbstractMeilisearchFrontendViewHelper;
use Closure;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class DocumentScoreAnalyzerViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 *
 * @noinspection PhpUnused Used in {@link Resources/Private/Partials/Result/Document.html}
 */
class DocumentScoreAnalyzerViewHelper extends AbstractMeilisearchFrontendViewHelper
{
    use CompileWithRenderStatic;

    protected static ?ScoreCalculationService $scoreService = null;

    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('document', SearchResult::class, 'The meilisearch document', true);
    }

    /**
     * @throws AspectNotFoundException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function renderStatic(array $arguments, Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $content = '';
        // only check whether a BE user is logged in, don't need to check
        // for enabled score analysis as we wouldn't be here if it was disabled
        $backendUserIsLoggedIn = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'isLoggedIn');
        if ($backendUserIsLoggedIn === false) {
            return $content;
        }

        $document = $arguments['document'];

        /** @var SearchResultSet $resultSet */
        $resultSet = self::getUsedSearchResultSetFromRenderingContext($renderingContext);
        $debugData = '';
        if (
            $resultSet->getUsedSearch()->getDebugResponse() !== null
            && !empty($resultSet->getUsedSearch()->getDebugResponse()->explain)
        ) {
            $debugData = $resultSet->getUsedSearch()->getDebugResponse()->explain->{$document->getId()} ?? '';
        }

        $scoreService = self::getScoreService();
        $queryFields = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchQueryQueryFields();
        $content = $scoreService->getRenderedScores($debugData, $queryFields);

        return '<div class="document-score-analysis">' . $content . '</div>';
    }

    protected static function getScoreService(): ScoreCalculationService
    {
        if (isset(self::$scoreService)) {
            return self::$scoreService;
        }

        self::$scoreService = GeneralUtility::makeInstance(ScoreCalculationService::class);
        return self::$scoreService;
    }
}
