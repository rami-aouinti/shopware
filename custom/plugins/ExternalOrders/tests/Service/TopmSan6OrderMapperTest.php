<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Service;

use ExternalOrders\Service\TopmSan6OrderMapper;
use PHPUnit\Framework\TestCase;

class TopmSan6OrderMapperTest extends TestCase
{
    public function testMapsNominalTopmPayloadToExternalOrderShape(): void
    {
        $mapper = new TopmSan6OrderMapper();

        $mapped = $mapper->mapOrder([
            'Auftragsnummer' => 'A-123',
            'Kundennummer' => 'C-42',
            'Kundenname' => 'Alice Muster',
            'E-Mail' => 'alice@example.org',
            'Auftragsdatum' => '21.01.2026',
            'Gesamtbetrag' => '15.50',
            'Positionen' => [
                ['Artikelnummer' => 'ARTICLE.01', 'Menge' => '2', 'Preis' => '5.00'],
            ],
        ]);

        static::assertSame('A-123', $mapped['externalId']);
        static::assertSame('A-123', $mapped['orderNumber']);
        static::assertSame('Alice Muster', $mapped['customersName']);
        static::assertSame('2026-01-21', $mapped['datePurchased']);
        static::assertSame(1, $mapped['totalItems']);
        static::assertSame('ARTICLE.01', $mapped['detail']['items'][0]['productNumber']);
        static::assertSame('01', $mapped['detail']['items'][0]['variant']);
    }


    public function testMapsPositionenPositionWithReferenzAndDateiAttachment(): void
    {
        $mapper = new TopmSan6OrderMapper();

        $mapped = $mapper->mapOrder([
            'Auftragsnummer' => 'A-456',
            'Kundenname' => 'Claire Beispiel',
            'Positionen' => [
                'Position' => [
                    [
                        'Referenz' => 'ABC 123   1',
                        'Menge' => '3',
                        'Preis' => '2.50',
                    ],
                ],
            ],
            'Anlagen' => [
                [
                    'Dateiname' => 'auftrag.txt',
                    'Datei' => base64_encode('payload'),
                ],
            ],
        ]);

        static::assertSame('ABC 123.01', $mapped['detail']['items'][0]['productNumber']);
        static::assertSame('ABC 123', $mapped['detail']['items'][0]['sku']);
        static::assertSame('01', $mapped['detail']['items'][0]['variant']);
        static::assertCount(1, $mapped['detail']['additional']['attachments']);
        static::assertSame('auftrag.txt', $mapped['detail']['additional']['attachments'][0]['name']);
        static::assertSame(base64_encode('payload'), $mapped['detail']['additional']['attachments'][0]['content']);
    }

    public function testTrimsAndNormalizesDirtyData(): void
    {
        $mapper = new TopmSan6OrderMapper();

        $largeBase64 = base64_encode(str_repeat('x', 300000));
        $mapped = $mapper->mapOrder([
            'Auftragsnummer' => '  A-999  ',
            'Kundennummer' => '  C-99 ',
            'Kundenname' => '  Bob  ',
            'E-Mail' => '  bob@example.org ',
            'Datum' => ' 20260122 ',
            'Betrag' => ' 7,50 ',
            'Positionen' => [
                ['Artikelnummer' => 'ARTICLE      1', 'Menge' => '1', 'Preis' => '7,50'],
            ],
            'Anlagen' => [
                ['Dateiname' => 'small.txt', 'InhaltBase64' => base64_encode('abc')],
                ['Dateiname' => 'large.bin', 'InhaltBase64' => $largeBase64],
            ],
        ]);

        static::assertSame('A-999', $mapped['externalId']);
        static::assertSame('bob@example.org', $mapped['customersEmailAddress']);
        static::assertSame('2026-01-22', $mapped['datePurchased']);
        static::assertSame('ARTICLE.01', $mapped['detail']['items'][0]['productNumber']);
        static::assertCount(1, $mapped['detail']['additional']['attachments']);
        static::assertCount(1, $mapped['attachmentPayload']);
    }

    public function testMapsCompleteDeliveryWithExplicitQuantities(): void
    {
        $mapper = new TopmSan6OrderMapper();

        $mapped = $mapper->mapOrder([
            'Auftragsnummer' => 'A-COMPLETE-1',
            'Positionen' => [
                [
                    'Artikelnummer' => 'COMPLETE.01',
                    'MengeBestellt' => '4',
                    'MengeGeliefert' => '4',
                    'Preis' => '10.00',
                ],
            ],
        ]);

        static::assertSame(4, $mapped['detail']['items'][0]['orderedQuantity']);
        static::assertSame(4, $mapped['detail']['items'][0]['shippedQuantity']);
        static::assertSame(4, $mapped['detail']['items'][0]['quantity']);
    }

    public function testMapsPartialDeliveryWithDistinctOrderedAndShippedQuantities(): void
    {
        $mapper = new TopmSan6OrderMapper();

        $mapped = $mapper->mapOrder([
            'Auftragsnummer' => 'A-PARTIAL-1',
            'Positionen' => [
                [
                    'Artikelnummer' => 'PARTIAL.01',
                    'Bestellmenge' => '5',
                    'GelieferteMenge' => '2',
                    'Preis' => '10.00',
                ],
            ],
        ]);

        static::assertSame(5, $mapped['detail']['items'][0]['orderedQuantity']);
        static::assertSame(2, $mapped['detail']['items'][0]['shippedQuantity']);
    }

    public function testMapsMultiParcelSplitLinesForSamePosition(): void
    {
        $mapper = new TopmSan6OrderMapper();

        $mapped = $mapper->mapOrder([
            'Auftragsnummer' => 'A-MULTI-1',
            'Positionen' => [
                [
                    'Referenz' => 'SPLIT 100 1',
                    'Bestellmenge' => '3',
                    'Versandmenge' => '1',
                    'Preis' => '10.00',
                ],
                [
                    'Referenz' => 'SPLIT 100 1',
                    'Bestellmenge' => '3',
                    'Versandmenge' => '2',
                    'Preis' => '10.00',
                ],
            ],
        ]);

        static::assertCount(2, $mapped['detail']['items']);
        static::assertSame('SPLIT 100.01', $mapped['detail']['items'][0]['productNumber']);
        static::assertSame(1, $mapped['detail']['items'][0]['shippedQuantity']);
        static::assertSame(2, $mapped['detail']['items'][1]['shippedQuantity']);
    }

}
