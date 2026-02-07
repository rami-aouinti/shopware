<template>
    {% block sw_settings_document_detail_content_field_delivery_countries %}
    {% parent %}

    <sw-checkbox-field
        v-if="pickwareErpIsInvoiceDocument"
        v-model:value="documentConfig.config.pickwareErpDisplayTaxExemptExportLabel"
        :label="$t('pickware-erp-starter.tax-exempt-export.display-label')"
    />

    <sw-entity-multi-id-select
        v-if="pickwareErpIsInvoiceDocument && documentConfig.config.pickwareErpDisplayTaxExemptExportLabel"
        v-model:value="documentConfig.config.pickwareErpTaxExemptExportLabelCountryIds"
        class="sw-settings-document-detail__field_pw-erp-tax_exempt_export_countries"
        :repository="countryRepository"
        :label="$t('pickware-erp-starter.tax-exempt-export.countries')"
    />
    {% endblock %}

    <!-- Hide the whole company config card since its values are not used by the picklist document -->
    {% block sw_settings_document_detail_company_card %}
    <template v-if="!pickwareErpHideDocumentCard">
        {% parent %}
    </template>
    {% endblock %}
</template>

<script>
import get from 'lodash/get';

import { DocumentTypeTechnicalNames, ShopwareDocumentTypeTechnicalName } from './document-types.js';

const disabledDocumentConfigurationFieldsByDocumentType = {
    [DocumentTypeTechnicalNames.PICKLIST]: [
        // General fields
        'itemsPerPage',
        'displayLineItems',
        'displayLineItemPosition',
        'displayPrices',
        'displayHeader',
        'displayFooter',
        'displayInCustomerAccount',
    ],
    [DocumentTypeTechnicalNames.SUPPLIER_ORDER]: [
        // General fields
        'itemsPerPage',
        'displayLineItems',
        'displayLineItemPosition',
        'displayPrices',
        'displayHeader',
        'displayFooter',
        'displayInCustomerAccount',
        // Company card fields
        'displayCompanyAddress',
        'companyName',
        'taxNumber',
        'taxOffice',
        'vatId',
        'bankName',
        'bankIban',
        'bankBic',
        'placeOfJurisdiction',
        'placeOfFulfillment',
        'executiveDirector',
    ],
};

const hideCompanyCardDocumentTypes = [
    DocumentTypeTechnicalNames.PICKLIST,
];

export default {
    overrideFrom: 'sw-settings-document-detail',

    computed: {
        pickwareErpDocumentType() {
            return get(this.documentConfig, 'documentType.technicalName');
        },

        pickwareErpHideDocumentCard() {
            return hideCompanyCardDocumentTypes.includes(this.pickwareErpDocumentType);
        },

        pickwareErpIsInvoiceDocument() {
            return this.documentConfig.documentType.technicalName === ShopwareDocumentTypeTechnicalName.INVOICE;
        },
    },

    methods: {
        // Override onChangeType since it is called after the documentConfig was fetched
        async onChangeType(documentType) {
            this.$super('onChangeType', documentType);

            // Remove configuration fields for specific documents from the view which we don't want to be changed by the
            // user. Filter both general fields and company fields.
            if (Object.keys(disabledDocumentConfigurationFieldsByDocumentType).includes(this.pickwareErpDocumentType)) {
                const hiddenFields = disabledDocumentConfigurationFieldsByDocumentType[this.pickwareErpDocumentType];
                const filterDisabledFields = (field) => field && !hiddenFields.includes(field.name);
                this.generalFormFields = this.generalFormFields.filter(filterDisabledFields);
                this.companyFormFields = this.companyFormFields.filter(filterDisabledFields);
            }
        },
    },
};
</script>

<style lang="scss">
.sw-settings-document-detail__field_pw-erp-tax_exempt_export_countries {
    grid-column: auto / span 2;

    .sw-select-selection-list > li:only-child {
        width: 100%;
    }
}
</style>

<i18n>
{
    "en-GB": {
        "pickware-erp-starter": {
            "tax-exempt-export": {
                "display-label": "Display \"tax-exempt export\" label",
                "countries": "Countries to display the tax-exempt export label for"
            }
        }

    },
    "de-DE": {
        "pickware-erp-starter": {
            "tax-exempt-export": {
                "display-label": "Hinweis \"steuerbefreite Auslieferung\" anzeigen",
                "countries": "Länder, für die der Hinweis \"steuerbefreite Auslieferung\" angezeigt werden soll"
            }
        }

    }
}
</i18n>
