<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Renderer;

use Closure;
use Pickware\DalBundle\EntityManager;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class Translator extends AbstractTranslator
{
    use TranslatorTrait;

    private const FALLBACK_LOCALE = 'en-GB';

    private AbstractTranslator $baseTranslator;
    private EntityManager $entityManager;
    private CacheItemPoolInterface $cache;
    private MessageFormatterInterface $formatter;
    private SnippetService $snippetService;

    /**
     * When set is the base message catalogue (of the base translator and any other decorator) with all base snippets as
     * well as custom (plugin) snippets that are loaded in this translator.
     */
    private ?MessageCatalogueInterface $customMessageCatalogue = null;

    public function __construct(
        AbstractTranslator $baseTranslator,
        EntityManager $entityManager,
        CacheItemPoolInterface $cache,
        MessageFormatterInterface $formatter,
        SnippetService $snippetService,
    ) {
        $this->baseTranslator = $baseTranslator;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->formatter = $formatter;
        $this->snippetService = $snippetService;
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if ($this->customMessageCatalogue) {
            return $this->formatter->format(
                $this->customMessageCatalogue->get($id, $domain ?? 'messages'),
                $locale ?? self::FALLBACK_LOCALE,
                $parameters,
            );
        }

        // If the snippets of this translator are not loaded, use Shopware's base translator with their own logic (e.g.
        // more specific locale fallback logic)
        return $this->baseTranslator->trans($id, $parameters, $domain, $locale);
    }

    public function loadCustomTranslations(string $localeCode, Context $context): void
    {
        if (
            $this->customMessageCatalogue
            && $this->customMessageCatalogue->getLocale() === $localeCode
        ) {
            // The message catalogue was already loaded and set for this locale
            return;
        }

        $snippetSet = $this->getSnippetSet($localeCode, $context);
        $this->customMessageCatalogue = $this->loadCatalogue($snippetSet->getId(), $localeCode);
        $this->setLocale($localeCode);
    }

    public function unloadCustomTranslations(): void
    {
        $this->customMessageCatalogue = null;
    }

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        return $this->customMessageCatalogue ?? $this->baseTranslator->getCatalogue($locale);
    }

    /**
     * @return MessageCatalogueInterface[]
     */
    public function getCatalogues(): array
    {
        if ($this->customMessageCatalogue === null) {
            return $this->baseTranslator->getCatalogues();
        }

        return [$this->customMessageCatalogue];
    }

    private function loadCatalogue(string $snippetSetId, string $locale): MessageCatalogueInterface
    {
        $catalog = clone $this->baseTranslator->getCatalogue($locale);
        $snippets = $this->loadSnippetsWithCache($catalog, $snippetSetId);
        $catalog->add($snippets);

        return $catalog;
    }

    private function loadSnippetsWithCache(MessageCatalogueInterface $catalog, string $snippetSetId): array
    {
        $cacheItem = $this->cache->getItem('translation.catalog.' . $snippetSetId);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        // Loads all (custom and base) snippets from files and database
        $snippets = $this->snippetService->getStorefrontSnippets($catalog, $snippetSetId, self::FALLBACK_LOCALE);
        $cacheItem->set($snippets);
        $this->cache->save($cacheItem);

        return $snippets;
    }

    private function getSnippetSet(string $localeCode, Context $context): SnippetSetEntity
    {
        // It may be that no snippet set exists for the given locale, as snippet sets can be created and deleted by the
        // user. This is more likely for languages that do not have "de-DE" or "en-GB" as their locale, because Shopware
        // does not provide a base file for these locales, which is necessary to create a snippet set.
        // However, theoretically, even for "de-DE" or "en-GB," a snippet set might not exist if it has been deleted by
        // the user.
        // So, we first attempt to find a snippet set that matches the locale. If none is found and the locale is a
        // German locale, we try to find a snippet set for "de-DE." If that also doesn’t work, or the locale is not
        // German, we attempt to find a snippet set for "en-GB."
        // If none of these attempts succeed, we throw an exception, because a document cannot be rendered without a
        // snippet set.

        /** @var SnippetSetCollection $snippetSets */
        $snippetSets = $this->entityManager->findBy(
            SnippetSetDefinition::class,
            EntityManager::createCriteriaFromArray([
                'iso' => [
                    $localeCode,
                    'de-DE',
                    'en-GB',
                ],
            ])->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING)),
            $context,
        );
        $matchingLocaleSnippetSet = $snippetSets->filter(
            fn(SnippetSetEntity $snippetSet) => $snippetSet->getIso() === $localeCode,
        )->first();
        if ($matchingLocaleSnippetSet) {
            return $matchingLocaleSnippetSet;
        }

        if (str_starts_with($localeCode, 'de')) {
            $germanSnippetSet = $snippetSets->filter(
                fn(SnippetSetEntity $snippetSet) => $snippetSet->getIso() === 'de-DE',
            )->first();
            if ($germanSnippetSet) {
                return $germanSnippetSet;
            }
        }

        $englishSnippetSet = $snippetSets->filter(
            fn(SnippetSetEntity $snippetSet) => $snippetSet->getIso() === 'en-GB',
        )->first();
        if ($englishSnippetSet) {
            return $englishSnippetSet;
        }

        throw DocumentRendererException::snippetSetNotFound($localeCode);
    }

    public function getLocale(): string
    {
        if ($this->customMessageCatalogue) {
            return $this->locale;
        }

        return $this->baseTranslator->getLocale();
    }

    public function getDecorated(): AbstractTranslator
    {
        return $this->baseTranslator;
    }

    public function trace(string $key, Closure $param): void
    {
        $this->getDecorated()->trace($key, $param);
    }

    public function getTrace(string $key): array
    {
        return $this->getDecorated()->getTrace($key);
    }
}
