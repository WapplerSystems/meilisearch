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

namespace WapplerSystems\Meilisearch\EventListener\PageIndexer;

use WapplerSystems\Meilisearch\Access\Rootline;
use WapplerSystems\Meilisearch\IndexQueue\FrontendHelper\AuthorizationService;
use WapplerSystems\Meilisearch\IndexQueue\PageIndexerRequest;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\ModifyResolvedFrontendGroupsEvent;

/**
 * Class FrontendGroupsModifier is responsible to fake the fe_groups to make
 * the indexing of access restricted pages and content elements.
 */
class FrontendGroupsModifier
{
    /**
     * Modifies the fe_groups of a user on X-Tx-Meilisearch-Iq requests.
     *
     * @throws PropagateResponseException
     */
    public function __invoke(ModifyResolvedFrontendGroupsEvent $event): void
    {
        $pageIndexerRequest = $event->getRequest()->getAttribute('meilisearch.pageIndexingInstructions');
        if (!$pageIndexerRequest instanceof PageIndexerRequest
            || (
                (int)$pageIndexerRequest->getParameter('userGroup') === 0
                && (
                    (int)$pageIndexerRequest->getParameter('pageUserGroup') !== -2
                    &&
                    (int)$pageIndexerRequest->getParameter('pageUserGroup') < 1
                )
            )
        ) {
            return;
        }

        if (!$pageIndexerRequest->isAuthenticated()) {
            $logger = GeneralUtility::makeInstance(MeilisearchLogManager::class, self::class);
            $logger->error(
                'Invalid Index Queue Frontend Request detected!',
                [
                    'page indexer request' => (array)$pageIndexerRequest,
                    'index queue header' => $event->getRequest()->getHeader(PageIndexerRequest::SOLR_INDEX_HEADER)[0],
                ]
            );
            throw new PropagateResponseException(
                new JsonResponse(
                    [
                        'error' => [
                            'code' => 403,
                            'message' => 'Invalid Index Queue Request.',
                        ],
                    ],
                    403
                ),
                1646655622
            );
        }

        $groups = $this->resolveFrontendUserGroups($pageIndexerRequest);
        if ((int)$pageIndexerRequest->getParameter('pageUserGroup') > 0) {
            $groups[] = (int)$pageIndexerRequest->getParameter('pageUserGroup');
        }
        $groupData = [];
        foreach ($groups as $groupUid) {
            if (in_array($groupUid, [-2, -1])) {
                continue;
            }
            $groupData[] = [
                'title' => 'group_(' . $groupUid . ')',
                'uid' => $groupUid,
                'pid' => 0,
            ];
        }
        $event->getUser()->user[$event->getUser()->username_column] = AuthorizationService::SOLR_INDEXER_USERNAME;
        $event->setGroups($groupData);
    }

    /**
     * Resolves a logged in fe_groups to retrieve access restricted content.
     */
    protected function resolveFrontendUserGroups(PageIndexerRequest $pageIndexerRequest): array
    {
        $accessRootline = $this->getAccessRootline($pageIndexerRequest);
        $stringAccessRootline = (string)$accessRootline;
        if (empty($stringAccessRootline)) {
            return [];
        }
        return $accessRootline->getGroups();
    }

    /**
     * Gets the access rootline as defined by the request.
     */
    protected function getAccessRootline(PageIndexerRequest $pageIndexerRequest): Rootline
    {
        $stringAccessRootline = '';
        if ($pageIndexerRequest->getParameter('accessRootline')) {
            $stringAccessRootline = $pageIndexerRequest->getParameter('accessRootline');
        }
        return GeneralUtility::makeInstance(Rootline::class, $stringAccessRootline);
    }
}
