import './page/test-admin-menu-page';

Shopware.Module.register('test-admin-menu', {
    type: 'plugin',
    name: 'test-admin-menu',
    title: 'test-admin-menu.general.mainMenuItemGeneral',
    description: 'test-admin-menu.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'regular-star',
    routes: {
        index: {
            component: 'test-admin-menu-page',
            path: 'index',
        },
    },
    navigation: [
        {
            id: 'test-admin-menu',
            label: 'test-admin-menu.general.mainMenuItemGeneral',
            path: 'test.admin.menu.index',
            parent: 'sw-extension',
            position: 100,
        },
    ],
});

Shopware.Locale.extend('en-GB', {
    'test-admin-menu': {
        general: {
            mainMenuItemGeneral: 'Test',
            descriptionTextModule: 'Test admin menu entry',
            title: 'Test',
        },
    },
});

Shopware.Locale.extend('de-DE', {
    'test-admin-menu': {
        general: {
            mainMenuItemGeneral: 'Test',
            descriptionTextModule: 'Test admin menu entry',
            title: 'Test',
        },
    },
});
