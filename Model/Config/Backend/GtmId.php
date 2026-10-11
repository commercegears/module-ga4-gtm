<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class GtmId extends Value
{
    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = strtoupper(trim((string)$this->getValue()));
        if ($value !== '' && !preg_match('/^GTM-[A-Z0-9]{4,12}$/', $value)) {
            throw new LocalizedException(__('"%1" is not a GTM container ID (format GTM-XXXXXXX).', $value));
        }
        // The example ID is refused when typed in; one saved before is kept, so other fields can still be saved.
        if (preg_match('/^GTM-X+$/', $value) && $value !== strtoupper((string)$this->getOldValue())) {
            throw new LocalizedException(__('"%1" is the example from the instructions. Enter the ID of your GTM container.', $value));
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
