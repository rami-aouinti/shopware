import './page/lieferzeiten-index';
import './page/lieferzeiten-all';
import './page/lieferzeiten-open';
import './page/lieferzeiten-statistics';
import './component/lieferzeiten-domain-selection';
import './component/lieferzeiten-order-table';

const { Module } = Shopware;

Module.register('lieferzeiten', {
    type: 'plugin',
    name: 'lieferzeiten',
    title: 'lieferzeiten.general.mainMenuItemGeneral',
    description: 'lieferzeiten.general.description',
    color: '#2B8CBF',
    icon: 'regular-clock',

    privileges: {
        viewer: {
            permissions: ['admin'],
            dependencies: [],
        },
        editor: {
            permissions: ['admin'],
            dependencies: ['viewer'],
        },
    },

    routes: {
        index: {
            component: 'lieferzeiten-index',
            path: 'index',
            redirect: { name: 'lieferzeiten.index.all' },
            children: {
                all: {
                    component: 'lieferzeiten-all',
                    path: 'all',
                    meta: {
                        parentPath: 'lieferzeiten.index',
                        privilege: 'admin',
                    },
                },
                open: {
                    component: 'lieferzeiten-open',
                    path: 'open',
                    meta: {
                        parentPath: 'lieferzeiten.index',
                        privilege: 'admin',
                    },
                },
                statistics: {
                    component: 'lieferzeiten-statistics',
                    path: 'statistics',
                    meta: {
                        parentPath: 'lieferzeiten.index',
                        privilege: 'admin',
                    },
                },
            },
        },
    },

    navigation: [
        {
            id: 'lieferzeiten',
            label: 'lieferzeiten.general.mainMenuItemGeneral',
            color: '#2B8CBF',
            path: 'lieferzeiten.index',
            icon: 'regular-clock',
            parent: 'sw-order',
            position: 100,
            privilege: 'admin',
        },
        {
            id: 'lieferzeiten-statistics',
            label: 'lieferzeiten.general.mainMenuItemStatistics',
            color: '#2B8CBF',
            path: 'lieferzeiten.index.statistics',
            icon: 'regular-chart-bar',
            parent: 'sw-dashboard',
            position: 110,
            privilege: 'admin',
        },
    ],
});
