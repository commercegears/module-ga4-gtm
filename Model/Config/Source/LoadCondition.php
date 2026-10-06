<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LoadCondition implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Immediately (Google Consent Mode)')],
            ['value' => 'analytics', 'label' => __('After "Analytics" consent')],
            ['value' => 'marketing', 'label' => __('After "Marketing" consent')],
        ];
    }
}
