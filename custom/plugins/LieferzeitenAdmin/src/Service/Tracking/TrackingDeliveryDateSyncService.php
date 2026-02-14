<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Tracking;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

readonly class TrackingDeliveryDateSyncService
{
    private const TERMINAL_STATUSES = ['delivered', 'completed'];

    /** @var list<string> */
    private array $carriers;

    /**
     * @param list<string> $carriers
     */
    public function __construct(
        private Connection $connection,
        private TrackingHistoryService $trackingHistoryService,
        private LoggerInterface $logger,
        array $carriers = ['dhl', 'gls'],
    ) {
        $this->carriers = $carriers;
    }

    public function sync(Context $context): void
    {
        unset($context);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT p.id AS paket_id, p.external_order_id, sh.sendenummer
             FROM lieferzeiten_sendenummer_history sh
             INNER JOIN lieferzeiten_position pos ON pos.id = sh.position_id
             INNER JOIN lieferzeiten_paket p ON p.id = pos.paket_id
             WHERE sh.sendenummer IS NOT NULL AND sh.sendenummer <> ""'
        );

        /** @var array<string, list<\DateTimeImmutable>> $paketTerminalDates */
        $paketTerminalDates = [];

        /** @var array<string, string> $paketOrderMap */
        $paketOrderMap = [];

        foreach ($rows as $row) {
            $paketId = (string) ($row['paket_id'] ?? '');
            $orderId = (string) ($row['external_order_id'] ?? '');
            $trackingNumber = trim((string) ($row['sendenummer'] ?? ''));

            if ($paketId === '' || $trackingNumber === '') {
                continue;
            }

            $paketOrderMap[$paketId] = $orderId;

            foreach ($this->resolveTerminalDates($trackingNumber) as $terminalDate) {
                $paketTerminalDates[$paketId] ??= [];
                $paketTerminalDates[$paketId][] = $terminalDate;
            }
        }

        foreach ($paketTerminalDates as $paketId => $terminalDates) {
            $lastTerminalDate = $this->maxDate($terminalDates);
            if ($lastTerminalDate === null) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE lieferzeiten_paket SET delivery_date = :deliveryDate WHERE id = :paketId',
                [
                    'deliveryDate' => $lastTerminalDate->format(DATE_ATOM),
                    'paketId' => $paketId,
                ]
            );
        }

        $this->propagateOrderDeliveryDate(array_unique(array_values($paketOrderMap)));
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private function resolveTerminalDates(string $trackingNumber): array
    {
        foreach ($this->carriers as $carrier) {
            try {
                $response = $this->trackingHistoryService->fetchHistory($carrier, $trackingNumber);
            } catch (\Throwable $exception) {
                $this->logger->warning('Tracking history lookup failed.', [
                    'carrier' => $carrier,
                    'trackingNumber' => $trackingNumber,
                    'error' => $exception->getMessage(),
                ]);
                continue;
            }

            if (($response['ok'] ?? false) !== true) {
                continue;
            }

            $events = is_array($response['events'] ?? null) ? $response['events'] : [];

            return $this->extractTerminalDates($events);
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return list<\DateTimeImmutable>
     */
    private function extractTerminalDates(array $events): array
    {
        $terminalDates = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $status = mb_strtolower(trim((string) ($event['status'] ?? '')));
            if (!in_array($status, self::TERMINAL_STATUSES, true)) {
                continue;
            }

            $timestamp = (string) ($event['timestamp'] ?? '');
            if ($timestamp === '') {
                continue;
            }

            try {
                $terminalDates[] = new \DateTimeImmutable($timestamp);
            } catch (\Throwable) {
            }
        }

        return $terminalDates;
    }

    /** @param list<\DateTimeImmutable> $dates */
    private function maxDate(array $dates): ?\DateTimeImmutable
    {
        if ($dates === []) {
            return null;
        }

        usort($dates, static fn (\DateTimeImmutable $left, \DateTimeImmutable $right): int => $left <=> $right);

        return end($dates) ?: null;
    }

    /** @param list<string> $externalOrderIds */
    private function propagateOrderDeliveryDate(array $externalOrderIds): void
    {
        foreach ($externalOrderIds as $externalOrderId) {
            if ($externalOrderId === '') {
                continue;
            }

            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, delivery_date FROM lieferzeiten_paket WHERE external_order_id = :externalOrderId',
                ['externalOrderId' => $externalOrderId]
            );

            if ($rows === []) {
                continue;
            }

            $deliveryDates = [];
            foreach ($rows as $row) {
                $deliveryDate = (string) ($row['delivery_date'] ?? '');
                if ($deliveryDate === '') {
                    $deliveryDates = [];
                    break;
                }

                try {
                    $deliveryDates[] = new \DateTimeImmutable($deliveryDate);
                } catch (\Throwable) {
                    $deliveryDates = [];
                    break;
                }
            }

            $orderDeliveryDate = $this->maxDate($deliveryDates);
            if ($orderDeliveryDate === null) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE lieferzeiten_paket SET delivery_date = :deliveryDate WHERE external_order_id = :externalOrderId',
                [
                    'deliveryDate' => $orderDeliveryDate->format(DATE_ATOM),
                    'externalOrderId' => $externalOrderId,
                ]
            );
        }
    }
}

