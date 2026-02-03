<script>
export default {
    overrideFrom: 'sw-bulk-edit-order',

    computed: {
        documentsFormFields() {
            const formFields = this.$super('documentsFormFields');
            formFields.splice(
                formFields.length - 1,
                0,
                {
                    name: 'pickware_erp_picklist',
                    labelHelpText: this.$tc('sw-bulk-edit-order-picklist.helpText'),
                    config: {
                        componentName: 'pw-erp-bulk-edit-order-picklist-form-fields',
                        changeLabel: this.$tc('sw-bulk-edit-order-picklist.changeLabel'),
                    },
                },
            );

            return formFields;
        },
    },
};
</script>
