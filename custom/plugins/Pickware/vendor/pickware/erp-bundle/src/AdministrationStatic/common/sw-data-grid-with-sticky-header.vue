<template>
    {% block sw_data_grid_header_columns_content %}
    {% parent %}

    <div
        v-if="pwErpShowStickyHeaderRow"
        class="pw-erp-sw-data-grid-with-sticky-header-cell sw-data-grid__cell-content"
    >
        <sw-skeleton
            v-if="isLoading"
            variant="listing"
        />
        <slot
            v-else
            :name="`pw-erp-sticky-header-${column.property}`"
        />
    </div>
    {% endblock %}
</template>

<script>
/**
 * Overrides Shopware's sw-data-grid to add an optional sticky header row to the top of the data grid. The slot must be
 * filled by the grid component that is using this sw-data-grid.
 */
export default {
    name: 'sw-data-grid-with-sticky-header',
    overrideFrom: 'sw-data-grid',

    props: {
        pwErpShowStickyHeaderRow: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
};
</script>

<style lang="scss">
@import "~shopware-scss-definitions/administration/variables.scss";

.pw-erp-sw-data-grid-with-sticky-header-cell {
    background: $color-gray-100;
    border-top: $color-gray-500 1px solid;
    border-bottom: $color-gray-500 1px solid;
}
</style>

