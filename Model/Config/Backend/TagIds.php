<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use CommerceGears\Ga4\Model\Config;

/**
 * Additional Google Tag IDs: G-, AW-, DC- or GT- IDs, saved as a tidy comma-separated list.
 */
class TagIds extends Value
{
    private const MAX_IDS = 10;

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
        $ids = Config::parseTagIds($value);
        foreach ($ids as $id) {
            if (!Config::isValidTagId($id)) {
                throw new LocalizedException(__(
                    '"%1" is not a Google tag ID. Use IDs such as AW-123456789 (Google Ads), G-AB12CD34EF (GA4), DC-1234567 (Floodlight) or GT-AB12CDE (Google tag).',
                    $id
                ));
            }
            if (preg_match('/^(?:G|GT)-X+$/', $id) || $id === 'AW-123456789') {
                throw new LocalizedException(__('"%1" is the example from the instructions. Enter the ID of your Google tag.', $id));
            }
        }
        if (count($ids) > self::MAX_IDS) {
            throw new LocalizedException(__('Enter at most %1 additional Google tag IDs.', self::MAX_IDS));
        }
        return implode(', ', $ids);
    }
}
