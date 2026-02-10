import template from './lieferzeiten-order-table.html.twig';

Shopware.Component.register('lieferzeiten-order-table', {
    template,

    props: {
        orders: {
            type: Array,
            required: true,
            default: () => [],
        },
        onlyOpen: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            expandedOrderIds: [],
            editableOrders: {},
        };
    },

    computed: {
        displayedOrders() {
            return this.orders
                .filter((order) => (this.onlyOpen ? this.isOrderOpen(order) : true))
                .map((order) => this.getEditableOrder(order));
        },
    },

    methods: {
        isOrderOpen(order) {
            return order.parcels.some((parcel) => !parcel.closed);
        },

        getEditableOrder(order) {
            if (!this.editableOrders[order.id]) {
                this.$set(this.editableOrders, order.id, {
                    ...order,
                    originalLieferterminLieferantDays: order.lieferterminLieferantDays,
                    originalNeuerLieferterminDays: order.neuerLieferterminDays,
                });
            }

            return this.editableOrders[order.id];
        },

        toggleOrder(orderId) {
            if (this.expandedOrderIds.includes(orderId)) {
                this.expandedOrderIds = this.expandedOrderIds.filter((id) => id !== orderId);
                return;
            }

            this.expandedOrderIds = [...this.expandedOrderIds, orderId];
        },

        isExpanded(orderId) {
            return this.expandedOrderIds.includes(orderId);
        },

        parcelSummary(order) {
            const openParcels = order.parcels.filter((parcel) => !parcel.closed).length;
            return `${openParcels}/${order.parcels.length}`;
        },

        shippingLabel(order) {
            if (order.versandart === 'teil') {
                return `${this.$t('lieferzeiten.shipping.partial')} (${order.partialShipment})`;
            }

            const labels = {
                unklar: this.$t('lieferzeiten.shipping.unclear'),
                gesamt: this.$t('lieferzeiten.shipping.complete'),
                trennung: this.$t('lieferzeiten.shipping.split'),
            };

            return labels[order.versandart] || this.$t('lieferzeiten.shipping.unclear');
        },

        canSaveLiefertermin(order) {
            const value = Number(order.lieferterminLieferantDays);
            return Number.isInteger(value) && value >= 1 && value <= 14 && value !== order.originalLieferterminLieferantDays;
        },

        canSaveNeuerLiefertermin(order) {
            const parentValue = Number(order.lieferterminLieferantDays);
            const value = Number(order.neuerLieferterminDays);

            return Number.isInteger(parentValue)
                && parentValue >= 1
                && parentValue <= 14
                && Number.isInteger(value)
                && value >= 1
                && value <= 4
                && value !== order.originalNeuerLieferterminDays;
        },

        saveLiefertermin(order) {
            if (!this.canSaveLiefertermin(order)) {
                return;
            }

            const value = Number(order.lieferterminLieferantDays);
            order.lieferterminLieferantDays = value;
            order.lieferterminLieferantKw = this.toWeekLabel(value);
            order.originalLieferterminLieferantDays = value;
            this.pushHistory(order, 'lieferterminLieferantHistory', `${value} ${this.$t('lieferzeiten.fields.days')}`);
            this.updateAudit(order, this.$t('lieferzeiten.audit.savedSupplierDate'));
        },

        saveNeuerLiefertermin(order) {
            if (!this.canSaveNeuerLiefertermin(order)) {
                return;
            }

            const value = Number(order.neuerLieferterminDays);
            order.neuerLieferterminDays = value;
            order.originalNeuerLieferterminDays = value;
            this.pushHistory(order, 'neuerLieferterminHistory', `${value} ${this.$t('lieferzeiten.fields.days')}`);
            this.updateAudit(order, this.$t('lieferzeiten.audit.savedNewDate'));
        },

        saveComment(order) {
            this.pushHistory(order, 'commentHistory', order.comment || '-');
            this.updateAudit(order, this.$t('lieferzeiten.audit.savedComment'));
        },

        pushHistory(order, key, value) {
            const entry = `${new Date().toLocaleString('de-DE')}: ${value}`;
            order[key] = [entry, ...(order[key] || [])].slice(0, 5);
        },

        updateAudit(order, action) {
            order.audit = `${action} • ${new Date().toLocaleString('de-DE')}`;
        },

        toWeekLabel(days) {
            const base = new Date();
            base.setDate(base.getDate() + days);

            const date = new Date(Date.UTC(base.getFullYear(), base.getMonth(), base.getDate()));
            const dayNum = date.getUTCDay() || 7;
            date.setUTCDate(date.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
            const week = Math.ceil((((date - yearStart) / 86400000) + 1) / 7);

            return `KW ${week}`;
        },
    },
});
