import template from './external-orders-list.html.twig';
import './external-orders-list.scss';

const { Component, Mixin } = Shopware;

Component.register('external-orders-list', {
    template,

    inject: ['externalOrderService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            orders: [],
            summary: {
                orderCount: 0,
                totalRevenue: 0,
                totalItems: 0,
            },
            channels: [
                { id: 'all', label: 'Alle' },
                { id: 'b2b', label: 'B2B-Shop' },
                { id: 'ebay_de', label: 'Ebay.de' },
                { id: 'ebay_at', label: 'Ebay.at' },
                { id: 'kaufland', label: 'Kaufland' },
                { id: 'zonami', label: 'Zonami' },
                { id: 'peg', label: 'PEG' },
            ],
            activeChannel: 'all',
            tableSearchTerm: '',
            selectedOrder: null,
            showDetailModal: false,
            page: 1,
            limit: 10,
            sortBy: 'date',
            sortDirection: 'DESC',
        };
    },

    computed: {
        columns() {
            return [
                { property: 'orderNumber', dataIndex: 'orderNumber', label: 'BestellNr', sortable: true, primary: true },
                { property: 'customerName', dataIndex: 'customerName', label: 'Kundenname', sortable: true },
                { property: 'orderReference', dataIndex: 'orderReference', label: 'AuftragsNr', sortable: true },
                { property: 'email', dataIndex: 'email', label: 'Email', sortable: true },
                { property: 'date', dataIndex: 'date', label: 'Datum', sortable: true },
                { property: 'statusLabel', dataIndex: 'statusLabel', label: 'Bestellstatus', sortable: true },
                { property: 'actions', label: 'Ansicht', sortable: false, width: '90px' },
            ];
        },
        filteredOrders() {
            const searchTerm = this.tableSearchTerm.trim().toLowerCase();

            const channelFilter = this.activeChannel === 'all' ? null : this.activeChannel;
            const filtered = this.orders.filter((order) => {
                if (!channelFilter) {
                    return true;
                }

                const channelValue = order.channel
                    ?? order.channelId
                    ?? order.salesChannelId
                    ?? order.salesChannel;

                return String(channelValue ?? '').toLowerCase() === channelFilter;
            });

            if (!searchTerm) {
                return filtered;
            }

            return filtered.filter((order) => {
                const values = [
                    order.orderNumber,
                    order.customerName,
                    order.orderReference,
                    order.email,
                    order.statusLabel,
                    order.date,
                ];

                return values.some((value) => String(value ?? '').toLowerCase().includes(searchTerm));
            });
        },
        sortedOrders() {
            const sortBy = this.sortBy;
            const direction = this.sortDirection === 'DESC' ? -1 : 1;

            return [...this.filteredOrders].sort((left, right) => {
                const leftValue = this.getSortValue(left, sortBy);
                const rightValue = this.getSortValue(right, sortBy);

                if (leftValue === rightValue) {
                    return 0;
                }

                return leftValue > rightValue ? direction : -direction;
            });
        },
        paginatedOrders() {
            const start = (this.page - 1) * this.limit;
            return this.sortedOrders.slice(start, start + this.limit);
        },
        paginationTotal() {
            return this.sortedOrders.length;
        },
    },

    watch: {
        tableSearchTerm() {
            this.page = 1;
        },
        activeChannel() {
            this.page = 1;
        },
    },

    created() {
        this.loadOrders();
    },

    methods: {
        async loadOrders() {
            this.isLoading = true;

            try {
                const selectedChannel = this.activeChannel === 'all' ? null : this.activeChannel;
                const response = await this.externalOrderService.list({
                    channel: selectedChannel,
                });

                const payload = response?.data?.data ?? response?.data ?? response;

                const apiOrders = Array.isArray(payload?.orders) ? payload.orders : [];
                const fakePayload = this.buildFakeOrders();
                const mergedOrders = apiOrders.length > 0
                    ? [...apiOrders, ...fakePayload.orders]
                    : fakePayload.orders;

                this.orders = mergedOrders;
                this.page = 1;

                if (payload?.summary) {
                    this.summary = {
                        orderCount: mergedOrders.length,
                        totalRevenue: (payload.summary.totalRevenue || 0) + fakePayload.summary.totalRevenue,
                        totalItems: (payload.summary.totalItems || 0) + fakePayload.summary.totalItems,
                    };
                } else {
                    this.summary = {
                        orderCount: mergedOrders.length,
                        totalRevenue: fakePayload.summary.totalRevenue,
                        totalItems: mergedOrders.reduce((sum, order) => sum + (order.totalItems || 0), 0),
                    };
                }
            } catch (error) {
                this.orders = [];
                this.summary = {
                    orderCount: 0,
                    totalRevenue: 0,
                    totalItems: 0,
                };
                this.createNotificationError({
                    title: 'Bestellungen konnten nicht geladen werden',
                    message: error?.message || 'Bitte prüfen Sie die Verbindung zu den externen APIs.',
                });
            } finally {
                this.isLoading = false;
            }
        },

        onPageChange(page) {
            this.page = page;
        },

        onSortColumn(column) {
            const nextSortBy = column?.sortBy
                ?? column?.dataIndex
                ?? column?.property
                ?? column;

            if (!nextSortBy) {
                return;
            }

            const nextDirection = column?.sortDirection ?? column?.direction;

            if (this.sortBy === nextSortBy && !nextDirection) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = nextSortBy;
                this.sortDirection = nextDirection ?? 'ASC';
            }

            this.page = 1;
        },

        async openDetail(order) {
            this.isLoading = true;
            try {
                this.selectedOrder = await this.externalOrderService.detail(order.id);
                this.showDetailModal = true;
            } catch (error) {
                this.createNotificationError({
                    title: 'Bestelldetails konnten nicht geladen werden',
                    message: error?.message || 'Bitte prüfen Sie die Verbindung zu den externen APIs.',
                });
            } finally {
                this.isLoading = false;
            }
        },

        closeDetail() {
            this.showDetailModal = false;
            this.selectedOrder = null;
        },

        formatCurrency(value) {
            return new Intl.NumberFormat('de-DE', {
                style: 'currency',
                currency: 'EUR',
            }).format(value);
        },

        statusVariant(status) {
            if (status === 'processing') {
                return 'info';
            }
            if (status === 'shipped') {
                return 'success';
            }
            if (status === 'closed') {
                return 'danger';
            }
            return 'neutral';
        },

        getSortValue(order, sortBy) {
            if (!sortBy) {
                return '';
            }

            const value = order?.[sortBy];

            if (sortBy.toLowerCase().includes('date')) {
                const parsed = Date.parse(value);
                return Number.isNaN(parsed) ? value ?? '' : parsed;
            }

            if (typeof value === 'number') {
                return value;
            }

            return String(value ?? '').toLowerCase();
        },

        buildFakeOrders() {
            const orders = [
                {
                    id: 'order-1008722',
                    channel: 'ebay_de',
                    orderNumber: '1008722',
                    customerName: 'Andreas Nanke',
                    orderReference: '446487',
                    email: '43ad1d549beae3625950@members.ebay.com',
                    date: '2025-12-30 09:31',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 1,
                },
                {
                    id: 'order-1008720',
                    channel: 'ebay_at',
                    orderNumber: '1008720',
                    customerName: 'Lea Wagner',
                    orderReference: '446475',
                    email: 'lea.wagner@example.at',
                    date: '2025-12-30 08:12',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 3,
                },
                {
                    id: 'order-1008721',
                    channel: 'ebay_de',
                    orderNumber: '1008721',
                    customerName: 'Frank Sagert',
                    orderReference: '446480',
                    email: '010eb3cea0c0a1c80a10@members.ebay.com',
                    date: '2025-12-30 08:46',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 2,
                },
                {
                    id: 'order-1008719',
                    channel: 'b2b',
                    orderNumber: '1008719',
                    customerName: 'MedTec GmbH',
                    orderReference: '446470',
                    email: 'bestellung@medtec.example',
                    date: '2025-12-30 07:45',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 4,
                },
                {
                    id: 'order-1008716',
                    channel: 'kaufland',
                    orderNumber: '1008716',
                    customerName: 'Karsten Stieler',
                    orderReference: '446447',
                    email: '43aab48bab92e321f662@members.kaufland.de',
                    date: '2025-12-29 22:33',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 1,
                },
                {
                    id: 'order-1008713',
                    channel: 'zonami',
                    orderNumber: '1008713',
                    customerName: 'Lina Hoffmann',
                    orderReference: '446431',
                    email: 'lina.hoffmann@zonami.example',
                    date: '2025-12-29 19:18',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 2,
                },
                {
                    id: 'order-1008711',
                    channel: 'peg',
                    orderNumber: '1008711',
                    customerName: 'Jonas Richter',
                    orderReference: '446420',
                    email: 'jonas.richter@peg.example',
                    date: '2025-12-29 18:44',
                    status: 'shipped',
                    statusLabel: 'Versendet',
                    totalItems: 1,
                },
                {
                    id: 'order-1008710',
                    channel: 'ebay_de',
                    orderNumber: '1008710',
                    customerName: 'Sophie Bauer',
                    orderReference: '446410',
                    email: 'sophie.bauer@example.com',
                    date: '2025-12-29 16:05',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 2,
                },
                {
                    id: 'order-1008708',
                    channel: 'b2b',
                    orderNumber: '1008708',
                    customerName: 'HealthLine AG',
                    orderReference: '446398',
                    email: 'sales@healthline.example',
                    date: '2025-12-29 14:22',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 6,
                },
                {
                    id: 'order-1008705',
                    channel: 'kaufland',
                    orderNumber: '1008705',
                    customerName: 'Peter Scholl',
                    orderReference: '446376',
                    email: 'peter.scholl@kaufland.example',
                    date: '2025-12-29 12:05',
                    status: 'closed',
                    statusLabel: 'Abgeschlossen',
                    totalItems: 1,
                },
                {
                    id: 'order-1008703',
                    channel: 'ebay_at',
                    orderNumber: '1008703',
                    customerName: 'Julia Krüger',
                    orderReference: '446360',
                    email: 'julia.krueger@example.at',
                    date: '2025-12-29 10:49',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 2,
                },
                {
                    id: 'order-1008701',
                    channel: 'zonami',
                    orderNumber: '1008701',
                    customerName: 'Tim König',
                    orderReference: '446350',
                    email: 'tim.koenig@zonami.example',
                    date: '2025-12-28 17:03',
                    status: 'shipped',
                    statusLabel: 'Versendet',
                    totalItems: 3,
                },
                {
                    id: 'order-1008699',
                    channel: 'peg',
                    orderNumber: '1008699',
                    customerName: 'Maja Keller',
                    orderReference: '446342',
                    email: 'maja.keller@peg.example',
                    date: '2025-12-28 12:11',
                    status: 'closed',
                    statusLabel: 'Abgeschlossen',
                    totalItems: 1,
                },
                {
                    id: 'order-1008697',
                    channel: 'ebay_de',
                    orderNumber: '1008697',
                    customerName: 'Noah Berg',
                    orderReference: '446330',
                    email: 'noah.berg@example.com',
                    date: '2025-12-28 08:27',
                    status: 'processing',
                    statusLabel: 'Bezahlt / in Bearbeitung',
                    totalItems: 2,
                },
            ];
            const totalItems = orders.reduce((sum, order) => sum + order.totalItems, 0);

            return {
                orders,
                summary: {
                    orderCount: orders.length,
                    totalRevenue: 1584.19,
                    totalItems,
                },
            };
        },

    },
});
