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

namespace WapplerSystems\Meilisearch\Middleware;

use WapplerSystems\Meilisearch\Indexer\PageIndexerRequest;
use WapplerSystems\Meilisearch\Indexer\PageIndexerRequestHandler;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PageIndexerInitialization
 */
class PageIndexerInitialization implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $pageIndexerRequestHandler = null;
        $pageIndexerRequest = null;
        if ($request->hasHeader(PageIndexerRequest::MEILISEARCH_INDEX_HEADER)) {
            // disable TSFE cache for TYPO3 v10
            $request = $request->withAttribute('noCache', true);
            $jsonEncodedParameters = $request->getHeader(PageIndexerRequest::MEILISEARCH_INDEX_HEADER)[0];
            $pageIndexerRequest = GeneralUtility::makeInstance(PageIndexerRequest::class, $jsonEncodedParameters);
            if (!$pageIndexerRequest->isAuthenticated()) {
                $logger = GeneralUtility::makeInstance(MeilisearchLogManager::class, self::class);
                $logger->error(
                    'Invalid Index Queue Frontend Request detected!',
                    [
                        'page indexer request' => (array)$pageIndexerRequest,
                        'index queue header' => $jsonEncodedParameters,
                    ]
                );
                return new JsonResponse(['error' => ['code' => 403, 'message' => 'Invalid Index Queue Request.']], 403);
            }
            $request = $request->withAttribute('meilisearch.pageIndexingInstructions', $pageIndexerRequest);
            $pageIndexerRequestHandler = GeneralUtility::makeInstance(PageIndexerRequestHandler::class);
            $pageIndexerRequestHandler->initialize($pageIndexerRequest);
        }

        $response = $handler->handle($request);
        if ($pageIndexerRequestHandler instanceof PageIndexerRequestHandler && $pageIndexerRequest instanceof PageIndexerRequest) {
            $pageIndexResponse = $pageIndexerRequestHandler->shutdown($pageIndexerRequest);

            $body = new Stream('php://temp', 'rw');
            $content = $pageIndexResponse->getContent();
            $body->write($content);
            return (new Response())
                ->withBody($body)
                ->withHeader('Content-Length', (string)strlen($content))
                ->withHeader('Content-Type', 'application/json');
        }
        return $response;
    }
}
