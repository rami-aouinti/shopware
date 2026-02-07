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
use Pickware\MoneyBundle\Currency;
use Pickware\MoneyBundle\CurrencyConverter;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Parcel\ParcelCustomsInformation;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Shopware\Core\Framework\Context;

class ShipmentBlueprint implements JsonSerializable
{
    private Address $senderAddress;
    private Address $receiverAddress;
    private ?string $carrierTechnicalName;
    private array $shipmentConfig;
    private ?string $customerReference;

    /**
     * @var Parcel[]
     */
    private array $parcels;

    /**
     * Type of shipment / Art der Sendung
     *
     * Field number CN 23: 10
     * Field exists on CN 22: yes
     */
    private ?ShipmentType $typeOfShipment;

    /**
     * Explanation / Erklärung
     *
     * Field number CN 23: 10
     * Field exists on CN 22: no
     */
    private ?string $explanationIfTypeOfShipmentIsOther;

    /**
     * Comments / Bemerkungen
     *
     * (e.g.: Goods subject to quarantine, sanitary/phytosanitary inspection or other restrictions)
     *
     * Field number CN 23: 11
     * Field exists on CN 22: no
     */
    private ?string $comment;

    /**
     * Place of committal / Einlieferungsstelle
     *
     * Field number CN 23: field does not have a number
     * Field exists on CN 22: no
     */
    private ?string $officeOfOrigin;

    /**
     * Numbers of invoice / Nummern der Rechnung
     *
     * Field number CN 23: 14
     * Field exists on CN 22: no
     */
    private ?string $invoiceNumber;

    /**
     * Date of invoice / Datum der Rechnung
     *
     * Field number CN 23: no
     * Field exists on CN 22: no
     */
    private ?string $invoiceDate;

    /**
     * Numbers of Permits or Licences / Nummern der Genehmigungen oder Lizenzen
     *
     * Field number CN 23: 12
     * Field exists on CN 22: no
     *
     * @var string[]
     */
    private array $permitNumbers;

    /**
     * Numbers of certificates / Nummern der Bescheinigungen
     *
     * Field number CN 23: 13
     * Field exists on CN 22: no
     *
     * @var string[]
     */
    private array $certificateNumbers;

    /**
     * Shipping costs and other fees like insurance / Versand-/Portokosten und Versicherungen
     *
     * Each component of the fees (like shipping costs, insurance, ...) has to be listed separately
     *
     * Field number CN 23: 9
     * Field exists on CN 22: no
     *
     * @var Fee[]
     */
    private array $fees;

    /**
     * @param Parcel[] $parcels
     */
    public function __construct(
        array $parcels = [],
        Address $senderAddress = new Address(),
        Address $receiverAddress = new Address(),
        ?string $carrierTechnicalName = null,
        array $shipmentConfig = [],
        ?string $customerReference = null,
        ?ShipmentType $typeOfShipment = null,
        ?string $officeOfOrigin = null,
        ?string $explanationIfTypeOfShipmentIsOther = null,
        ?string $comment = null,
        ?string $invoiceNumber = null,
        ?string $invoiceDate = null,
        array $permitNumbers = [],
        array $certificateNumbers = [],
        array $fees = [],
        private ?Address $importerOfRecordsAddress = null,
        private ?string $movementReferenceNumber = null,
    ) {
        $this->parcels = $parcels;
        foreach ($parcels as $parcel) {
            new ParcelCustomsInformation($parcel, $this);
        }
        $this->senderAddress = $senderAddress;
        $this->receiverAddress = $receiverAddress;
        $this->carrierTechnicalName = $carrierTechnicalName;
        $this->shipmentConfig = $shipmentConfig;
        $this->customerReference = $customerReference;
        $this->typeOfShipment = $typeOfShipment;
        $this->officeOfOrigin = $officeOfOrigin;
        $this->explanationIfTypeOfShipmentIsOther = $explanationIfTypeOfShipmentIsOther;
        $this->comment = $comment;
        $this->invoiceNumber = $invoiceNumber;
        $this->invoiceDate = $invoiceDate;
        $this->permitNumbers = $permitNumbers;
        $this->certificateNumbers = $certificateNumbers;
        $this->fees = $fees;
    }

    public function jsonSerialize(): array
    {
        return [
            'senderAddress' => $this->senderAddress,
            'receiverAddress' => $this->receiverAddress,
            'parcels' => $this->parcels,
            'carrierTechnicalName' => $this->carrierTechnicalName,
            'shipmentConfig' => $this->shipmentConfig,
            'customerReference' => $this->customerReference,
            'typeOfShipment' => $this->typeOfShipment,
            'officeOfOrigin' => $this->officeOfOrigin,
            'explanationIfTypeOfShipmentIsOther' => $this->explanationIfTypeOfShipmentIsOther,
            'invoiceNumber' => $this->invoiceNumber,
            'invoiceDate' => $this->invoiceDate,
            'permitNumbers' => $this->permitNumbers,
            'certificateNumbers' => $this->certificateNumbers,
            'fees' => $this->fees,
            'comment' => $this->comment,
            'importerOfRecordsAddress' => $this->importerOfRecordsAddress,
            'movementReferenceNumber' => $this->movementReferenceNumber,
        ];
    }

