import './page/external-orders-list';

const { Module } = Shopware;

Module.register('external-orders', {
    type: 'plugin',
    name: 'external-orders',
    title: 'Bestellübersichten',
    description: 'Zentrale Übersicht für externe Bestellungen',
    color: '#009ee3',
    icon: 'regular-shopping-cart',
    routePrefixPath: 'external-orders',
    routePrefixName: 'external.orders',

    routes: {
        index: {
            component: 'external-orders-list',
            path: 'index',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'admin',
            },
        },
    },

    navigation: [
        {
            id: 'external-orders',
            label: 'Bestellübersichten',
            color: '#009ee3',
            path: 'external.orders.index',
            icon: 'regular-shopping-cart',
            position: 40,
            parent: 'sw-order',
        },
    ],
});
