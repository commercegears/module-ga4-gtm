<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product attributes that can hold a brand (text and select types).
 */
class ProductAttributes implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    /**
     * @return array<int, array{value: string, label: string|\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- None --')]];
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('frontend_input', ['in' => ['text', 'select']])
            ->setOrder('frontend_label', 'ASC');
        foreach ($collection as $attribute) {
            $label = (string)$attribute->getFrontendLabel();
            if ($label === '') {
                continue;
            }
            $options[] = ['value' => $attribute->getAttributeCode(), 'label' => $label . ' (' . $attribute->getAttributeCode() . ')'];
        }
        return $options;
    }
}
