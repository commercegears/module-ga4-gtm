<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Currency implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'display', 'label' => __('Currency the customer sees (order currency)')],
            ['value' => 'base', 'label' => __('Base currency')],
        ];
    }
}
