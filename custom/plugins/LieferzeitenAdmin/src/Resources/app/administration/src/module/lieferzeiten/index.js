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
            permissions: ['lieferzeiten.viewer'],
            dependencies: [],
        },
        editor: {
            permissions: ['lieferzeiten.editor'],
            dependencies: ['lieferzeiten.viewer'],
        },
    },

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
                        privilege: 'lieferzeiten.viewer',
                    },
                },
                open: {
                    component: 'lieferzeiten-open',
                    path: 'open',
                    meta: {
                        parentPath: 'lieferzeiten.index',
                        privilege: 'lieferzeiten.viewer',
                    },
                },
                statistics: {
                    component: 'lieferzeiten-statistics',
                    path: 'statistics',
                    meta: {
                        parentPath: 'lieferzeiten.index',
                        privilege: 'lieferzeiten.viewer',
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
            privilege: 'lieferzeiten.viewer',
        },
    ],
});
