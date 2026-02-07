<script>
import { Criteria } from '@pickware/shopware-adapter';
import { DocumentTypeTechnicalNames } from '@pickware-erp-bundle/document/document-types';

export default {
    overrideFrom: 'sw-bulk-edit-order-documents-download-documents',

    computed: {
        documentTypeCriteria() {
            const criteria = this.$super('documentTypeCriteria');

            criteria.addFilter(Criteria.not(
                'AND',
                [Criteria.equals('technicalName', DocumentTypeTechnicalNames.SUPPLIER_ORDER)],
            ));

            return criteria;
        },
    },
};
</script>
