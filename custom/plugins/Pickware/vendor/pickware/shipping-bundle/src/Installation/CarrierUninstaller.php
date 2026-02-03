<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DocumentBundle\Installation\DocumentUninstaller;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CarrierUninstaller
{
    private Connection $db;
    private DocumentUninstaller $documentUninstaller;

    public function __construct(Connection $db, DocumentUninstaller $documentUninstaller)
    {
        $this->db = $db;
        $this->documentUninstaller = $documentUninstaller;
    }

    public static function createForContainer(ContainerInterface $container): self
    {
        return new self(
            $container->get(Connection::class),
            DocumentUninstaller::createForContainer($container),
        );
    }

    public function uninstallCarrier(string $carrierTechnicalName): void
    {
        $documentIds = $this->db->fetchAllAssociative(
            'SELECT LOWER(HEX(`document_id`)) AS documentId
            FROM `pickware_shipping_document_shipment_mapping` `document_shipment_mapping`
            INNER JOIN `pickware_shipping_shipment` `shipment`
                ON `document_shipment_mapping`.`shipment_id` = `shipment`.`id`
            WHERE `shipment`.`carrier_technical_name` = :carrierTechnicalName',
            [
                'carrierTechnicalName' => $carrierTechnicalName,
            ],
        );
        $documentIds = array_column($documentIds, 'documentId');
        $this->documentUninstaller->removeDocuments($documentIds);

        $this->db->executeStatement(
            'DELETE FROM pickware_shipping_shipping_method_config
            WHERE carrier_technical_name = :carrierTechnicalName',
            ['carrierTechnicalName' => $carrierTechnicalName],
        );
        $this->db->executeStatement(
            'DELETE FROM pickware_shipping_shipment
            WHERE carrier_technical_name = :carrierTechnicalName',
            ['carrierTechnicalName' => $carrierTechnicalName],
        );
        $this->db->executeStatement(
            'DELETE FROM `pickware_shipping_carrier`
            WHERE `technical_name` = :carrierTechnicalName',
            ['carrierTechnicalName' => $carrierTechnicalName],
        );
    }
}
