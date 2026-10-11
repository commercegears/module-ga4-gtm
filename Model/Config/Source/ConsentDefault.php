<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ConsentDefault implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => __('Do not set (consent tool sets them)')],
            ['value' => 'denied', 'label' => __('Denied until updated')],
            ['value' => 'granted', 'label' => __('Granted (no consent required)')],
        ];
    }
}
