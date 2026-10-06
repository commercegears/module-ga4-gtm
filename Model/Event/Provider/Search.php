<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Event\Provider;

use Magento\Search\Model\QueryFactory;
use Softaware\Ga4\Api\PageEventProviderInterface;

/**
 * search on the search results page.
 */
class Search implements PageEventProviderInterface
{
    public function __construct(private readonly QueryFactory $queryFactory)
    {
    }

    public function getEvents(): array
    {
        $term = trim((string)$this->queryFactory->get()->getQueryText());
        if ($term === '') {
            return [];
        }
        return [['event' => 'search', 'params' => ['search_term' => mb_substr($term, 0, 100)]]];
    }
}
