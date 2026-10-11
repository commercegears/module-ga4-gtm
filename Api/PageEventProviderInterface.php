<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Api;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Supplies dataLayer events rendered into the page (view_item, view_cart, begin_checkout, purchase, search).
 * Add your own provider to the "providers" argument of block "commercegears.ga4.datalayer" in layout XML.
 */
interface PageEventProviderInterface extends ArgumentInterface
{
    /**
     * @return array<int, array{event: string, params: array<string, mixed>, extra?: array<string, mixed>, uid?: string}>
     */
    public function getEvents(): array;
}
