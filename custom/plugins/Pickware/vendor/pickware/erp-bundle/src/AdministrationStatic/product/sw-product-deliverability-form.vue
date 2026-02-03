<template>
    {% block sw_product_deliverability_form %}
    <div>
        <sw-container
            :columns="pwErpGetColumnStyle"
            :justify="pwErpGetJustifyStyle"
        >
            <pw-erp-is-stock-management-disabled-field
                v-if="pwErpIsProductStockManagementFeatureActive"
                ref="stockManagementSwitch"
            />
            <router-link
                v-if="pwErpShowStock && !product._isNew"
                class="sw-card__quick-link"
                :to="{ name: 'sw.product.detail.pw.erp.stock', params: { id: $route.params.id } }"
            >
                {{ $t('sw-product-deliverability-form.pw-erp.stocks-link') }}

                <sw-icon
                    name="regular-long-arrow-right"
                    small
                />
            </router-link>
        </sw-container>

        {% parent %}
    </div>
    {% endblock %}
    <!-- Is a copy of the parent stock field but removes the inheritance wrapper since we do not allow stock inheritance
         for variant products. This also disables the stock field for not-new products because the stock is managed in
         the stock tab. -->
    {% block sw_product_deliverability_form_stock_field %}
    <pw-erp-product-deliverability-form-stock-field
        v-model:value="product.stock"
        :show="pwErpShowProductStockField"
        name="sw-field--product-stock"
        :disabled="!product._isNew"
        :label="$t('sw-product.settingsForm.labelStock')"
        :placeholder="$t('sw-product.settingsForm.placeholderStock')"
        :error="productStockError"
    />
    {% endblock %}

    <!-- We need to wrap both the "available stock" and the "closeout switch" in separate components in ERP to show/hide
         depending on the stock managed property. It needs to be in separate components so that other plugins can
         override the show/hide logic safely. (without override conflicts).
    -->
    {% block sw_product_deliverability_form_available_stock_field %}
    <pw-erp-product-deliverability-form-available-stock-container>
        {% parent %}
    </pw-erp-product-deliverability-form-available-stock-container>
    {% endblock %}

    {% block sw_product_deliverability_form_is_closeout_field %}
    <pw-erp-product-deliverability-form-closeout-switch-container>
        {% parent %}
    </pw-erp-product-deliverability-form-closeout-switch-container>
    {% endblock %}
</template>

<script>
import { Component } from '@pickware/shopware-adapter';
import { PickwareFeature } from '@pickware/shopware-administration-feature';

import { PickwareProductStoreNamespace } from './pickware-product-store.js';

const { mapState, mapGetters } = Component.getComponentHelper();

export default {
    overrideFrom: 'sw-product-deliverability-form',

    computed: {
        ...mapState(PickwareProductStoreNamespace, {
            pwErpPickwareProduct: 'pickwareProduct',
        }),
        ...mapGetters(PickwareProductStoreNamespace, {
            pwErpIsStockManagementDisabled: 'isStockManagementDisabled',
            pwErpShowStock: 'showStock',
        }),

        pwErpIsProductStockManagementFeatureActive() {
            return PickwareFeature.isActive('pickware-erp.feature.product-stock-management');
        },

        pwErpGetColumnStyle() {
            return this.pwErpIsProductStockManagementFeatureActive ? '1fr 0fr' : '';
        },

        pwErpGetJustifyStyle() {
            return !this.pwErpIsProductStockManagementFeatureActive ? 'end' : 'stretch';
        },

        pwErpShowProductStockField() {
            return !this.pwErpIsStockManagementDisabled;
        },
    },

    watch: {
        pwErpIsStockManagementDisabled(newValue) {
            if (newValue && this.product.isNew()) {
                this.product.stock = 0;
            }
        },
    },
};
</script>

<i18n>
    {
        "de-DE": {
            "sw-product-deliverability-form": {
                "pw-erp": {
                    "stocks-link": "Bestand"
                }
            }
        },
        "en-GB": {
            "sw-product-deliverability-form": {
                "pw-erp": {
                    "stocks-link": "Stock"
                }
            }
        }
    }
</i18n>
