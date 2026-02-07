<template>
    {% block sw_price_field_lock_button %}
    <button
        v-if="pwErpLockGrossNetPriceLink"
        v-tooltip="$t('sw-price-field.pw-erp.locked-link-hint')"
        class="is--locked sw-price-field__lock"
    >
        <sw-icon
            key="price-field-locked-indicator"
            name="regular-lock"
            color="red"
            size="16"
        />
    </button>

    <template v-else>
        {% parent %}
    </template>
    {% endblock %}
</template>

<script>
import get from 'lodash/get';

export default {
    overrideFrom: 'sw-price-field',

    data() {
        return {
            pwErpLockGrossNetPriceLink: false,
        };
    },

    mounted() {
        // This is a workaround to determine whether or not this 'sw-price-field' is used to display Shopware's product
        // purchase price. Check for the specific CSS class that is set in the 'sw-list-price-field' template.
        // For the product detail page we can assert the CSS class from the 'sw-list-price-field' template.
        const isDetailPagePurchasePriceField = this.$el.classList.contains('sw-purchase-price-field');
        // The bulk edit uses a generic form renderer. Check the parent's props instead. This may be produce false
        // negatives if the component hierarchy is changed by another plugin (unrealistic but possible).
        const isBulkEditPurchasePriceField = get(this.$parent, '_props.name') === 'purchasePrices';

        // If we (lock) link a formerly unlinked price, the price would be changed automatically. To avoid this unwanted
        // and intransparent behavior, we only (lock) link prices that are already linked.
        this.pwErpLockGrossNetPriceLink = (isDetailPagePurchasePriceField || isBulkEditPurchasePriceField)
            && this.priceForCurrency.linked;
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-price-field": {
            "pw-erp": {
                "locked-link-hint": "Der Einkaufspreis in Brutto und Netto ist fest verlinkt, da Pickware ERP ausschlielich mit dem Netto Einkaufspreis arbeitet."
            }
        }
    },
    "en-GB": {
        "sw-price-field": {
            "pw-erp": {
                "locked-link-hint": "The link between net and gross purchase price is locked because Pickware ERP only considers the purchase price net."
            }
        }
    }
}
</i18n>
