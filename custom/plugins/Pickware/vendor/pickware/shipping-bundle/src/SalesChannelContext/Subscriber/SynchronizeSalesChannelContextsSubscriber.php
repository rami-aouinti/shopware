<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\SalesChannelContext\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\ShippingBundle\SalesChannelContext\Model\SalesChannelApiContextDefinition;
use Pickware\ShippingBundle\SalesChannelContext\Model\SalesChannelApiContextEntity;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SynchronizeSalesChannelContextsSubscriber implements EventSubscriberInterface
{
    public const PAYLOAD_KEY_DELIMITER = '__';
    public const SYNCHRONIZATION_PREFIX = 'pickware' . self::PAYLOAD_KEY_DELIMITER;
    public const PICKWARE_SALES_CHANNEL_CONTEXT_EXTENSION_NAME = 'pickwareSalesChannelContext';

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelContextSwitchEvent::class => 'onSalesChannelContextSwitched',
            StorefrontRenderEvent::class => 'onStorefrontPageRendered',
        ];
    }

    /**
     * This method persists all request parameters in the PickwareSalesChannelContext that were passed to the
     * ContextSwitchRoute (where the event is thrown) and that start with 'pickware__'.
     *
     * The following example highlights the style in which the parameters are persisted:
     *  Request Parameters:
     *      'pickware__some_package__some_key': 'some-value'
     *      'pickware__some_package__some_other_key': 'a-second-value'
     *      'pickware__some_other_package__some_key': 'a-third-value'
     *  Value persisted in the pickware sales channel context:
     *      [
     *          'some_package' => [
     *              'some_key' => 'some-value',
     *              'some_other_key' => 'a-second-value'
     *          ],
     *          'some_other_package' => [
     *              'some_key' => 'a-third-value'
     *          ]
     *      ]
     */
    public function onSalesChannelContextSwitched(SalesChannelContextSwitchEvent $event): void
    {
        $requestPayload = $event->getRequestDataBag()->all();
        $flattenedPickwarePayload = array_filter($requestPayload, fn(string $key) => str_starts_with($key, self::SYNCHRONIZATION_PREFIX), ARRAY_FILTER_USE_KEY);

        if (count($flattenedPickwarePayload) === 0) {
            return;
        }

        $pickwarePayload = array_merge_recursive(...array_map(function(string $key) use ($flattenedPickwarePayload) {
            $keyWithoutPrefix = str_replace(self::SYNCHRONIZATION_PREFIX, '', $key);

            return $this->unflattenKeyPayload(explode(self::PAYLOAD_KEY_DELIMITER, $keyWithoutPrefix), $flattenedPickwarePayload[$key]);
        }, array_keys($flattenedPickwarePayload)));

        /** @var ?SalesChannelApiContextEntity $existingPickwareSalesChannelContext */
        $existingPickwareSalesChannelContext = $this->entityManager->findByPrimaryKey(
            SalesChannelApiContextDefinition::class,
            $event->getSalesChannelContext()->getToken(),
            $event->getContext(),
        );

        $this->entityManager->upsert(
            SalesChannelApiContextDefinition::class,
            [
                [
                    'salesChannelContextToken' => $event->getSalesChannelContext()->getToken(),
                    'payload' => array_replace_recursive(
                        $existingPickwareSalesChannelContext?->getPayload() ?? [],
                        $pickwarePayload,
                    ),
                ],
            ],
            $event->getContext(),
        );
    }

    /**
     * Returns a nested array, where each keyPart is used as key once in the order they are contained in the parameter.
     * Example:
     *  Parameters:
     *      keyParts => ['dhl_some_package', 'some_content_key']
     *      value => 'some-value'
     *  Returns:
     *      [
     *          'dhl_some_package' => [
     *              'some_content_key' => 'some-value'
     *          ]
     *      ]
     *
     * @return array[]|string[]
     */
    private function unflattenKeyPayload(array $keyParts, string $value): array
    {
        if (count($keyParts) === 0) {
            return [$value];
        }

        if (count($keyParts) === 1) {
            return [$keyParts[0] => $value];
        }

        return [$keyParts[0] => $this->unflattenKeyPayload(array_slice($keyParts, 1), $value)];
    }

    public function onStorefrontPageRendered(StorefrontRenderEvent $event): void
    {
        /** @var SalesChannelApiContextEntity $pickwareSalesChannelContext */
        $pickwareSalesChannelContext = $this->entityManager->findByPrimaryKey(
            SalesChannelApiContextDefinition::class,
            $event->getSalesChannelContext()->getToken(),
            $event->getContext(),
        );

        if (!$pickwareSalesChannelContext) {
            return;
        }

        $event->getSalesChannelContext()->addExtension(
            self::PICKWARE_SALES_CHANNEL_CONTEXT_EXTENSION_NAME,
            $pickwareSalesChannelContext,
        );
    }
}
