<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LeadForms implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'contact', 'label' => __('Contact Us form')],
            ['value' => 'newsletter', 'label' => __('Newsletter sign-up')],
            ['value' => 'custom', 'label' => __('Other forms (reported by another module)')],
        ];
    }
}
