<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

/**
 * We have to implement this interface in Pickware\PickwareErpStarter\ReturnOrder\Events\CompletelyReturnedEvent
 * to facilitate sending an email via flow after the a11y documents are introduced. However, the plugin also has
 * to work if the interface is not available.
 */

if (!interface_exists(A11yRenderedDocumentAware::class)) {
    interface A11yRenderedDocumentAware
    {
        public const A11Y_DOCUMENTS = 'a11yDocuments';
        public const A11Y_DOCUMENT_IDS = 'a11yDocumentIds';

        /**
         * @return array<string>
         */
        public function getA11yDocumentIds(): array;
    }
}
