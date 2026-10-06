<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CategoryPath implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'deepest', 'label' => __('Deepest assigned category (full path)')],
            ['value' => 'first', 'label' => __('First assigned category (full path)')],
        ];
    }
}
