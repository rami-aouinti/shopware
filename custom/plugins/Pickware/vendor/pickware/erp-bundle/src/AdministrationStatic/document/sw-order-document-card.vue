<script>
import { Criteria } from '@pickware/shopware-adapter';

import { DocumentTypeTechnicalNames } from './document-types.js';

const excludedOrderDetailPageDocumentTypes = [
    DocumentTypeTechnicalNames.SUPPLIER_ORDER,
];

export default {
    overrideFrom: 'sw-order-document-card',

    computed: {
        documentTypeCriteria() {
            const criteria = this.$super('documentTypeCriteria');

            // Remove specific document types from the flow builder document creation selection
            criteria.addFilter(
                Criteria.not(
                    'AND',
                    [
                        Criteria.equalsAny('technicalName', excludedOrderDetailPageDocumentTypes),
                    ],
                ),
            );

            return criteria;
        },
    },
};
</script>
