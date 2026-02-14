<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\BaseDateResolver;
use PHPUnit\Framework\TestCase;

class BaseDateResolverTest extends TestCase
{
    public function testResolveUsesPaymentDateForPrepayment(): void
    {
        $resolver = new BaseDateResolver();

        $result = $resolver->resolve([
            'paymentMethod' => 'Vorkasse',
            'orderDate' => '2026-02-01 09:30:00',
            'paymentDate' => '2026-02-03 11:00:00',
        ]);

        static::assertSame('payment_date', $result['baseDateType']);
        static::assertFalse($result['missingPaymentDate']);
        static::assertSame('2026-02-03 11:00:00', $result['baseDate']?->format('Y-m-d H:i:s'));
    }

    public function testResolveReturnsMissingPaymentDateForPrepaymentWithoutPaymentDate(): void
    {
        $resolver = new BaseDateResolver();

        $result = $resolver->resolve([
            'paymentMethod' => 'prepayment invoice',
            'date' => '2026-02-01T09:30:00+00:00',
            'paymentDate' => null,
        ]);

        static::assertSame('payment_date_missing', $result['baseDateType']);
        static::assertTrue($result['missingPaymentDate']);
        static::assertNull($result['baseDate']);
    }

    public function testResolveUsesOrderDateForNonPrepayment(): void
    {
        $resolver = new BaseDateResolver();

        $result = $resolver->resolve([
            'paymentMethod' => 'Kreditkarte',
            'orderDate' => '2026-02-04 08:00:00',
            'paymentDate' => '2026-02-08 15:00:00',
        ]);

        static::assertSame('order_date', $result['baseDateType']);
        static::assertFalse($result['missingPaymentDate']);
        static::assertSame('2026-02-04 08:00:00', $result['baseDate']?->format('Y-m-d H:i:s'));
    }


    public function testResolveTreatsEmptyPaymentDateAsMissingForPrepayment(): void
    {
        $resolver = new BaseDateResolver();

        $result = $resolver->resolve([
            'paymentMethod' => 'Vorkasse',
            'orderDate' => '2026-02-07 12:00:00',
            'paymentDate' => ' ',
        ]);

        static::assertSame('payment_date_missing', $result['baseDateType']);
        static::assertTrue($result['missingPaymentDate']);
        static::assertNull($result['baseDate']);
    }

    public function testResolveReturnsNullWhenDatesAreInvalid(): void
    {
        $resolver = new BaseDateResolver();

        $result = $resolver->resolve([
            'paymentMethod' => 'vorkasse',
            'orderDate' => 'invalid-order-date',
            'paymentDate' => 'invalid-payment-date',
        ]);

        static::assertNull($result['baseDate']);
        static::assertSame('payment_date_missing', $result['baseDateType']);
        static::assertTrue($result['missingPaymentDate']);
    }
}
