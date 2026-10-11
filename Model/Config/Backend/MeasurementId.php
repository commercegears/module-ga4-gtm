<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class MeasurementId extends Value
{
    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = strtoupper(trim((string)$this->getValue()));
        if ($value !== '' && !preg_match('/^G-[A-Z0-9]{4,15}$/', $value)) {
            throw new LocalizedException(__('"%1" is not a GA4 measurement ID (format G-XXXXXXXXXX).', $value));
        }
        // The example ID is refused when typed in; one saved before is kept, so other fields can still be saved.
        if (preg_match('/^G-X+$/', $value) && $value !== strtoupper((string)$this->getOldValue())) {
            throw new LocalizedException(__('"%1" is the example from the instructions. Enter the measurement ID of your GA4 data stream.', $value));
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
