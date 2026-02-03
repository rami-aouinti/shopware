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
                { id: 'b2b', label: 'B2B-Shop' },
                { id: 'ebay_de', label: 'Ebay.de' },
                { id: 'ebay_at', label: 'Ebay.at' },
                { id: 'kaufland', label: 'Kaufland' },
                { id: 'zonami', label: 'Zonami' },
                { id: 'peg', label: 'PEG' },
            ],
            activeChannel: 'ebay_de',
            searchTerm: '',
            selectedOrder: null,
            showDetailModal: false,
        };
    },

    computed: {
        columns() {
            return [
                { property: 'orderNumber', label: 'BestellNr', sortable: true, primary: true },
                { property: 'customerName', label: 'Kundenname', sortable: true },
                { property: 'orderReference', label: 'AuftragsNr', sortable: true },
                { property: 'email', label: 'Email', sortable: true },
                { property: 'date', label: 'Datum', sortable: true },
                { property: 'statusLabel', label: 'Bestellstatus', sortable: true },
                { property: 'actions', label: 'Ansicht', sortable: false, width: '90px' },
            ];
        },
    },

    created() {
        this.loadOrders();
    },

    methods: {
        async loadOrders() {
            this.isLoading = true;

            try {
                const { orders, summary } = this.buildFakeOrders();

                this.orders = orders;
                this.summary = summary;
            } catch (error) {
                this.createNotificationError({
                    title: 'Bestellungen konnten nicht geladen werden',
                    message: error?.message || 'Bitte prüfen Sie die Verbindung zu den externen APIs.',
                });
            } finally {
                this.isLoading = false;
            }
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

        buildFakeOrders() {
            const orders = [
                {
                    id: 'fake-1001',
                    orderNumber: 'EO-1001',
                    customerName: 'Anna Müller',
                    orderReference: 'A-3901',
                    email: 'anna.mueller@example.com',
                    date: '2024-06-18',
                    statusLabel: 'In Bearbeitung',
                    status: 'processing',
                },
                {
                    id: 'fake-1002',
                    orderNumber: 'EO-1002',
                    customerName: 'Louis Schmidt',
                    orderReference: 'A-3902',
                    email: 'louis.schmidt@example.com',
                    date: '2024-06-17',
                    statusLabel: 'Versendet',
                    status: 'shipped',
                },
                {
                    id: 'fake-1003',
                    orderNumber: 'EO-1003',
                    customerName: 'Sofia Weber',
                    orderReference: 'A-3903',
                    email: 'sofia.weber@example.com',
                    date: '2024-06-16',
                    statusLabel: 'Abgeschlossen',
                    status: 'closed',
                },
            ];

            return {
                orders,
                summary: {
                    orderCount: orders.length,
                    totalRevenue: 1290.45,
                    totalItems: 8,
                },
            };
        },
    },
});
