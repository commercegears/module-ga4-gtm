<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Google Ads Conversion ID: AW- followed by digits. A pasted send_to ("AW-123/label") keeps the ID part only.
 */
class AdsConversionId extends Value
{
    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $this->setValue(self::normalise((string)$this->getValue()));
        return parent::beforeSave();
    }

    /**
     * @throws LocalizedException
     */
    public static function normalise(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }
        if (preg_match('#^(AW-\d+)/[A-Z0-9_-]+$#', $value, $m)) {
            $value = $m[1];
        }
        if (!preg_match('/^AW-\d{6,15}$/', $value)) {
            throw new LocalizedException(__(
                '"%1" is not a Google Ads conversion ID. It starts with AW- followed by digits, for example AW-123456789.',
                $value
            ));
        }
        if ($value === 'AW-123456789') {
            throw new LocalizedException(__('"%1" is the example from the instructions. Enter the ID of your Google tag.', $value));
        }
        return $value;
    }
}
