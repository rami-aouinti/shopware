<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class DemoDataSeederService
{
    private const DOMAINS = ['First Medical', 'E-Commerce', 'Medical Solutions'];

    /**
     * Official external order ID demo format: DEMO-{CHANNEL}-{NNN} (e.g. DEMO-B2B-001).
     * Keep this prefix aligned with ExternalOrders\Service\FakeExternalOrderProvider::DEMO_ORDER_PREFIX.
     */
    private const ORDER_PREFIX = 'DEMO-';

    public function __construct(
        private readonly Connection $connection,
        private readonly LieferzeitenExternalOrderLinkService $externalOrderLinkService,
    ) {
    }


    public function hasDemoData(): bool
    {
        $checks = [
            [
                'sql' => 'SELECT 1 FROM `lieferzeiten_paket` WHERE external_order_id LIKE :prefix LIMIT 1',
                'params' => ['prefix' => self::ORDER_PREFIX . '%'],
            ],
            [
                'sql' => 'SELECT 1 FROM `lieferzeiten_channel_settings` WHERE sales_channel_id LIKE :prefix OR last_changed_by = :changedBy LIMIT 1',
                'params' => ['prefix' => 'demo_%', 'changedBy' => 'demo.seeder'],
            ],
            [
                'sql' => 'SELECT 1 FROM `lieferzeiten_task_assignment_rule` WHERE name LIKE :prefix OR trigger_key LIKE :prefix LIMIT 1',
                'params' => ['prefix' => 'demo_%'],
            ],
            [
                'sql' => 'SELECT 1 FROM `lieferzeiten_notification_toggle` WHERE code LIKE :prefix OR trigger_key LIKE :prefix LIMIT 1',
                'params' => ['prefix' => 'demo_%'],
            ],
        ];

        foreach ($checks as $check) {
            $result = $this->connection->fetchOne($check['sql'], $check['params']);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function removeDemoData(Context $context): array
    {
        $deleted = [];

        $this->connection->transactional(function () use (&$deleted): void {
            $deleted = $this->cleanup();
        });

        return [
            'status' => 'ok',
            'deleted' => $deleted,
            'created' => [],
            'message' => 'Demo data removed successfully.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function seed(Context $context, bool $reset = false): array
    {
        $created = [];
        $deleted = [];
        $linkResult = ['linked' => 0, 'missingIds' => []];

        $this->connection->transactional(function () use ($reset, &$created, &$deleted, &$linkResult): void {
            if ($reset) {
                $deleted = $this->cleanup();
            }

            $created = $this->insertDemoData();
            $linkResult = $this->externalOrderLinkService->linkDemoExternalOrders($this->buildExpectedDemoExternalOrderIds());
        });

        return [
            'status' => 'ok',
            'reset' => $reset,
            'deleted' => $deleted,
            'created' => $created,
            'linking' => $linkResult,
            'message' => 'Demo data generated successfully.',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function cleanup(): array
    {
        $orderPrefixParam = self::ORDER_PREFIX . '%';

        $counts = [
            'paket' => 0,
            'position' => 0,
            'lieferterminLieferantHistory' => 0,
            'neuerLieferterminHistory' => 0,
            'sendenummerHistory' => 0,
            'channelSettings' => 0,
            'notificationToggles' => 0,
            'notificationEvents' => 0,
            'taskAssignmentRules' => 0,
            'tasks' => 0,
            'auditLogs' => 0,
        ];

        $demoPaketIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM `lieferzeiten_paket` WHERE external_order_id LIKE :prefix',
            ['prefix' => $orderPrefixParam],
        );

        if ($demoPaketIds !== []) {
            $placeholders = implode(',', array_fill(0, count($demoPaketIds), '?'));

            $demoPositionIds = $this->connection->fetchFirstColumn(
                sprintf('SELECT id FROM `lieferzeiten_position` WHERE paket_id IN (%s)', $placeholders),
                $demoPaketIds,
            );

            if ($demoPositionIds !== []) {
                $positionPlaceholders = implode(',', array_fill(0, count($demoPositionIds), '?'));
                $counts['lieferterminLieferantHistory'] = $this->connection->executeStatement(
                    sprintf('DELETE FROM `lieferzeiten_liefertermin_lieferant_history` WHERE position_id IN (%s)', $positionPlaceholders),
                    $demoPositionIds,
                );
                $counts['neuerLieferterminHistory'] = $this->connection->executeStatement(
                    sprintf('DELETE FROM `lieferzeiten_neuer_liefertermin_history` WHERE position_id IN (%s)', $positionPlaceholders),
                    $demoPositionIds,
                );
                $counts['sendenummerHistory'] = $this->connection->executeStatement(
                    sprintf('DELETE FROM `lieferzeiten_sendenummer_history` WHERE position_id IN (%s)', $positionPlaceholders),
                    $demoPositionIds,
                );
                $counts['position'] = $this->connection->executeStatement(
                    sprintf('DELETE FROM `lieferzeiten_position` WHERE id IN (%s)', $positionPlaceholders),
                    $demoPositionIds,
                );
            }

            $counts['paket'] = $this->connection->executeStatement(
                sprintf('DELETE FROM `lieferzeiten_paket` WHERE id IN (%s)', $placeholders),
                $demoPaketIds,
            );
        }

        $counts['notificationEvents'] = $this->connection->executeStatement(
            'DELETE FROM `lieferzeiten_notification_event` WHERE event_key LIKE :prefix',
            ['prefix' => 'demo:%'],
        );
        $counts['channelSettings'] = $this->connection->executeStatement(
            'DELETE FROM `lieferzeiten_channel_settings` WHERE sales_channel_id LIKE :prefix OR last_changed_by = :changedBy',
            ['prefix' => 'demo_%', 'changedBy' => 'demo.seeder'],
        );
        $counts['notificationToggles'] = $this->connection->executeStatement(
            'DELETE FROM `lieferzeiten_notification_toggle` WHERE code LIKE :prefix OR trigger_key LIKE :prefix',
            ['prefix' => 'demo_%'],
        );
        $counts['taskAssignmentRules'] = $this->connection->executeStatement(
            'DELETE FROM `lieferzeiten_task_assignment_rule` WHERE name LIKE :prefix OR trigger_key LIKE :prefix',
            ['prefix' => 'demo_%'],
        );
        $counts['tasks'] = $this->connection->executeStatement(
            'DELETE FROM `lieferzeiten_task` WHERE payload LIKE :prefix',
            ['prefix' => '%"externalOrderId":"' . self::ORDER_PREFIX . '%'],
        );
        $counts['auditLogs'] = $this->connection->executeStatement(
            'DELETE FROM `lieferzeiten_audit_log` WHERE payload LIKE :prefix',
            ['prefix' => '%"externalOrderId":"' . self::ORDER_PREFIX . '%'],
        );

        return $counts;
    }

    /**
     * @return array<int, string>
     */
    private function buildExpectedDemoExternalOrderIds(): array
    {
        return [
            self::ORDER_PREFIX . '1001',
            self::ORDER_PREFIX . '1002',
            self::ORDER_PREFIX . '1003',
            self::ORDER_PREFIX . '1004',
            self::ORDER_PREFIX . '1005',
            self::ORDER_PREFIX . '1006',
            self::ORDER_PREFIX . '1007',
            self::ORDER_PREFIX . '1008',
            self::ORDER_PREFIX . '1999',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function insertDemoData(): array
    {
        $counts = [
            'paket' => 0,
            'position' => 0,
            'lieferterminLieferantHistory' => 0,
            'neuerLieferterminHistory' => 0,
            'sendenummerHistory' => 0,
            'channelSettings' => 0,
            'notificationToggles' => 0,
            'notificationEvents' => 0,
            'taskAssignmentRules' => 0,
            'tasks' => 0,
            'auditLogs' => 0,
        ];

        $now = new \DateTimeImmutable('now');
        $datasets = $this->buildOrderDataset($now);

        foreach ($datasets as $dataset) {
            $paketId = $this->uuidBytes();
            $this->connection->insert('lieferzeiten_paket', [
                'id' => $paketId,
                'paket_number' => $dataset['paketNumber'],
                'external_order_id' => $dataset['externalOrderId'],
                'source_system' => $dataset['domain'],
                'status' => (string) $dataset['status'],
                'shipping_assignment_type' => $dataset['shippingAssignmentType'],
                'partial_shipment_quantity' => $dataset['partialShipmentQuantity'],
                'order_date' => $dataset['orderDate']->format('Y-m-d H:i:s'),
                'shipping_date' => $dataset['shippingDate']->format('Y-m-d H:i:s'),
                'delivery_date' => $dataset['deliveryDate']->format('Y-m-d H:i:s'),
                'business_date_from' => $dataset['businessFrom']->format('Y-m-d H:i:s'),
                'business_date_to' => $dataset['businessTo']->format('Y-m-d H:i:s'),
                'payment_date' => $dataset['paymentDate']->format('Y-m-d H:i:s'),
                'calculated_delivery_date' => $dataset['calculatedDeliveryDate']->format('Y-m-d H:i:s'),
                'is_test_order' => $dataset['isTestOrder'] ? 1 : 0,
                'last_changed_by' => 'demo.seeder',
                'last_changed_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            ++$counts['paket'];

            foreach ($dataset['positions'] as $index => $positionData) {
                $positionId = $this->uuidBytes();
                $this->connection->insert('lieferzeiten_position', [
                    'id' => $positionId,
                    'paket_id' => $paketId,
                    'position_number' => sprintf('%s-%d', $dataset['externalOrderId'], $index + 1),
                    'article_number' => sprintf('SKU-%d%d', $dataset['status'], $index + 1),
                    'status' => $positionData['status'],
                    'ordered_at' => $dataset['orderDate']->format('Y-m-d H:i:s'),
                    'last_changed_by' => 'demo.seeder',
                    'last_changed_at' => $now->format('Y-m-d H:i:s'),
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);
                ++$counts['position'];

                $this->connection->insert('lieferzeiten_liefertermin_lieferant_history', [
                    'id' => $this->uuidBytes(),
                    'position_id' => $positionId,
                    'liefertermin_from' => $positionData['supplierFrom']->format('Y-m-d H:i:s'),
                    'liefertermin_to' => $positionData['supplierTo']->format('Y-m-d H:i:s'),
                    'last_changed_by' => 'demo.seeder',
                    'last_changed_at' => $now->format('Y-m-d H:i:s'),
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);
                ++$counts['lieferterminLieferantHistory'];

                $this->connection->insert('lieferzeiten_neuer_liefertermin_history', [
                    'id' => $this->uuidBytes(),
                    'position_id' => $positionId,
                    'liefertermin_from' => $positionData['newFrom']->format('Y-m-d H:i:s'),
                    'liefertermin_to' => $positionData['newTo']->format('Y-m-d H:i:s'),
                    'last_changed_by' => 'demo.seeder',
                    'last_changed_at' => $now->format('Y-m-d H:i:s'),
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);
                ++$counts['neuerLieferterminHistory'];

                if ($positionData['trackingNumber'] !== null) {
                    $this->connection->insert('lieferzeiten_sendenummer_history', [
                        'id' => $this->uuidBytes(),
                        'position_id' => $positionId,
                        'sendenummer' => $positionData['trackingNumber'],
                        'last_changed_by' => 'demo.seeder',
                        'last_changed_at' => $now->format('Y-m-d H:i:s'),
                        'created_at' => $now->format('Y-m-d H:i:s'),
                    ]);
                    ++$counts['sendenummerHistory'];
                }
            }

            $this->connection->insert('lieferzeiten_task', [
                'id' => $this->uuidBytes(),
                'status' => $dataset['taskStatus'],
                'assignee' => $dataset['taskAssignee'],
                'due_date' => $dataset['deliveryDate']->format('Y-m-d H:i:s'),
                'initiator' => 'demo.seeder',
                'payload' => json_encode([
                    'externalOrderId' => $dataset['externalOrderId'],
                    'sourceSystem' => $dataset['domain'],
                    'taskType' => $dataset['taskType'],
                ], JSON_THROW_ON_ERROR),
                'closed_at' => $dataset['taskStatus'] === 'closed' ? $now->format('Y-m-d H:i:s') : null,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            ++$counts['tasks'];

            $this->connection->insert('lieferzeiten_notification_event', [
                'id' => $this->uuidBytes(),
                'event_key' => sprintf('demo:%s:%s', $dataset['externalOrderId'], $dataset['status']),
                'trigger_key' => 'demo_shipping_delay',
                'channel' => 'email',
                'external_order_id' => $dataset['externalOrderId'],
                'source_system' => $dataset['domain'],
                'payload' => json_encode(['message' => 'Demo event', 'externalOrderId' => $dataset['externalOrderId']], JSON_THROW_ON_ERROR),
                'status' => 'queued',
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            ++$counts['notificationEvents'];

            $this->connection->insert('lieferzeiten_audit_log', [
                'id' => $this->uuidBytes(),
                'action' => 'demo_data_seeded',
                'target_type' => 'lieferzeiten_paket',
                'target_id' => $dataset['externalOrderId'],
                'source_system' => $dataset['domain'],
                'user_id' => 'demo.seeder',
                'correlation_id' => 'demo-seeder',
                'payload' => json_encode(['externalOrderId' => $dataset['externalOrderId']], JSON_THROW_ON_ERROR),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            ++$counts['auditLogs'];
        }

        foreach ([
            ['sales_channel_id' => 'demo_main_storefront', 'default_status' => 'open', 'enable_notifications' => 1, 'shipping_working_days' => 0, 'shipping_cutoff' => '14:00', 'delivery_working_days' => 2, 'delivery_cutoff' => '14:00'],
            ['sales_channel_id' => 'demo_b2b_storefront', 'default_status' => 'closed', 'enable_notifications' => 0, 'shipping_working_days' => 1, 'shipping_cutoff' => '12:00', 'delivery_working_days' => 3, 'delivery_cutoff' => '12:00'],
            ['sales_channel_id' => 'demo_marketplace', 'default_status' => 'open', 'enable_notifications' => 1, 'shipping_working_days' => 0, 'shipping_cutoff' => '16:00', 'delivery_working_days' => 1, 'delivery_cutoff' => '16:00'],
        ] as $channelSetting) {
            $this->connection->insert('lieferzeiten_channel_settings', [
                'id' => $this->uuidBytes(),
                'sales_channel_id' => $channelSetting['sales_channel_id'],
                'default_status' => $channelSetting['default_status'],
                'enable_notifications' => $channelSetting['enable_notifications'],
                'shipping_working_days' => $channelSetting['shipping_working_days'],
                'shipping_cutoff' => $channelSetting['shipping_cutoff'],
                'delivery_working_days' => $channelSetting['delivery_working_days'],
                'delivery_cutoff' => $channelSetting['delivery_cutoff'],
                'last_changed_by' => 'demo.seeder',
                'last_changed_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            ++$counts['channelSettings'];
        }

        foreach ([
            ['trigger_key' => 'demo_shipping_delay', 'channel' => 'email', 'enabled' => 1],
            ['trigger_key' => 'demo_delivery_delay', 'channel' => 'slack', 'enabled' => 1],
            ['trigger_key' => 'demo_eigenversand_alert', 'channel' => 'email', 'enabled' => 0],
        ] as $toggle) {
            $this->connection->insert('lieferzeiten_notification_toggle', [
                'id' => $this->uuidBytes(),
                'code' => $toggle['trigger_key'] . ':' . $toggle['channel'],
                'trigger_key' => $toggle['trigger_key'],
                'channel' => $toggle['channel'],
                'enabled' => $toggle['enabled'],
                'last_changed_by' => 'demo.seeder',
                'last_changed_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            ++$counts['notificationToggles'];
        }

        foreach ([
            ['name' => 'demo_rule_shipping_delay', 'trigger_key' => 'demo_shipping_delay', 'status' => 'open', 'priority' => 100],
            ['name' => 'demo_rule_delivery_delay', 'trigger_key' => 'demo_delivery_delay', 'status' => 'open', 'priority' => 90],
            ['name' => 'demo_rule_eigenversand', 'trigger_key' => 'demo_eigenversand_alert', 'status' => 'closed', 'priority' => 80],
        ] as $rule) {
            $this->connection->insert('lieferzeiten_task_assignment_rule', [
                'id' => $this->uuidBytes(),
                'name' => $rule['name'],
                'status' => $rule['status'],
                'trigger_key' => $rule['trigger_key'],
                'assignee_type' => 'team',
                'assignee_identifier' => 'ops-team',
                'priority' => $rule['priority'],
                'active' => 1,
                'conditions' => json_encode(['demo' => true, 'trigger' => $rule['trigger_key']], JSON_THROW_ON_ERROR),
                'last_changed_by' => 'demo.seeder',
                'last_changed_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            ++$counts['taskAssignmentRules'];
        }

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOrderDataset(\DateTimeImmutable $base): array
    {
        return [
            $this->buildOrder('1001', self::DOMAINS[0], 1, 'dhl', false, false, 'open', $base->modify('-2 days')),
            $this->buildOrder('1002', self::DOMAINS[1], 2, 'gls', false, true, 'open', $base->modify('-3 days')),
            $this->buildOrder('1003', self::DOMAINS[2], 3, 'eigenversand', false, false, 'open', $base->modify('-4 days')),
            $this->buildOrder('1004', self::DOMAINS[0], 4, 'dhl', true, false, 'closed', $base->modify('-5 days')),
            $this->buildOrder('1005', self::DOMAINS[1], 5, 'gls', false, true, 'open', $base->modify('-6 days')),
            $this->buildOrder('1006', self::DOMAINS[2], 6, 'eigenversand', true, false, 'closed', $base->modify('-7 days')),
            $this->buildOrder('1007', self::DOMAINS[0], 7, 'dhl', false, true, 'open', $base->modify('-8 days')),
            $this->buildOrder('1008', self::DOMAINS[1], 8, 'gls', true, true, 'closed', $base->modify('-9 days')),
            $this->buildOrder('1999', self::DOMAINS[2], 8, 'dhl', false, false, 'open', $base->modify('-1 day'), true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrder(
        string $suffix,
        string $domain,
        int $status,
        string $shippingType,
        bool $closed,
        bool $overdue,
        string $taskStatus,
        \DateTimeImmutable $orderDate,
        bool $isTestOrder = false,
    ): array {
        $shippingDate = $overdue ? $orderDate->modify('-1 day') : $orderDate->modify('+2 days');
        $deliveryDate = $overdue ? $orderDate->modify('-1 day') : $orderDate->modify('+4 days');
        $supplierFrom = $orderDate->modify('+1 day');
        $supplierTo = $orderDate->modify('+5 days');

        return [
            'externalOrderId' => self::ORDER_PREFIX . $suffix,
            'paketNumber' => 'SAN6-' . $suffix,
            'domain' => $domain,
            'status' => (string) $status,
            'shippingAssignmentType' => $shippingType,
            'partialShipmentQuantity' => $shippingType === 'eigenversand' ? '1/1' : '2/3',
            'orderDate' => $orderDate,
            'shippingDate' => $shippingDate,
            'deliveryDate' => $deliveryDate,
            'businessFrom' => $orderDate,
            'businessTo' => $orderDate->modify('+6 days'),
            'paymentDate' => $orderDate->modify('-1 day'),
            'calculatedDeliveryDate' => $orderDate->modify('+5 days'),
            'isTestOrder' => $isTestOrder,
            'taskStatus' => $taskStatus,
            'taskAssignee' => $closed ? 'qa-team' : 'ops-team',
            'taskType' => $overdue ? 'overdue_followup' : 'status_check',
            'positions' => [
                [
                    'status' => $closed ? 'closed' : 'open',
                    'supplierFrom' => $supplierFrom,
                    'supplierTo' => $supplierTo,
                    'newFrom' => $supplierFrom->modify('+1 day'),
                    'newTo' => $supplierFrom->modify('+2 days'),
                    'trackingNumber' => $shippingType === 'eigenversand' ? null : strtoupper($shippingType) . '-' . $suffix,
                ],
            ],
        ];
    }

    private function uuidBytes(): string
    {
        return hex2bin(Uuid::randomHex()) ?: random_bytes(16);
    }
}
