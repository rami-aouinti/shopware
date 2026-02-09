const orders = [
    {
        id: 'order-1001',
        orderNumber: '1001',
        domain: 'First Medical',
        createdAt: '2024-10-01T08:15:00+02:00',
        promisedAt: '2024-10-04T16:00:00+02:00',
        parcels: [
            { id: '1001-1', closed: false },
            { id: '1001-2', closed: false },
        ],
    },
    {
        id: 'order-1002',
        orderNumber: '1002',
        domain: 'E-Commerce',
        createdAt: '2024-10-02T10:30:00+02:00',
        promisedAt: '2024-10-05T12:00:00+02:00',
        parcels: [
            { id: '1002-1', closed: true },
        ],
    },
    {
        id: 'order-1003',
        orderNumber: '1003',
        domain: 'Medical Solutions',
        createdAt: '2024-10-03T09:45:00+02:00',
        promisedAt: '2024-10-06T18:00:00+02:00',
        parcels: [
            { id: '1003-1', closed: true },
            { id: '1003-2', closed: false },
        ],
    },
    {
        id: 'order-1004',
        orderNumber: '1004',
        domain: 'First Medical',
        createdAt: '2024-10-04T14:05:00+02:00',
        promisedAt: '2024-10-07T17:00:00+02:00',
        parcels: [
            { id: '1004-1', closed: true },
            { id: '1004-2', closed: true },
        ],
    },
    {
        id: 'order-1005',
        orderNumber: '1005',
        domain: 'E-Commerce',
        createdAt: '2024-10-05T11:20:00+02:00',
        promisedAt: '2024-10-08T13:00:00+02:00',
        parcels: [
            { id: '1005-1', closed: false },
        ],
    },
];

export default orders;
