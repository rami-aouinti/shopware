<script>
export default {
    overrideFrom: 'sw-bulk-edit-save-modal-process',
    data() {
        return {
            document: {
                pickware_erp_invoice_correction: {
                    isReached: 0,
                },
            },
        };
    },

    methods: {

        // We override the 'createDocuments' function of sw-bulk-edit-order to add our invoice correction document to
        // the document creation
        async createDocuments(...args) {
            if (this.createDocumentPayload.length <= 0) {
                return;
            }

            await this.$super('createDocuments', ...args);

            const invoiceCorrectionDocuments = this.createDocumentPayload.filter(
                (item) => item.type === 'pickware_erp_invoice_correction',
            );

            if (invoiceCorrectionDocuments.length > 0) {
                await this.createDocument('pickware_erp_invoice_correction', invoiceCorrectionDocuments);
            }
        },
    },
};
</script>
