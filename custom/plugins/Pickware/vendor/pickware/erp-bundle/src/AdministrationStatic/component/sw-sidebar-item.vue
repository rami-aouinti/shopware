<script>
export default {
    overrideFrom: 'sw-sidebar-item',

    beforeUnmount() {
        // Similar to the created() event of the parent component, remove this sidebar-item from its parent's registry
        // when unmounted. This is necessary to dynamically add or remove sidebar-items from a sidebar (e.g. when
        // changing tabs).
        let parent = this.$parent;
        while (parent) {
            if (parent.$options.name === 'sw-sidebar') {
                parent.pickwareErpRemoveSidebarItem(this);

                return;
            }

            parent = parent.$parent;
        }
    },
};
</script>
