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

namespace WapplerSystems\Meilisearch\System\Meilisearch;

use RuntimeException;

/**
 * This exception or a more specific one should be thrown when there is an error in the communication with the meilisearch server.
 */
class MeilisearchCommunicationException extends RuntimeException
{
    protected ?ResponseAdapter $meilisearchResponse = null;

    public function getMeilisearchResponse(): ?ResponseAdapter
    {
        return $this->meilisearchResponse;
    }

    public function setMeilisearchResponse(ResponseAdapter $meilisearchResponse): void
    {
        $this->meilisearchResponse = $meilisearchResponse;
    }
}
