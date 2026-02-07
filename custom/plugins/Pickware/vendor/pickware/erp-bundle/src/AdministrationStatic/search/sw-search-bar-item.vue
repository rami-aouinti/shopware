<!-- eslint-disable vue/valid-v-else-if -->
<template>
    {% block sw_search_bar_item_cms_page %}
    {% parent %}

    <!-- These new template blocks are necessary to format the search result item names and set the correct link to
         the entity detail page -->
    <router-link
        v-else-if="type === 'pickware_erp_warehouse'"
        ref="routerLink"
        class="sw-search-bar-item__link"
        :to="{ name: 'pw.erp.warehouse.detail', params: { id: item.id } }"
        @click="onClickSearchResult('pickware_erp_warehouse', item.id)"
    >
        <span class="sw-search-bar-item__label">
            <sw-highlight-text
                :searchTerm="searchTerm"
                :text="item.name"
            />

            <sw-highlight-text
                :searchTerm="searchTerm"
                :text="item.code"
            />
        </span>
    </router-link>

    <router-link
        v-else-if="type === 'pickware_erp_supplier'"
        ref="routerLink"
        class="sw-search-bar-item__link"
        :to="{ name: 'pw.erp.supplier.detail', params: { id: item.id } }"
        @click="onClickSearchResult('pickware_erp_supplier', item.id)"
    >
        <span class="sw-search-bar-item__label">
            <sw-highlight-text
                :searchTerm="searchTerm"
                :text="item.name"
            />

            <sw-highlight-text
                :searchTerm="searchTerm"
                :text="item.number"
            />
        </span>
    </router-link>

    <router-link
        v-else-if="type === 'pickware_erp_return_order'"
        ref="routerLink"
        class="sw-search-bar-item__link"
        :to="{ name: 'pw.erp.return.order.detail', params: { id: item.id } }"
        @click="onClickSearchResult('pickware_erp_return_order', item.id)"
    >
        <span class="sw-search-bar-item__label">
            <sw-highlight-text
                :searchTerm="searchTerm"
                :text="item.number"
            />
        </span>
    </router-link>

    <router-link
        v-else-if="type === 'pickware_erp_supplier_order'"
        ref="routerLink"
        class="sw-search-bar-item__link"
        :to="{ name: 'pw.erp.supplier.order.detail', params: { id: item.id } }"
        @click="onClickSearchResult('pickware_erp_supplier_order', item.id)"
    >
        <span class="sw-search-bar-item__label">
            <sw-highlight-text
                :searchTerm="searchTerm"
                :text="item.number"
            />
        </span>
    </router-link>

    <router-link
        v-else-if="type === 'pickware_erp_goods_receipt'"
        ref="routerLink"
        class="sw-search-bar-item__link"
        :to="{ name: 'pw.erp.goods.receipt.detail', params: { id: item.id } }"
        @click="onClickSearchResult('pickware_erp_goods_receipt', item.id)"
    >
        <span class="sw-search-bar-item__label">
            <sw-highlight-text
                :searchTerm="searchTerm"
                :text="item.number"
            />
        </span>
    </router-link>

    <!-- Let the 'Create new Return'-action route to the list page instead of the create-page
    (because that doesn't work without knowing the order id to create the return for) -->
    <router-link
        v-else-if="type === 'module' && item.action && item.entity === 'pickware_erp_return_order'"
        ref="routerLink"
        class="sw-search-bar-item__link"
        :to="{ name: 'pw.erp.return.order.index' }"
        @click="onClickSearchResult('pickware_erp_return_order', item.id)"
    >
        <span
            class="sw-search-bar-item__label"
        >
            <sw-highlight-text
                :searchTerm="searchTerm"
                :text="moduleName"
            />

            <sw-shortcut-overview-item
                v-if="shortcut"
                title=""
                :content="shortcut"
            />
            <sw-highlight-text
                :text="$tc(`global.sw-search-bar-item.${item.action ? 'typeLabelAction': 'typeLabelModule'}`)"
            />
        </span>
    </router-link>
    {% endblock %}
</template>

<script>
export default {
    overrideFrom: 'sw-search-bar-item',
};
</script>
