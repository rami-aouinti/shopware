<!-- eslint-disable vue/valid-v-slot !-->
<template>
    {% block sw_product_detail_content_tabs_advanced_variants %}
    {% parent %}

    <pw-erp-modal-promise-adapter
        ref="manageStockModal"
        #default="{ visible, confirm, cancel }"
    >
        <pw-erp-stock-management-change-confirmation-modal
            v-if="visible"
            :isLoading="pwErpIsActionInProgress"
            @close="cancel"
            @confirm="confirm"
        />
    </pw-erp-modal-promise-adapter>

    <sw-tabs-item
        v-if="!pwErpIsLoading && pwErpIsStockTabShown"
        key="sw.product.detail.pw.erp.stock"
        :route="{ name: 'sw.product.detail.pw.erp.stock', params: { id: $route.params.id } }"
        :title="$t('sw-product-detail.pw-erp.stock-tab-title')"
    >
        {{ $t('sw-product-detail.pw-erp.stock-tab-title') }}
    </sw-tabs-item>

    <sw-tabs-item
        v-if="pwErpIsSupplierTabShown"
        key="sw.product.detail.pw.erp.supplier"
        :route="{ name: 'sw.product.detail.pw.erp.supplier', params: { id: $route.params.id } }"
        :title="$t('sw-product-detail.pw-erp.supplier-tab-title')"
    >
        {{ $t('sw-product-detail.pw-erp.supplier-tab-title') }}
    </sw-tabs-item>

    <sw-tabs-item
        key="sw.product.detail.pw.erp.order-list"
        :route="{ name: 'sw.product.detail.pw.erp.order-list', params: { id: $route.params.id } }"
        :title="$t('sw-product-detail.pw-erp.order-list-tab-title')"
    >
        {{ $t('sw-product-detail.pw-erp.order-list-tab-title') }}
    </sw-tabs-item>

    {% endblock %}

    {% block sw_product_detail_sidebar %}
    {% parent %}
    <!-- Due to an optimization inside Vue, this sw-sidebar inside the <template> is not actually destroyed when
    changing the tab away from our pw-erp-order-list tab. Hence we need to make an additional distinction of
    pickwareErpIndexOfInitiallyActiveItem here. Otherwise the initially open state of the sidebar would not work. !-->
    <sw-sidebar
        v-if="isPwErpOrderListTabActive"
        :pickwareErpIndexOfInitiallyActiveItem="isPwErpOrderListTabActive ? 0 : null"
    >
        <pw-erp-product-order-list-filter-sidebar-item />
    </sw-sidebar>
    {% endblock %}

    {% block sw_product_detail_content_view %}
    {% parent %}
    <pw-erp-apply-to-all-product-variants-confirmation-modal
        v-if="pwErpApplyToAllProductVariantsProductId && pwErpApplyToAllProductVariantsChangeSet"
        :flatChangeSet="pwErpApplyToAllProductVariantsChangeSet"
        :productId="pwErpApplyToAllProductVariantsProductId"
        @close="pwErpCloseApplyToAllProductVariantsModal"
    />
    {% endblock %}
</template>

<script>
import { Component, State } from '@pickware/shopware-adapter';
import { PickwareFeature } from '@pickware/shopware-administration-feature';
import { ProductStockStore, ProductStockStoreNamespace } from '@pickware-erp-bundle/stock/product-stock-store';

import { PickwareProductStore, PickwareProductStoreNamespace } from './pickware-product-store.js';
import { PickwareProductDetailTabsStore, PickwareProductDetailTabsStoreNamespace } from './product-detail-tabs-store.js';
import {
    ProductSupplierConfigurationStore,
    ProductSupplierConfigurationStoreNamespace,
} from './product-supplier-configuration-store.js';

const { mapActions, mapState, mapGetters } = Component.getComponentHelper();

