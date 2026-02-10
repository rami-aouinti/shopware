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

    routes: {
        index: {
            component: 'lieferzeiten-index',
            path: 'index',
            redirect: { name: 'lieferzeiten.all' },
            children: {
                all: {
                    component: 'lieferzeiten-all',
                    path: 'all',
                    meta: {
                        parentPath: 'lieferzeiten.index',
                    },
                },
                open: {
                    component: 'lieferzeiten-open',
                    path: 'open',
                    meta: {
                        parentPath: 'lieferzeiten.index',
                    },
                },
                statistics: {
                    component: 'lieferzeiten-statistics',
                    path: 'statistics',
                    meta: {
                        parentPath: 'lieferzeiten.index',
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
        },
    ],
});
