<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\Integration\IntegrationContractValidator;
use PHPUnit\Framework\TestCase;

class IntegrationContractValidatorTest extends TestCase
{
    public function testValidateApiPayloadAcceptsValidShopwarePayload(): void
    {
        $validator = new IntegrationContractValidator();

        $violations = $validator->validateApiPayload('shopware', [
            'id' => 'SW-123',
            'status' => '5',
            'date' => '2026-02-10T12:00:00+00:00',
        ]);

        static::assertSame([], $violations);
    }

    public function testValidateApiPayloadRejectsInvalidSan6Payload(): void
    {
        $validator = new IntegrationContractValidator();

        $violations = $validator->validateApiPayload('san6', [
            'orderNumber' => '',
            'shippingDate' => null,
        ]);

        static::assertCount(2, $violations);
        static::assertSame('Missing required field: orderNumber', $violations[0]);
        static::assertSame('Missing required field: shippingDate|deliveryDate|parcels', $violations[1]);
    }

    public function testValidatePersistencePayloadRequiresPaketMinimumFields(): void
    {
        $validator = new IntegrationContractValidator();

        $violations = $validator->validatePersistencePayload('paket', [
            'orderNumber' => '100045',
            'sourceSystem' => 'shopware',
        ]);

        static::assertCount(1, $violations);
        static::assertSame('Missing required field: paketNumber|packageNumber|orderNumber', $violations[0]);
    }

    public function testResolveValueByPriorityUsesSan6ThenTrackingThenShop(): void
    {
        $validator = new IntegrationContractValidator();

        static::assertSame('san6', $validator->resolveValueByPriority('shopware', 'dhl', 'san6'));
        static::assertSame('dhl', $validator->resolveValueByPriority('shopware', 'dhl', null));
        static::assertSame('shopware', $validator->resolveValueByPriority('shopware', null, ''));
    }
}
