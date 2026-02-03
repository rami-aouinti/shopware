<script>
import { PickwareFeature } from '@pickware/shopware-administration-feature';

export default {
    overrideFrom: 'sw-bulk-edit-order',

    computed: {
        documentsFormFields() {
            const formFields = this.$super('documentsFormFields');

            if (PickwareFeature.isActive('pickware-erp.feature.invoice-correction')) {
                formFields.splice(
                    formFields.length - 1,
                    0,
                    {
                        name: 'pickware_erp_invoice_correction',
                        config: {
                            componentName: 'pw-erp-bulk-edit-order-invoice-correction-form-fields',
                            changeLabel: this.$tc('sw-bulk-edit-order-invoice-correction.changeLabel'),
                            changeSubLabel: this.$tc('sw-bulk-edit-order-invoice-correction.subLabel'),
                        },
                    },
                );
            }

            return formFields;
        },
    },
};
</script>

<i18n>
{
  "de-DE": {
    "sw-bulk-edit-order-invoice-correction": {
      "changeLabel": "Erstellen: Rechnungskorrektur",
      "subLabel": "Die Rechnungskorrektur enthält alle Retouren und weitere Änderungen an der Bestellung, die seit der letzten Rechnungsstellung vorgenommen wurden."
    }
  },
  "en-GB": {
    "sw-bulk-edit-order-invoice-correction": {
      "changeLabel": "Generate: Invoice correction",
      "subLabel": "The invoice correction contains all return orders and other changes of the order that have been made since the last invoice creation."

    }
  }
}
</i18n>
