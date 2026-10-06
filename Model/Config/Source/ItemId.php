<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ItemId implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'sku', 'label' => __('SKU')],
            ['value' => 'id', 'label' => __('Product ID')],
        ];
    }
}
