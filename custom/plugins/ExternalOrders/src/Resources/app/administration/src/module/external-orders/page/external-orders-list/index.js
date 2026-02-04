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
                const selectedChannel = this.activeChannel === 'all' ? null : this.activeChannel;
                const response = await this.externalOrderService.list({
                    channel: selectedChannel,
                    search: this.searchTerm?.trim() || null,
                });

                const orders = Array.isArray(response?.orders) ? response.orders : [];
                this.orders = orders;
                this.summary = response?.summary ?? {
                    orderCount: orders.length,
                    totalRevenue: 0,
                    totalItems: orders.reduce((sum, order) => sum + (order.totalItems || 0), 0),
                };
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

    },
});
