<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Hashed customer data for Google enhanced conversions (gtag user_data format).
 */
class UserData
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function fromOrder(OrderInterface $order): array
    {
        if (!$this->config->isUserDataEnabled((int)$order->getStoreId())) {
            return [];
        }
        $address = $order->getBillingAddress();
        $data = ['sha256_email_address' => $this->hash((string)$order->getCustomerEmail())];
        if ($address) {
            $phone = $this->normalisePhone((string)$address->getTelephone());
            if ($phone !== '') {
                $data['sha256_phone_number'] = hash('sha256', $phone);
            }
            $street = $address->getStreet();
            $data['address'] = array_filter([
                'sha256_first_name' => $this->hash((string)$address->getFirstname()),
                'sha256_last_name' => $this->hash((string)$address->getLastname()),
                'sha256_street' => $this->hash(is_array($street) ? (string)($street[0] ?? '') : (string)$street),
                'city' => trim((string)$address->getCity()),
                'region' => trim((string)$address->getRegion()),
                'postal_code' => trim((string)$address->getPostcode()),
                'country' => (string)$address->getCountryId(),
            ]);
        }
        return array_filter($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function fromCustomer(CustomerInterface $customer): array
    {
        if (!$this->config->isUserDataEnabled((int)$customer->getStoreId())) {
            return [];
        }
        return array_filter([
            'sha256_email_address' => $this->hash((string)$customer->getEmail()),
            'address' => array_filter([
                'sha256_first_name' => $this->hash((string)$customer->getFirstname()),
                'sha256_last_name' => $this->hash((string)$customer->getLastname()),
            ]),
        ]);
    }

    private function hash(string $value): string
    {
        $value = mb_strtolower(trim($value));
        return $value === '' ? '' : hash('sha256', $value);
    }

    /**
     * Only numbers already in international format (+44..., 0044...) can be normalised to E.164 safely.
     */
    private function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone) ? $phone : '';
    }
}
