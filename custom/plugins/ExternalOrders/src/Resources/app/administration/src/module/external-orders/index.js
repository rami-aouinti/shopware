import './page/external-orders-list';
import pdfIcon from './icons/external-orders-pdf.svg';
import excelIcon from './icons/external-orders-excel.svg';

const { Module } = Shopware;

const iconRegistry = Shopware.Service('iconRegistry')
    ?? Shopware.Application.getContainer('service')?.iconRegistry;

if (iconRegistry?.register) {
    iconRegistry.register('external-orders-pdf', pdfIcon);
    iconRegistry.register('external-orders-excel', excelIcon);
}

Module.register('external-orders', {
    type: 'plugin',
    name: 'external-orders',
    title: 'Bestellübersichten',
    description: 'Zentrale Übersicht für externe Bestellungen',
    color: '#009ee3',
    icon: 'regular-shopping-cart',

    routes: {
        index: {
            component: 'external-orders-list',
            path: 'index',
            meta: {
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
            parent: 'sw-order',
            position: 45,
        },
    ],
});