    public static function fromArray(array $array): self
    {
        if (isset($array['parcels'][0]['customsInformation'])) {
            $array = array_merge($array, $array['parcels'][0]['customsInformation']);
            $fees = [];
            foreach ($array['fees'] as $feeType => $fee) {
                $fees[] = [
                    'type' => $feeType,
                    'amount' => $fee,
                ];
            }
            // ParcelCustomsInformation used to contain multiple invoice numbers which got refactored to a single
            // invoice number. Since some ShipmentBlueprints might still contain multiple invoice numbers, we need
            // to fallback to it if customs information exists.
            $array['invoiceNumber'] = $array['invoiceNumbers'][0] ?? $array['invoiceNumber'] ?? null;
            $array['invoiceDate'] ??= null;
            $array['comment'] ??= null;
            $array['fees'] = $fees;
            trigger_error(
                'The customs information is now expected on the shipment level instead of the parcel level. ' .
                'Please update your code to use the customs information on the shipment level.',
                E_USER_DEPRECATED,
            );
        }

        return new self(
            parcels: array_map(fn(array $parcelArray) => Parcel::fromArray($parcelArray), $array['parcels'] ?? []),
            senderAddress: is_array($array['senderAddress'] ?? null) ? Address::fromArray($array['senderAddress']) : new Address(),
            receiverAddress: is_array($array['receiverAddress'] ?? null) ? Address::fromArray($array['receiverAddress']) : new Address(),
            carrierTechnicalName: $array['carrierTechnicalName'] ?? null,
            shipmentConfig: is_array($array['shipmentConfig'] ?? null) ? $array['shipmentConfig'] : [],
            customerReference: $array['customerReference'] ?? null,
            typeOfShipment: isset($array['typeOfShipment']) ? ShipmentType::from($array['typeOfShipment']) : null,
            officeOfOrigin: $array['officeOfOrigin'] ?? null,
            explanationIfTypeOfShipmentIsOther: $array['explanationIfTypeOfShipmentIsOther'] ?? null,
            comment: $array['comment'] ?? null,
            invoiceNumber: $array['invoiceNumber'] ?? null,
            invoiceDate: $array['invoiceDate'] ?? null,
            permitNumbers: $array['permitNumbers'] ?? [],
            certificateNumbers: $array['certificateNumbers'] ?? [],
            fees: array_map(fn(array $feeArray) => Fee::fromArray($feeArray), $array['fees'] ?? []),
            importerOfRecordsAddress: is_array($array['importerOfRecordsAddress'] ?? null) ? Address::fromArray($array['importerOfRecordsAddress']) : null,
            movementReferenceNumber: $array['movementReferenceNumber'] ?? null,
        );
    }

    public function getSenderAddress(): Address
    {
        return $this->senderAddress;
    }

    public function setSenderAddress(Address $senderAddress): void
    {
        $this->senderAddress = $senderAddress;
    }

    public function getReceiverAddress(): Address
    {
        return $this->receiverAddress;
    }

    public function setReceiverAddress(Address $receiverAddress): void
    {
        $this->receiverAddress = $receiverAddress;
    }

    public function getImporterOfRecordsAddress(): ?Address
    {
        return $this->importerOfRecordsAddress;
    }

    public function setImporterOfRecordsAddress(Address $importerOfRecordsAddress): void
    {
        $this->importerOfRecordsAddress = $importerOfRecordsAddress;
    }

    public function getMovementReferenceNumber(): ?string
    {
        return $this->movementReferenceNumber;
    }

    public function setMovementReferenceNumber(?string $movementReferenceNumber): void
    {
        $this->movementReferenceNumber = $movementReferenceNumber;
    }

    /**
     * @return Parcel[]
     */
    public function getParcels(): array
    {
        return $this->parcels;
    }

    public function addParcel(Parcel $parcel): void
    {
        $this->parcels[] = $parcel;
    }

    /**
     * @param Parcel[] $parcels
     */
    public function setParcels(array $parcels): void
    {
        $this->parcels = $parcels;
        foreach ($parcels as $parcel) {
            new ParcelCustomsInformation($parcel, $this);
        }
    }

    public function getCarrierTechnicalName(): ?string
    {
        return $this->carrierTechnicalName;
    }

    public function setCarrierTechnicalName(?string $carrierTechnicalName): void
    {
        $this->carrierTechnicalName = $carrierTechnicalName;
    }

    public function getShipmentConfig(): array
    {
        return $this->shipmentConfig;
    }

    public function setShipmentConfig(array $shipmentConfig): void
    {
        $this->shipmentConfig = $shipmentConfig;
    }

    public function getCustomerReference(): ?string
    {
        return $this->customerReference;
    }

    public function setCustomerReference(?string $customerReference): void
    {
        $this->customerReference = $customerReference;
    }

    public function getTypeOfShipment(): ?ShipmentType
    {
        return $this->typeOfShipment;
    }

