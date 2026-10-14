<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Test\Unit\Model;

use CommerceGears\Ga4\Model\UserDataNormaliser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserDataNormaliserTest extends TestCase
{
    private UserDataNormaliser $n;

    protected function setUp(): void
    {
        $this->n = new UserDataNormaliser();
    }

    public function testEmail(): void
    {
        self::assertSame('jane.doe@example.com', $this->n->email('  Jane.Doe@Example.COM '));
        self::assertSame('janedoe@gmail.com', $this->n->email('Jane.Doe@gmail.com'));
        self::assertSame('janedoe@googlemail.com', $this->n->email('jane.doe @googlemail.com'));
        self::assertSame('', $this->n->email('not-an-email'));
        self::assertSame('', $this->n->email(''));
    }

    public function testHashIsSha256HexOfNormalisedValue(): void
    {
        self::assertSame(hash('sha256', 'jane.doe@example.com'), $this->n->hash($this->n->email(' Jane.Doe@example.com')));
        self::assertSame(64, strlen($this->n->hash('x')));
        self::assertSame('', $this->n->hash(''));
        // Known vector (sha256 of "test@example.com").
        self::assertSame('973dfe463ec85785f5f95af5ba3906eedb2d931c24e69824a89ea65dba4e813b', $this->n->hash($this->n->email('Test@Example.com')));
    }

    public function testNames(): void
    {
        self::assertSame('jane', $this->n->name('  Jane '));
        self::assertSame('obrien', $this->n->name("O'Brien"));
        self::assertSame('maryjane', $this->n->name('Mary-Jane3'));
        self::assertSame('van der berg', $this->n->name('Van  der Berg'));
        self::assertSame('çağlar', $this->n->name('ÇAĞLAR'));
        self::assertSame('müller', $this->n->name('Müller'));
    }

    public function testStreetCityRegionPostcodeCountry(): void
    {
        self::assertSame('1 test street apt 4', $this->n->street(['1 Test Street,', 'Apt. 4']));
        self::assertSame('221b baker street', $this->n->street('221B Baker Street'));
        self::assertSame('', $this->n->street(null));
        self::assertSame('london', $this->n->name(' London 1 '));
        self::assertSame('sw1a 1aa', strtolower($this->n->postalCode(' SW1A 1AA ')));
        self::assertSame('12345', $this->n->postalCode('12.345~'));
        self::assertSame('GB', $this->n->country(' gb '));
        self::assertSame('', $this->n->country('GBR'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function phoneProvider(): array
    {
        return [
            'international with spaces' => ['+44 20 7946 0000', 'GB', '+442079460000'],
            'international with 00' => ['0044 7700 900123', 'GB', '+447700900123'],
            'UK national' => ['07700 900123', 'GB', '+447700900123'],
            'UK without 0' => ['7700 900123', 'GB', '+447700900123'],
            'UK with code, no plus' => ['447700900123', 'GB', '+447700900123'],
            'Germany national' => ['030 1234567', 'DE', '+49301234567'],
            'Türkiye mobile' => ['0532 123 45 67', 'TR', '+905321234567'],
            'Switzerland' => ['044 668 18 00', 'CH', '+41446681800'],
            'Italy keeps leading 0' => ['06 1234 5678', 'IT', '+390612345678'],
            'Italy mobile starting 39' => ['392 123 4567', 'IT', '+393921234567'],
            'Spain' => ['612 34 56 78', 'ES', '+34612345678'],
            'Spain with code' => ['34612345678', 'ES', '+34612345678'],
            'Hungary 06' => ['06 30 123 4567', 'HU', '+36301234567'],
            'US 10 digits' => ['(555) 123-4567', 'US', '+15551234567'],
            'US with 1' => ['1-555-123-4567', 'US', '+15551234567'],
            'France' => ['01 23 45 67 89', 'FR', '+33123456789'],
            'unknown country, national' => ['0123 456789', 'BR', ''],
            'unknown country, international' => ['+55 11 91234 5678', 'BR', '+5511912345678'],
            'too short' => ['+44 123', 'GB', ''],
            'empty' => ['', 'GB', ''],
            'letters only' => ['n/a', 'GB', ''],
        ];
    }

    /**
     * @dataProvider phoneProvider
     */
    #[DataProvider('phoneProvider')]
    public function testPhoneE164(string $input, string $country, string $expected): void
    {
        self::assertSame($expected, $this->n->phone($input, $country));
    }
}
