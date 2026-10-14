<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

/**
 * Normalises customer data the way Google expects it for user-provided data / enhanced conversions
 * (https://developers.google.com/analytics/devguides/collection/ga4/uid-data) and hashes it as SHA-256 hex.
 * Pure functions without Magento dependencies.
 */
class UserDataNormaliser
{
    /**
     * Country => [calling code, national trunk prefixes, longest national number for countries without a trunk
     * prefix]. Covers the EU/EEA, the UK, Switzerland, Türkiye and a few large English-speaking markets.
     */
    private const COUNTRIES = [
        'AT' => ['43', ['0'], 0], 'BE' => ['32', ['0'], 0], 'BG' => ['359', ['0'], 0], 'HR' => ['385', ['0'], 0],
        'CY' => ['357', [], 8], 'CZ' => ['420', [], 9], 'DK' => ['45', [], 8], 'EE' => ['372', [], 8],
        'FI' => ['358', ['0'], 0], 'FR' => ['33', ['0'], 0], 'DE' => ['49', ['0'], 0], 'GR' => ['30', [], 10],
        'HU' => ['36', ['06'], 0], 'IE' => ['353', ['0'], 0], 'IT' => ['39', [], 11], 'LV' => ['371', [], 8],
        'LT' => ['370', ['8', '0'], 0], 'LU' => ['352', [], 11], 'MT' => ['356', [], 8], 'NL' => ['31', ['0'], 0],
        'PL' => ['48', [], 9], 'PT' => ['351', [], 9], 'RO' => ['40', ['0'], 0], 'SK' => ['421', ['0'], 0],
        'SI' => ['386', ['0'], 0], 'ES' => ['34', [], 9], 'SE' => ['46', ['0'], 0], 'IS' => ['354', [], 7],
        'LI' => ['423', [], 9], 'NO' => ['47', [], 8], 'GB' => ['44', ['0'], 0], 'CH' => ['41', ['0'], 0],
        'TR' => ['90', ['0'], 0], 'US' => ['1', ['1'], 0], 'CA' => ['1', ['1'], 0], 'AU' => ['61', ['0'], 0],
        'NZ' => ['64', ['0'], 0], 'IM' => ['44', ['0'], 0], 'JE' => ['44', ['0'], 0], 'GG' => ['44', ['0'], 0],
    ];

    public function hash(string $normalised): string
    {
        return $normalised === '' ? '' : hash('sha256', $normalised);
    }

    /**
     * Lower case, no spaces; dots before the @ are removed for gmail.com and googlemail.com.
     */
    public function email(string $email): string
    {
        $email = mb_strtolower(preg_replace('/\s+/u', '', $email) ?? '');
        if (!str_contains($email, '@')) {
            return '';
        }
        [$local, $domain] = explode('@', $email, 2);
        if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
            $local = str_replace('.', '', $local);
        }
        return $local === '' || $domain === '' ? '' : $local . '@' . $domain;
    }

    /**
     * First or last name, city and region: letters only (any alphabet), lower case, single spaces, trimmed.
     */
    public function name(string $value): string
    {
        $value = preg_replace('/[^\p{L}\p{M}\s]+/u', '', $value) ?? '';
        return $this->tidy($value);
    }

    /**
     * Street and house number: symbols removed (letters and digits kept), lower case, trimmed.
     *
     * @param string|string[]|null $street
     */
    public function street(string|array|null $street): string
    {
        $value = is_array($street) ? implode(' ', $street) : (string)$street;
        $value = preg_replace('/[^\p{L}\p{M}\p{N}\s]+/u', '', $value) ?? '';
        return $this->tidy($value);
    }

    /**
     * Postal code: "." and "~" removed, trimmed (not hashed).
     */
    public function postalCode(string $value): string
    {
        return trim(str_replace(['.', '~'], '', $value));
    }

    /**
     * ISO 3166-1 alpha-2, upper case (not hashed).
     */
    public function country(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_match('/^[A-Z]{2}$/', $value) ? $value : '';
    }

    /**
     * E.164 (+ and 8 to 15 digits). Numbers in international format (+44..., 0044...) are kept; national numbers are
     * converted with the calling code of the address country (trunk prefix such as the leading 0 removed).
     * Returns '' when the number cannot be converted safely.
     */
    public function phone(string $phone, string $countryId = ''): string
    {
        $phone = trim($phone);
        $international = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (!$international && str_starts_with($digits, '00')) {
            $international = true;
            $digits = substr($digits, 2);
        }
        if (!$international) {
            $digits = $this->nationalToInternational($digits, strtoupper(trim($countryId)));
            if ($digits === '') {
                return '';
            }
        }
        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+' . $digits : '';
    }

    private function nationalToInternational(string $digits, string $countryId): string
    {
        if (!isset(self::COUNTRIES[$countryId])) {
            return '';
        }
        [$code, $trunks, $maxNational] = self::COUNTRIES[$countryId];
        foreach ($trunks as $trunk) {
            if (str_starts_with($digits, $trunk)) {
                return $code . substr($digits, strlen($trunk));
            }
        }
        // Typed with the country code but without "+" or "00" (for example 447700900123).
        if (str_starts_with($digits, $code) && ($trunks !== [] || strlen($digits) > $maxNational)) {
            return $digits;
        }
        return $code . $digits;
    }

    private function tidy(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($value)) ?? '');
    }
}
