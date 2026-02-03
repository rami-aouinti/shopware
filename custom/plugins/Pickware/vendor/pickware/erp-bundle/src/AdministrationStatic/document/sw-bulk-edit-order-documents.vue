<script>
import { Criteria } from '@pickware/shopware-adapter';

import { DocumentTypeTechnicalNames } from './document-types.js';

const excludeFlowDocumentCreationDocumentTypes = [
    DocumentTypeTechnicalNames.SUPPLIER_ORDER,
];

export default {
    overrideFrom: 'sw-bulk-edit-order-documents',

    computed: {
        documentTypeCriteria() {
            const criteria = this.$super('documentTypeCriteria');

            // Remove specific document types from the flow builder document creation selection
            criteria.addFilter(
                Criteria.not(
                    'AND',
                    [
                        Criteria.equalsAny('technicalName', excludeFlowDocumentCreationDocumentTypes),
                    ],
                ),
            );

            return criteria;
        },
    },
};
</script>
