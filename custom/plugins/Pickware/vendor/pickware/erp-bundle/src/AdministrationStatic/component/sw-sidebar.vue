<script>
/**
 * Overrides Shopware's sw-sidebar to allow an initially opened item to render this sidebar open by default.
 */
export default {
    overrideFrom: 'sw-sidebar',

    props: {
        pickwareErpIndexOfInitiallyActiveItem: {
            type: Number,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            pickwareErpIsInitialized: false,
        };
    },

    watch: {
        // Workaround to re-evaluate pickwareErpSetInitiallyActiveItem when the items are loaded because they are loaded
        // _after_ the component is initialised
        items(newItems) {
            if (!this.pickwareErpIsInitialized && newItems.length !== 0) {
                this.pickwareErpSetInitiallyActiveItem();
                this.pickwareErpIsInitialized = true;
            }
        },
    },

    methods: {
        pickwareErpRemoveSidebarItem(removedItem) {
            // eslint-disable-next-line no-underscore-dangle
            this.items = this.items.filter((item) => item._uid !== removedItem._uid);
            this.pickwareErpSetInitiallyActiveItem();
        },

        pickwareErpSetInitiallyActiveItem() {
            this.isOpened = this.pickwareErpIndexOfInitiallyActiveItem !== null;
            if (
                this.pickwareErpIndexOfInitiallyActiveItem !== null
                && this.items.length > this.pickwareErpIndexOfInitiallyActiveItem
            ) {
                this.setItemActive(this.items[this.pickwareErpIndexOfInitiallyActiveItem]);
            }
        },
    },
};
</script>