export default {
    overrideFrom: 'sw-product-detail',

    data() {
        return {
            pwErpOrderListFilter: {},
            pwErpShowStockManagedModal: false,
            pwErpProductSupplierConfigurationChangedCopyableFields: [],
            pwErpSaveProductActionInProgress: false,
            // To prevent flickering in the UI, we need this property so it indicates when the initial loading is done
            // to safely show the stock tab.
            pwErpInitialLoading: true,
        };
    },

    computed: {
        isPwErpOrderListTabActive() {
            return this.$route.name === 'sw.product.detail.pw.erp.order-list';
        },

        // We need our pickware product in the product stock tab component (pw-erp-product-stock-tab), but the product
        // is fetched via Shopware's State object, which is filled here. Therefore we add the necessary associations
        // that are needed in the product stock tab here.
        productCriteria() {
            const criteria = this.$super('productCriteria');
            criteria.addAssociation('pickwareErpPickwareProduct');

            return criteria;
        },

        pwErpDigitalProductStockManagedChanged() {
            // Compatibility check. `this.productStates` considers physical/digital products and was added in 6.4.19.
            // See https://github.com/shopware/shopware/commit/3ade730e2254ebf86b629ee5a5a4db10efebe25f
            if (!this.productStates) {
                return false;
            }

            return !this.productStates.includes('is-physical') && !this.product.isCloseout
            && (this.productId ? !this.pwErpPickwareProduct.isStockManagementDisabled : true) !== this.product
                .isCloseout;
        },

        pwErpIsStockManagementFeatureActive() {
            return PickwareFeature.isActive('pickware-erp.feature.product-stock-management');
        },

        pwErpIsLoading() {
            return this.pwErpIsActionInProgress || this.pwErpInitialLoading;
        },

        ...mapState(ProductSupplierConfigurationStoreNamespace, {
            pwErpProductSupplierConfiguration: 'productSupplierConfiguration',
            pwErpApplyToAllProductVariantsProductId: 'applyToAllProductVariantsProductId',
            pwErpApplyToAllProductVariantsChangeSet: 'applyToAllProductVariantsChangeSet',
        }),
        ...mapState(PickwareProductStoreNamespace, {
            pwErpPickwareProduct: 'pickwareProduct',
        }),
        ...mapGetters(PickwareProductStoreNamespace, {
            pwErpIsActionInProgress: 'isActionInProgress',
            pwErpIsStockManagementDisabled: 'isStockManagementDisabled',
            pwErpHasStockManagedChanged: 'hasStockManagedChanged',
            pwErpShowStock: 'showStock',
        }),
        ...mapGetters(PickwareProductDetailTabsStoreNamespace, {
            pwErpIsSupplierTabShown: 'isSupplierTabShown',
            pwErpIsStockTabShown: 'isStockTabShown',
        }),
    },

    beforeUnmount() {
        State.unregisterModule(ProductSupplierConfigurationStoreNamespace);
        State.unregisterModule(PickwareProductStoreNamespace);
        State.unregisterModule(ProductStockStoreNamespace);
    },

    methods: {
        ...mapActions(ProductSupplierConfigurationStoreNamespace, {
            pwErpLoadProductSupplierConfiguration: 'loadProductSupplierConfiguration',
            pwErpCloseApplyToAllProductVariantsModal: 'closeApplyToAllProductVariantsModal',
            pwErpSaveProductSupplierConfiguration: 'saveProductSupplierConfiguration',
        }),

        ...mapActions(PickwareProductStoreNamespace, {
            pwErpFetchPickwareProduct: 'fetchPickwareProduct',
            pwErpSavePickwareProduct: 'savePickwareProduct',
            pwErpUpdateIsStockManaged: 'updateUnsavedPickwareProductChanges',
        }),

        ...mapActions(PickwareProductDetailTabsStoreNamespace, {
            pwErpUpdateSupplierTabCondition: 'updateSupplierTabCondition',
            pwErpUpdateStockTabCondition: 'updateStockTabCondition',
            pwErpRemoveStockTabCondition: 'removeStockTabCondition',
        }),

        createdComponent() {
            this.$super('createdComponent');
            State.registerModule(PickwareProductStoreNamespace, PickwareProductStore);
            State.registerModule(ProductSupplierConfigurationStoreNamespace, ProductSupplierConfigurationStore);
            // This state was registered in the corresponding child component 'pw-erp-product-stock-tab', but due to
            // a bug in Vuex 4.1.0, which resets the current state when a module is registered, we need to register it here
            // to ensure that the state is working correctly.
            State.registerModule(ProductStockStoreNamespace, ProductStockStore);

            if (!State.list().includes(PickwareProductDetailTabsStoreNamespace)) {
                State.registerModule(PickwareProductDetailTabsStoreNamespace, PickwareProductDetailTabsStore);
            }

            this.pwErpUpdateSupplierTabCondition({
                'pw-erp-product-sw-product-detail': PickwareFeature.isActive(
                    'pickware-erp.feature.supplier-order-management',
                ),
            });

            this.pwErpUnsubscribeFromStore = this.$store.subscribe(
                this.pwErpLoadPickwareProductAndSetStockTabCondition,
            );
        },

        destroyedComponent() {
            this.$super('destroyedComponent');

            this.pwErpUnsubscribeFromStore();
        },

        // We need to interrupt the saving process to show a confirmation modal
        async onSave() {
            this.pwErpSaveProductActionInProgress = true;

            // Compatibility check. `this.productStates` considers physical/digital products and was added in 6.4.19.
            // See https://github.com/shopware/shopware/commit/3ade730e2254ebf86b629ee5a5a4db10efebe25f
            if (this.pwErpIsStockManagementFeatureActive && this.productStates) {
                if ((this.pwErpHasStockManagedChanged && this.productStates.includes('is-physical'))
                        || (this.productId && this.pwErpDigitalProductStockManagedChanged)
                ) {
                    try {
                        await this.$refs.manageStockModal.show();
                    } catch (e) {
                        // modal got closed
                        return;
                    }
                    if (!this.productStates.includes('is-physical')) {
                        this.pwErpUpdateIsStockManaged({ isStockManagementDisabled: true });
                    }
                    // We commit 'setLoading' here, as we already saving the product at this state
                    State.commit('swProductDetail/setLoading', ['product', true]);
                    await this.pwErpSavePickwareProduct();
                }
            }

            this.$super('onSave');
        },

        async onSaveFinished(response) {
            this.$super('onSaveFinished', response);
            // We need to check here if a new product is stock managed and update the value afterwards.
            // If a product is not physical we need to change the stock management to disabled, as we don't track
            // our state in non-physical products
            // Also: Compatibility check. `this.productStates` considers physical/digital products and was added in
            // 6.4.19. See https://github.com/shopware/shopware/commit/3ade730e2254ebf86b629ee5a5a4db10efebe25f
            if (this.pwErpIsStockManagementFeatureActive && this.productStates) {
                if (this.pwErpIsStockManagementDisabled || this.pwErpDigitalProductStockManagedChanged) {
                    if (!this.productId) {
                        if (!this.productStates.includes('is-physical')) {
                            this.pwErpUpdateIsStockManaged({ isStockManagementDisabled: true });
                        }
                        // We need to fetch the newly created pickware product and overwrite it with the changes
                        // done in the UI.
                        await this.pwErpFetchPickwareProduct(this.product.id);
                        await this.pwErpSavePickwareProduct();
                    } else {
                        // Subscribers may change properties of the product after saving. Reload the product to force a
                        // refresh of all properties (e.g. closeout) in the UI.
                        await this.loadProduct();
                    }
                }

                // Check if a product is not physical and selected for close out, which is shopwares handling of not
                // stock managed products. If close out is true we need to set stock management to enabled
                if (this.productId && !this.productStates.includes('is-physical') && this.product.isCloseout) {
                    this.pwErpUpdateIsStockManaged({ isStockManagementDisabled: false });
                }
            }

            // Save the product supplier configuration when configuration already exists
            if (this.pwErpProductSupplierConfiguration) {
                await this.pwErpSaveProductSupplierConfiguration();
            }
            // this is done when a product is not stock managed anymore or has not been changed
            if (this.pwErpPickwareProduct && !this.pwErpIsStockManagementDisabled) {
                await this.pwErpSavePickwareProduct();
            }

            this.pwErpSaveProductActionInProgress = false;
        },

        /**
         * Auto-fetch related pickware entities when the product is (re-)loaded. Please note, that the respective save
         * actions of the related pickware entities handle the entity reloading on themselves. Otherwise, unsaved
         * changes of these entities would be overwritten by this auto-fetch due to the fact, that the product is saved
         * and reloaded before the related pickware entities. It also sets the condition for showing the stock tab
         * of the product when changes being made to the pickware product.
         */
        async pwErpLoadPickwareProductAndSetStockTabCondition(mutation, state) {
            if (mutation.type === 'swProductDetail/setProduct' && !this.pwErpSaveProductActionInProgress) {
                this.pwErpInitialLoading = false;

                await Promise.all([
                    this.pwErpLoadProductSupplierConfiguration(state.swProductDetail.product),
                    this.pwErpFetchPickwareProduct(state.swProductDetail.product.id),
                ]);
            }

            if (mutation.type === `${PickwareProductStoreNamespace}/setPickwareProduct`
                || mutation.type === `${PickwareProductStoreNamespace}/setUnsavedPickwareProductChanges`) {
                if (this.pwErpShowStock) {
                    this.pwErpUpdateStockTabCondition({
                        erpStockTabCondition: true,
                    });
                } else {
                    this.pwErpUpdateStockTabCondition({
                        erpStockTabCondition: false,
                    });
                }
            }
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-product-detail": {
            "pw-erp": {
                "stock-tab-title": "Bestand",
                "supplier-tab-title": "Lieferant",
                "order-list-tab-title": "Bestellungen"
            }
        }
    },
    "en-GB": {
        "sw-product-detail": {
            "pw-erp": {
                "stock-tab-title": "Stock",
                "supplier-tab-title": "Supplier",
                "order-list-tab-title": "Orders"
            }
        }
    }
}
</i18n>
