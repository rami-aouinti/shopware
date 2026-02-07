import template from './test-admin-menu-page.html.twig';

Shopware.Component.register('test-admin-menu-page', {
    template,
    metaInfo() {
        return {
            title: this.$createTitle(this.$t('test-admin-menu.general.title')),
        };
    },
});
