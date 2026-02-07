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

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Twig\Environment as TwigEnvironment;

class DocumentTemplateRenderer
{
    private TwigEnvironment $twig;
    private TemplateFinder $templateFinder;
    private Translator $translator;
    private ContextFactory $contextFactory;
    private EntityManager $entityManager;

    public function __construct(
        TwigEnvironment $twig,
        TemplateFinder $templateFinder,
        Translator $translator,
        ContextFactory $contextFactory,
        EntityManager $entityManager,
    ) {
        $this->twig = $twig;
        $this->templateFinder = $templateFinder;
        $this->translator = $translator;
        $this->contextFactory = $contextFactory;
        $this->entityManager = $entityManager;
    }

    public function render(string $templateName, array $parameters, string $languageId, Context $context): string
    {
        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $context, ['locale']);
        $localeCode = $language->getLocale()->getCode();
        $documentContext = $this->contextFactory->createLocalizedContext($languageId, $context);

        $template = $this->templateFinder->find($templateName);

        // The 'context' template variable will be used in twig filters (e.g. currency formatting)
        $parameters['context'] = $documentContext;

        // Even if no locale code is provided, this preparation step is necessary to load all plugin snippets.
        $this->translator->loadCustomTranslations($localeCode, $documentContext);
        $rendered = $this->twig->render($template, $parameters);
        $this->translator->unloadCustomTranslations();

        return $rendered;
    }
}