    public function setTypeOfShipment(?ShipmentType $typeOfShipment): void
    {
        $this->typeOfShipment = $typeOfShipment;
    }

    public function getExplanationIfTypeOfShipmentIsOther(): ?string
    {
        return $this->explanationIfTypeOfShipmentIsOther;
    }

    public function setExplanationIfTypeOfShipmentIsOther(?string $explanationIfTypeOfShipmentIsOther): void
    {
        $this->explanationIfTypeOfShipmentIsOther = $explanationIfTypeOfShipmentIsOther;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getOfficeOfOrigin(): ?string
    {
        return $this->officeOfOrigin;
    }

    public function setOfficeOfOrigin(?string $officeOfOrigin): void
    {
        $this->officeOfOrigin = $officeOfOrigin;
    }

    /**
     * @return string[]
     */
    public function getPermitNumbers(): array
    {
        return $this->permitNumbers;
    }

    /**
     * @param string[] $permitNumbers
     */
    public function setPermitNumbers(array $permitNumbers): void
    {
        $this->permitNumbers = $permitNumbers;
    }

    public function addPermitNumber(string $numberOfPermit): void
    {
        $this->permitNumbers[] = $numberOfPermit;
    }

    /**
     * @return string[]
     */
    public function getCertificateNumbers(): array
    {
        return $this->certificateNumbers;
    }

    public function setCertificateNumbers(array $certificateNumbers): void
    {
        $this->certificateNumbers = $certificateNumbers;
    }

    public function addCertificateNumber(string $numberOfCertificate): void
    {
        $this->certificateNumbers[] = $numberOfCertificate;
    }

    public function getInvoiceDate(): ?string
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?string $invoiceDate): void
    {
        if ($invoiceDate !== null && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $invoiceDate)) {
            throw new InvalidArgumentException('The invoice date must be in the format "YYYY-MM-DD".');
        }

        $this->invoiceDate = $invoiceDate;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    /**
     * @return Fee[]
     */
    public function getFees(): array
    {
        return $this->fees;
    }

    /**
     * @param Fee[] $fees
     */
    public function setFees(array $fees): void
    {
        $this->fees = $fees;
    }

    public function getTotalFeesOfType(FeeType $feeType): ?MoneyValue
    {
        $fees = array_map(
            fn(Fee $fee) => $fee->getAmount(),
            array_filter($this->fees, fn(Fee $fee) => $fee->getType() === $feeType),
        );

        return MoneyValue::sum(...$fees);
    }

    public function addFee(Fee $fee): void
    {
        $this->fees[] = $fee;
    }

    public function getTotalFees(): MoneyValue
    {
        return MoneyValue::sum(...array_values(array_map(fn(Fee $fee) => $fee->getAmount(), $this->fees)));
    }

    /**
     * Returns the total customs value of all items with the total fees ontop.
     * If any of the customs value of the items is not set, this value cannot be determined.
     */
    public function getTotalValue(): ?MoneyValue
    {
        $customsValues = array_merge(
            ...array_map(
                fn(Parcel $parcel) => array_map(fn(ParcelItem $item) => $item->getUnitPrice(), $parcel->getItems()),
                $this->parcels,
            ),
        );

        if (in_array(null, $customsValues)) {
            return null;
        }

        return MoneyValue::sum(
            $this->getTotalFees(),
            ...$customsValues,
        );
    }

    public function convertAllMoneyValuesToSameCurrency(
        CurrencyConverter $currencyConverter,
        Currency $targetCurrency,
        Context $context,
    ): void {
        $fees = array_map(
            fn(Fee $fee) => new Fee(
                type: $fee->getType(),
                amount: $currencyConverter->convertMoneyValueToCurrency($fee->getAmount(), $targetCurrency, $context),
            ),
            $this->getFees(),
        );
        $this->setFees($fees);

        foreach ($this->parcels as $parcel) {
            foreach ($parcel->getItems() as $item) {
                if (!$item->getUnitPrice()) {
                    continue;
                }
                $item->setUnitPrice($currencyConverter->convertMoneyValueToCurrency(
                    $item->getUnitPrice(),
                    $targetCurrency,
                    $context,
                ));
            }
        }
    }

    /**
     * Returns the items from all parcels, grouped by their attributes.
     *
     * @return ParcelItem[]
     */
    public function getItemsOfAllParcelsGroupByAttributes(): array
    {
        /** @var ParcelItem[] $parcelItems */
        $parcelItems = array_merge(
            ...array_map(
                fn(Parcel $parcel) => $parcel->getItems(),
                $this->parcels,
            ),
        );

        $groupedItems = [];
        foreach ($parcelItems as $item) {
            foreach ($groupedItems as $groupedItem) {
                if ($item->hasSameAttributesAs($groupedItem)) {
                    $groupedItem->setQuantity($groupedItem->getQuantity() + $item->getQuantity());

                    continue 2;
                }
            }
            $groupedItems[] = clone $item;
        }

        return array_values($groupedItems);
    }
}
