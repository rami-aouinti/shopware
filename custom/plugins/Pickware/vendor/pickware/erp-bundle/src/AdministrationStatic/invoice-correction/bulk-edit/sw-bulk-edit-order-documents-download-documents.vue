<script>
import { Criteria } from '@pickware/shopware-adapter';
import { PickwareFeature } from '@pickware/shopware-administration-feature';
import { DocumentTypeTechnicalNames } from '@pickware-erp-bundle/document/document-types';

export default {
    overrideFrom: 'sw-bulk-edit-order-documents-download-documents',

    computed: {

        documentTypeCriteria() {
            const criteria = this.$super('documentTypeCriteria');

            if (!PickwareFeature.isActive('pickware-erp.feature.invoice-correction')) {
                // Remove pickware_erp_invoice_correction (Rechnungskorrektur) from select options
                criteria.addFilter(
                    Criteria.not(
                        'AND',
                        [
                            Criteria.equals('technicalName', DocumentTypeTechnicalNames.INVOICE_CORRECTION),
                        ],
                    ),
                );
            }

            return criteria;
        },
    },
};
</script>
