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

namespace WapplerSystems\Meilisearch\Controller\Backend\Search;

use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Index Administration Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class IndexAdministrationModuleController extends AbstractModuleController
{
    /**
     * Index action, shows an overview of available index maintenance operations.
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->selectedSite === null || empty($this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite))) {
            $this->view->assign('can_not_proceed', true);
        }
        return $this->getModuleTemplateResponse();
    }

    /**
     * Empties the site's indexes.
     */
    public function emptyIndexAction(): ResponseInterface
    {
        $siteHash = $this->selectedSite->getSiteHash();

        try {
            $affectedCores = [];
            $meilisearchServers = $this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite);
            foreach ($meilisearchServers as $meilisearchServer) {
                $writeService = $meilisearchServer->getWriteService();
                /** @var MeilisearchConnection $meilisearchServer */
                $writeService->deleteByQuery('siteHash:' . $siteHash);
                $writeService->commit(false, false);
                $affectedCores[] = $writeService->getPrimaryEndpoint()->getCore();
            }
            $message = LocalizationUtility::translate('meilisearch.backend.index_administration.index_emptied_all', 'Meilisearch', [$this->selectedSite->getLabel(), implode(', ', $affectedCores)]);
            $this->addFlashMessage($message);
        } catch (Throwable $e) {
            $this->addFlashMessage(LocalizationUtility::translate('meilisearch.backend.index_administration.error.on_empty_index', 'Meilisearch', [$e->__toString()]), '', ContextualFeedbackSeverity::ERROR);
        }

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Reloads the site's Meilisearch cores.
     */
    public function reloadIndexConfigurationAction(): ResponseInterface
    {
        $coresReloaded = true;
        $reloadedCores = [];
        $meilisearchServers = $this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite);

        foreach ($meilisearchServers as $meilisearchServer) {
            $coreAdmin = $meilisearchServer->getAdminService();
            $coreReloaded = $coreAdmin->reloadCore()->getHttpStatus() === 200;

            $coreName = $coreAdmin->getPrimaryEndpoint()->getCore();
            if (!$coreReloaded) {
                $coresReloaded = false;

                $this->addFlashMessage(
                    'Failed to reload index configuration for core "' . $coreName . '"',
                    '',
                    ContextualFeedbackSeverity::ERROR
                );
                break;
            }

            $reloadedCores[] = $coreName;
        }

        if ($coresReloaded) {
            $this->addFlashMessage(
                'Core configuration reloaded (' . implode(', ', $reloadedCores) . ').',
            );
        }

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }
}
