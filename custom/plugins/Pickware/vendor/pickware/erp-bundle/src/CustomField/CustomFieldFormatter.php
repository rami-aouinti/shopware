<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField;

use DateTime;
use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

class CustomFieldFormatter
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Translator $translator,
        private readonly LanguageLocaleCodeProvider $languageLocaleCodeProvider,
        private readonly CurrencyFormatter $currencyFormatter,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly MediaBase64Formatter $mediaBase64Formatter,
    ) {}

    public function formatCustomFieldValue(string $customFieldId, mixed $value, string $languageId, string $currencyId, Context $context): string
    {
        /** @var CustomFieldEntity $customField */
        $customField = $this->entityManager->getByPrimaryKey(
            CustomFieldDefinition::class,
            $customFieldId,
            $context,
        );

        $localeCode = $this->languageLocaleCodeProvider->getLocaleForLanguageId($languageId);
        $this->translator->setTranslationLocale($localeCode, $context);

        $type = $customField->getConfig()['customFieldType'] ?? $customField->getType();

        return match ($type) {
            CustomFieldTypes::BOOL,
            CustomFieldTypes::SWITCH,
            CustomFieldTypes::CHECKBOX => $this->translator->translate(sprintf('pickware-erp-starter.custom-field-value.bool.%s', $value ? 'yes' : 'no')),
            CustomFieldTypes::COLORPICKER,
            CustomFieldTypes::TEXT,
            CustomFieldTypes::NUMBER,
            CustomFieldTypes::FLOAT,
            CustomFieldTypes::INT => (string) $value,
            CustomFieldTypes::DATE => (new DateTime($value))->format('Y-m-d'),
            CustomFieldTypes::DATETIME => (new DateTime($value))->format('Y-m-d H:i'),
            CustomFieldTypes::JSON => Json::stringify($value),
            CustomFieldTypes::SELECT => $this->formatSelect($customField, $value, $localeCode),
            CustomFieldTypes::PRICE => $this->formatPrice($value, $languageId, $currencyId, $context),
            CustomFieldTypes::ENTITY => $this->formatEntity($customField, $value, $context),
            CustomFieldTypes::MEDIA => $this->formatMedia($value, $context),
            default => throw new LogicException(sprintf('Custom field with type %s is not supported.', $type)),
        };
    }

    public function isCustomFieldSupported(string $customFieldId, Context $context): bool
    {
        /** @var CustomFieldEntity $customField */
        $customField = $this->entityManager->getByPrimaryKey(
            CustomFieldDefinition::class,
            $customFieldId,
            $context,
        );
        $type = $customField->getConfig()['customFieldType'] ?? $customField->getType();

        return match ($type) {
            CustomFieldTypes::BOOL,
            CustomFieldTypes::SWITCH,
            CustomFieldTypes::CHECKBOX,
            CustomFieldTypes::COLORPICKER,
            CustomFieldTypes::TEXT,
            CustomFieldTypes::NUMBER,
            CustomFieldTypes::FLOAT,
            CustomFieldTypes::INT,
            CustomFieldTypes::DATE,
            CustomFieldTypes::DATETIME,
            CustomFieldTypes::JSON,
            CustomFieldTypes::SELECT,
            CustomFieldTypes::PRICE,
            CustomFieldTypes::ENTITY,
            CustomFieldTypes::MEDIA => true,
            default => false,
        };
    }

    public function formatCustomFieldLabel(string $customFieldId, string $languageId, Context $context): string
    {
        /** @var CustomFieldEntity $customField */
        $customField = $this->entityManager->getByPrimaryKey(
            CustomFieldDefinition::class,
            $customFieldId,
            $context,
        );
        $localeCode = $this->languageLocaleCodeProvider->getLocaleForLanguageId($languageId);

        $labels = $customField->getConfig()['label'] ?? null;
        if (is_string($labels)) {
            return $labels;
        }

        return $labels[$localeCode] ?? $labels['en-GB'] ?? $customField->getName();
    }

    private function formatSelect(CustomFieldEntity $customField, mixed $value, string $localeCode): string
    {
        $options = $customField->getConfig()['options'] ?? [];
        $selectedOptionValues = is_array($value) ? $value : [$value];
        $formattedOptions = ImmutableCollection::create($selectedOptionValues)
            ->map(fn(string $selectedOptionValue) => array_find($options, fn(array $option) => $option['value'] === $selectedOptionValue))
            ->filter(fn(?array $option) => $option !== null)
            ->map(fn(array $option) => $option['label'][$localeCode] ?? $option['label']['en-GB'] ?? $option['value'])
            ->asArray();

        return implode(', ', $formattedOptions);
    }

    private function formatPrice(PriceCollection $value, string $languageId, string $currencyId, Context $context): string
    {
        $price = $value->getCurrencyPrice($currencyId, fallback: true) ?? $value->first();
        if ($price === null) {
            return '';
        }

        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            $price->getCurrencyId(),
            $context,
        );
        $grossFormatted = $this->currencyFormatter->formatCurrencyByLanguage(
            $price->getGross(),
            $currency->getIsoCode(),
            $languageId,
            $context,
        );
        $netFormatted = $this->currencyFormatter->formatCurrencyByLanguage(
            $price->getNet(),
            $currency->getIsoCode(),
            $languageId,
            $context,
        );
        $grossLabel = $this->translator->translate('pickware-erp-starter.custom-field-value.price.gross');
        $netLabel = $this->translator->translate('pickware-erp-starter.custom-field-value.price.net');

        return sprintf('%s (%s) / %s (%s)', $grossFormatted, $grossLabel, $netFormatted, $netLabel);
    }

    private function formatEntity(CustomFieldEntity $customField, mixed $value, Context $context): string
    {
        $entityName = $customField->getConfig()['entity'];
        $entityIds = is_array($value) ? $value : [$value];
        $entities = $this->entityManager->findBy(
            $this->definitionInstanceRegistry->getByEntityName($entityName)::class,
            ['id' => $entityIds],
            $context,
        );

        // Formats entity by concatenating the values from the label properties defined in the custom field config,
        // e.g. "labelProperty": ["firstName", "lastName"] => "FirstNameValue LastNameValue", or just their "name"
        // property if no label properties are defined. This is also how shopware formats entities in the
        // sw-multi-select component in the administration.
        $formattedEntities = $entities->map(function(Entity $entity) use ($customField): string {
            $labelProperties = $customField->getConfig()['labelProperty'] ?? [];
            $labelProperties = is_array($labelProperties) ? $labelProperties : [$labelProperties];
            if (empty($labelProperties)) {
                return $entity->get('name');
            }

            return implode(' ', array_filter(array_map(fn(string $property) => $entity->get($property), $labelProperties)));
        });

        return implode(', ', $formattedEntities);
    }

    private function formatMedia(mixed $mediaId, Context $context): string
    {
        $base64Data = $this->mediaBase64Formatter->formatMedia($mediaId, $context);
        /** @var MediaEntity $media */
        $media = $this->entityManager->getByPrimaryKey(
            MediaDefinition::class,
            $mediaId,
            $context,
        );
        $safeMimeType = htmlspecialchars($media->getMimeType(), ENT_QUOTES, 'UTF-8');

        return sprintf('data:%s;base64,%s', $safeMimeType, $base64Data);
    }
}
