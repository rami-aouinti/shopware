<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use InvalidArgumentException;
use JsonSerializable;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use VIISON\AddressSplitter\AddressSplitter;
use VIISON\AddressSplitter\Exceptions\SplittingException;

class Address implements JsonSerializable
{
    public function __construct(
        private string $firstName = '',
        private string $lastName = '',
        private string $company = '',
        private string $department = '',
        private string $addressAddition = '',
        private string $street = '',
        private string $houseNumber = '',
        private string $city = '',
        private string $zipCode = '',
        /* @var string $countryIso ISO 3166-1 alpha-2 (2 character) code (e.g. DE for Germany) */
        private string $countryIso = '',
        /* @var string $stateIso ISO 3166-2 code (e.g. DE-HE for Hesse) */
        private string $stateIso = '',
        private string $phone = '',
        private string $email = '',
        /* @var string $customsReference A number to identify this person/company at the customs. E.g. the EORI-Number or TAX id. Usually not mandatory. */
        private string $customsReference = '',
    ) {}

    public static function fromShopwareOrderAddress(OrderAddressEntity $orderAddress): Address
    {
        $self = new self();

        $addressAdditions = [];

        $self->firstName = $orderAddress->getFirstName();
        $self->lastName = $orderAddress->getLastName();
        $self->company = $orderAddress->getCompany() ?: '';
        $self->department = $orderAddress->getDepartment() ?: '';

        $self->city = $orderAddress->getCity();
        $self->zipCode = $orderAddress->getZipcode() ?? '';
        $self->countryIso = $orderAddress->getCountry()?->getIso() ?? '';
        $self->stateIso = $orderAddress->getCountryState() ? $orderAddress->getCountryState()->getShortCode() : '';
        $self->phone = $orderAddress->getPhoneNumber() ?: '';

        $addressAdditions[] = $orderAddress->getAdditionalAddressLine1();
        $addressAdditions[] = $orderAddress->getAdditionalAddressLine2();

        try {
            $splitAddress = AddressSplitter::splitAddress($orderAddress->getStreet());
            $self->street = $splitAddress['streetName'];
            $self->houseNumber = $splitAddress['houseNumber'];
            $addressAdditions[] = $splitAddress['additionToAddress1'];
            $addressAdditions[] = $splitAddress['additionToAddress2'];
        } catch (SplittingException $e) {
            $self->street = $orderAddress->getStreet();
        }

        $self->addressAddition = implode('; ', array_filter($addressAdditions));

        return $self;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        foreach ($array as $key => $value) {
            if (property_exists(self::class, $key)) {
                $self->$key = (string) $value;
            }
        }

        return $self;
    }

    public function getFirstName(): string
    {
        return trim($this->firstName);
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getAddressAddition(): string
    {
        return trim($this->addressAddition);
    }

    public function setAddressAddition(string $addressAddition): void
    {
        $this->addressAddition = $addressAddition;
    }

    public function getStreet(): string
    {
        return trim($this->street);
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function getHouseNumber(): string
    {
        return trim($this->houseNumber);
    }

    public function setHouseNumber(string $houseNumber): void
    {
        $this->houseNumber = $houseNumber;
    }

    public function getZipCode(): string
    {
        return trim($this->zipCode);
    }

    public function setZipCode(string $zipCode): void
    {
        $this->zipCode = $zipCode;
    }

    public function getCountry(): ?Country
    {
        if (!$this->countryIso) {
            return null;
        }

        return new Country($this->countryIso);
    }

    /**
     * @return string ISO 3166-1 alpha-2 (2 character) code (e.g. DE for Germany)
     */
    public function getCountryIso(): string
    {
        return trim($this->countryIso);
    }

    /**
     * @param string $countryIso ISO 3166-1 alpha-2 (2 character) code (e.g. DE for Germany)
     */
    public function setCountryIso(string $countryIso): void
    {
        $this->countryIso = $countryIso;
    }

    public function getPhone(): string
    {
        return trim($this->phone);
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getEmail(): string
    {
        return trim($this->email);
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getLastName(): string
    {
        return trim($this->lastName);
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getCompany(): string
    {
        return trim($this->company);
    }

    public function setCompany(string $company): void
    {
        $this->company = $company;
    }

    public function getCity(): string
    {
        return trim($this->city);
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getDepartment(): string
    {
        return trim($this->department);
    }

    public function setDepartment(string $department): void
    {
        $this->department = $department;
    }

    public function getHouseNumberBase(): string
    {
        try {
            $splitHouseNumber = AddressSplitter::splitHouseNumber($this->houseNumber);

            return trim($splitHouseNumber['base']);
        } catch (SplittingException $e) {
            return trim($this->houseNumber);
        }
    }

    public function getHouseNumberExtension(): string
    {
        try {
            $splitHouseNumber = AddressSplitter::splitHouseNumber($this->houseNumber);

            return trim($splitHouseNumber['extension']);
        } catch (SplittingException $e) {
            return '';
        }
    }

    /**
     * @return string ISO 3166-2 code (e.g. DE-HE for Hesse)
     */
    public function getStateIso(): string
    {
        return trim($this->stateIso);
    }

    /**
     * @param string $stateIso ISO 3166-2 code (e.g. DE-HE for Hesse)
     */
    public function setStateIso(string $stateIso): void
    {
        $this->stateIso = $stateIso;
    }

    public function getCustomsReference(): string
    {
        return trim($this->customsReference);
    }

    public function setCustomsReference(string $customsReference): void
    {
        $this->customsReference = $customsReference;
    }

    public function copy(): self
    {
        return clone $this;
    }

    public function copyWithoutEmail(): self
    {
        $copy = clone $this;
        $copy->email = '';

        return $copy;
    }

    public function copyWithoutPhone(): self
    {
        $copy = clone $this;
        $copy->phone = '';

        return $copy;
    }

    /**
     * Returns an array containing all name information from the address but with as few entries as possible.
     *
     * See unit tests for examples.
     *
     * @param string[]|null $keys Use these strings as keys instead of numeric keys
     * @param bool|null $prioritizeCompanyIfSet Whether the company should be the first entry in the array if set
     * @return string[]
     */
    public function getOptimizedNameArray(?array $keys = null, ?bool $prioritizeCompanyIfSet = false): array
    {
        $names = [
            sprintf('%s %s', $this->getFirstName(), $this->getLastName()),
            sprintf('%s, %s', $this->getCompany(), $this->getDepartment()),
            $this->getAddressAddition(),
        ];

        if ($prioritizeCompanyIfSet === true) {
            $company = array_splice($names, 1, 1);
            $names = array_merge($company, $names);
        }

        $names = array_values(array_filter(array_map(fn($name) => trim($name, " \t\n\r\0\x0B,"), $names)));

        if ($keys === null) {
            return $names;
        }

        if (count($keys) !== 3) {
            throw new InvalidArgumentException(
                sprintf('Method %s currently only works with exactly 3 keys.', __METHOD__),
            );
        }

        $keys = array_slice($keys, 0, count($names));

        return array_combine($keys, $names);
    }
}
