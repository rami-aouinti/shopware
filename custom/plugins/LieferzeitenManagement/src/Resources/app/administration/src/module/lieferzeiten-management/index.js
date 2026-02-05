import './page/lieferzeiten-management-page';

Shopware.Module.register('lieferzeiten-management', {
    type: 'plugin',
    name: 'lieferzeiten-management',
    title: 'lieferzeiten-management.general.mainMenuItemGeneral',
    description: 'lieferzeiten-management.general.descriptionTextModule',
    color: '#1a73e8',
    icon: 'regular-clock',
    routes: {
        index: {
            component: 'lieferzeiten-management-page',
            path: 'index',
        },
    },
    navigation: [
        {
            id: 'lieferzeiten-management',
            label: 'lieferzeiten-management.general.mainMenuItemGeneral',
            path: 'lieferzeiten.management.index',
            parent: 'sw-order',
            position: 110,
        },
    ],
});

Shopware.Locale.extend('en-GB', {
    'lieferzeiten-management': {
        general: {
            mainMenuItemGeneral: 'Delivery times',
            descriptionTextModule: 'Manage delivery times and tracking',
            title: 'Delivery times',
            filtersTitle: 'Filters',
            filterTracking: 'Tracking number',
            filterPackageStatus: 'Package status',
            filterShippedFrom: 'Shipped from',
            filterShippedTo: 'Shipped to',
            filterDeliveredFrom: 'Delivered from',
            filterDeliveredTo: 'Delivered to',
            columnSan6PackageNumber: 'San6 package',
            columnPackageStatus: 'Package status',
            columnShippedAt: 'Shipping date',
            columnDeliveredAt: 'Delivery date',
            columnTrackingNumber: 'Tracking number',
        },
    },
});

Shopware.Locale.extend('de-DE', {
    'lieferzeiten-management': {
        general: {
            mainMenuItemGeneral: 'Lieferzeiten',
            descriptionTextModule: 'Lieferzeiten und Tracking verwalten',
            title: 'Lieferzeiten',
            filtersTitle: 'Filter',
            filterTracking: 'Sendenummer',
            filterPackageStatus: 'Paket-Status',
            filterShippedFrom: 'Versand ab',
            filterShippedTo: 'Versand bis',
            filterDeliveredFrom: 'Lieferung ab',
            filterDeliveredTo: 'Lieferung bis',
            columnSan6PackageNumber: 'San6 Paket',
            columnPackageStatus: 'Paket-Status',
            columnShippedAt: 'Versand-Datum',
            columnDeliveredAt: 'Liefer-Datum',
            columnTrackingNumber: 'Sendenummer',
        },
    },
});
