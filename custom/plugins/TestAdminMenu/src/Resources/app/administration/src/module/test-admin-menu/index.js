import './page/test-admin-menu-page';

Shopware.Module.register('test-admin-menu', {
    type: 'plugin',
    name: 'test-admin-menu',
    title: 'test-admin-menu.general.mainMenuItemGeneral',
    description: 'test-admin-menu.general.descriptionTextModule',
    color: '#189eff',
    icon: 'regular-chart-bar',
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
            parent: 'sw-dashboard',
            position: 50,
        },
    ],
});

Shopware.Locale.extend('en-GB', {
    'test-admin-menu': {
        general: {
            mainMenuItemGeneral: 'Activity statistics',
            descriptionTextModule: 'Overview with KPIs and activity charts',
            title: 'Activity statistics',
        },
    },
});

Shopware.Locale.extend('de-DE', {
    'test-admin-menu': {
        general: {
            mainMenuItemGeneral: 'Aktivitätsstatistiken',
            descriptionTextModule: 'Übersicht mit KPIs und Aktivitätsdiagrammen',
            title: 'Aktivitätsstatistiken',
        },
    },
});
