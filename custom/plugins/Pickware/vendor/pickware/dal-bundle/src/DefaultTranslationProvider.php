<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Doctrine\DBAL\Connection;
use Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DefaultTranslationProvider
{
    private ContainerInterface $container;
    private Connection $connection;
    private ?string $systemDefaultLocaleCode = null;

    public function __construct(
        #[Autowire(service: 'service_container')]
        ContainerInterface $container,
        Connection $connection,
    ) {
        $this->container = $container;
        $this->connection = $connection;
    }

    /**
     * When writing entities with an associated translated field (associated "_translation" table), the field serializer
     * (TranslationsAssociationFieldSerializer) checks that a translation in the system default language is present and
     * fails if such a translation is missing. Since the system default language is set when setting up shopware, it can
     * be ANY language and locale. To ensure that our entities can be created, we ensure that the system default
     * language translations are present by falling back on the en-GB translation and copying it if necessary.
     *
     * See also: https://github.com/shopware/shopware/blob/0aaa7c6ba42ac31a16176a9ae5c2c201b13aacbc/src/Core/Framework/DataAbstractionLayer/FieldSerializer/TranslationsAssociationFieldSerializer.php#L213-L218
     * Coming from: https://github.com/pickware/shopware-plugins/issues/2551
     *
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     * @param array<array<string,mixed>> $payload DAL payload i.e., an array of multiple entity payloads
     */
    public function ensureSystemDefaultTranslationInEntityWritePayload(
        string $entityDefinitionClassName,
        array &$payload,
    ): void {
        foreach ($payload as &$singleEntityPayload) {
            $this->ensureSystemDefaultTranslationInSingleEntityWritePayload(
                $entityDefinitionClassName,
                $singleEntityPayload,
            );
        }
    }

    /**
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     * @param array<string,mixed> $payload DAL payload of a single entity
     */
    private function ensureSystemDefaultTranslationInSingleEntityWritePayload(
        string $entityDefinitionClassName,
        array &$payload,
    ): void {
        /** @var EntityDefinition<Entity> $entityDefinition */
        $entityDefinition = $this->container->get($entityDefinitionClassName);

        /** @var TranslatedField $translatedField */
        foreach ($entityDefinition->getTranslatedFields() as $translatedField) {
            if (!array_key_exists($translatedField->getPropertyName(), $payload)) {
                continue;
            }

            /**
             * Common case: TranslatedField. With this translated field, the translated values are set directly at the
             * property. We ensure that the default locale is supported for each property value. The payloads look as follows:
             * [
             *   id => 'some-id',
             *   name => [
             *     'de-DE' => 'Ein Name',
             *     'en-GB' => 'Some Name',
             *   ],
             * ]
             */
            $propertyValue = $payload[$translatedField->getPropertyName()];
            $isTranslatableFieldWithTranslations = is_array($propertyValue) && (array_key_exists('de-DE', $propertyValue) || array_key_exists('en-GB', $propertyValue));
            if ($isTranslatableFieldWithTranslations) {
                $this->ensureSystemDefaultTranslations($payload[$translatedField->getPropertyName()]);
            }
        }

        // Recursive calls for association fields (immediate associations and associations from extensions)
        $associationFields = array_filter(
            array_merge(
                $entityDefinition->getFields()->getElements(),
                $entityDefinition->getExtensionFields(),
            ),
            fn(Field $field) => $field instanceof AssociationField,
        );
        foreach ($associationFields as $associationField) {
            $associatedClass = $associationField->getReferenceDefinition()->getClass();
            if (array_key_exists($associationField->getPropertyName(), $payload)) {
                $isTranslationAssociationField = $associationField instanceof TranslationsAssociationField;
                $isToManyAssociationField = $associationField instanceof OneToManyAssociationField || $associationField instanceof ManyToManyAssociationField;

                if ($isTranslationAssociationField) {
                    /**
                     * Special case: TranslationsAssociationField. With this association field, translated field values
                     * are not set directly at the property. But instead translations are bundles by locale key directly
                     * in the "translations" association field. We ensure that the default locale is supported for this
                     * association value. The payload looks as follows:
                     * [
                     *   id => 'some-id',
                     *   translations => [
                     *     'de-DE' => [ name => 'Ein Name' ],
                     *     'en-GB' => [ name => 'Some Name' ],
                     *   ],
                     * ]
                     *
                     * It is also possible to _not_ use the locale code as keys but a language id as a regular field
                     * of the associated translation entity. This case is _not supported_ as it relies on language ids
                     * rather than on locale codes. We identify this format by checking the array keys. The payload
                     * looks as follows:
                     * [
                     *   id => 'some-id',
                     *   translations => [
                     *     [
                     *       languageId => 'some-uuid-1',
                     *       name => 'Ein Name'
                     *     ],
                     *     [
                     *       languageId => 'some-uuid-2',
                     *       name => 'Some Name'
                     *     ],
                     *   ],
                     * ]
                     */
                    $areLocaleCodeKeysUsed = array_reduce(
                        array_keys($payload[$associationField->getPropertyName()]),
                        fn(bool $areLocaleCodeKeysUsed, $key) => $areLocaleCodeKeysUsed && is_string($key) && preg_match('/[a-z]{2}-[A-Z]{2}/', $key),
                        true,
                    );
                    if ($areLocaleCodeKeysUsed) {
                        $this->ensureSystemDefaultTranslations($payload[$associationField->getPropertyName()]);
                    }
                } elseif ($isToManyAssociationField && is_array($payload[$associationField->getPropertyName()])) {
                    foreach ($payload[$associationField->getPropertyName()] as &$singleAssociationPayload) {
                        $this->ensureSystemDefaultTranslationInSingleEntityWritePayload(
                            $associatedClass,
                            $singleAssociationPayload,
                        );
                    }
                } elseif (is_array($payload[$associationField->getPropertyName()])) {
                    $this->ensureSystemDefaultTranslationInSingleEntityWritePayload(
                        $associatedClass,
                        $payload[$associationField->getPropertyName()],
                    );
                }
                // If the payload is not an array (last elseif), the payload may look like the following:
                // [
                //   "someAssociation": null,
                // ]
                // [
                //   "someField": null,
                // ]
                // We do not need to further ensure translation values here and simply return.
            }
        }
    }

    /**
     * @param array<string, mixed> $translations
     */
    private function ensureSystemDefaultTranslations(array &$translations): void
    {
        if (!array_key_exists('de-DE', $translations) || !array_key_exists('en-GB', $translations)) {
            throw new Exception('Translations must support locale codes \'de-DE\' and \'en-GB\'.');
        }

        if (!array_key_exists($this->getSystemDefaultLocaleCode(), $translations)) {
            $translations[$this->getSystemDefaultLocaleCode()] = $translations['en-GB'];
        }
    }

    private function getSystemDefaultLocaleCode(): string
    {
        if (!$this->systemDefaultLocaleCode) {
            $localeCode = $this->connection->fetchOne(
                'SELECT
                    locale.code AS localeCode
                FROM language
                LEFT JOIN locale ON locale.id = language.locale_id
                WHERE language.id = UNHEX(:languageId)',
                ['languageId' => Defaults::LANGUAGE_SYSTEM],
            );
            if (!$localeCode) {
                throw new Exception('The system default language has no associated locale.');
            }

            $this->systemDefaultLocaleCode = $localeCode;
        }

        return $this->systemDefaultLocaleCode;
    }
}
