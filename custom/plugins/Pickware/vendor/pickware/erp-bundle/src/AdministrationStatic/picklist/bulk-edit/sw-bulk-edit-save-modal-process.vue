<script>
export default {
    overrideFrom: 'sw-bulk-edit-save-modal-process',

    data() {
        return {
            document: {
                pickware_erp_picklist: {
                    isReached: 0,
                },
            },
        };
    },

    methods: {
        async createDocuments() {
            await this.$super('createDocuments');
            const picklistDocuments = this.createDocumentPayload
                .filter((item) => item.type === 'pickware_erp_picklist');

            if (picklistDocuments.length > 0) {
                await this.createDocument('pickware_erp_picklist', picklistDocuments);
            }
        },
    },
};
</script>
