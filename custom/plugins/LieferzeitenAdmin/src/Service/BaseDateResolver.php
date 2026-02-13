<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

class BaseDateResolver
{
    /**
     * @param array<string,mixed> $payload
     * @return array{baseDate:?\DateTimeImmutable,baseDateType:string,missingPaymentDate:bool}
     */
    public function resolve(array $payload): array
    {
        $paymentMethod = mb_strtolower((string) ($payload['paymentMethod'] ?? ''));
        $isPrepayment = str_contains($paymentMethod, 'vorkasse') || str_contains($paymentMethod, 'prepayment');

        $orderDate = $this->parseDate($payload['orderDate'] ?? $payload['date'] ?? null);
        $paymentDate = $this->parseDate($payload['paymentDate'] ?? null);

        if ($isPrepayment) {
            if ($paymentDate !== null) {
                return ['baseDate' => $paymentDate, 'baseDateType' => 'payment_date', 'missingPaymentDate' => false];
            }

            return ['baseDate' => $orderDate, 'baseDateType' => 'order_date_fallback', 'missingPaymentDate' => true];
        }

        return ['baseDate' => $orderDate, 'baseDateType' => 'order_date', 'missingPaymentDate' => false];
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
