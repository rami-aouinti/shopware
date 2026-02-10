import template from './lieferzeiten-order-table.html.twig';
import './lieferzeiten-order-table.scss';

Shopware.Component.register('lieferzeiten-order-table', {
    template,

    mixins: ['notification'],

    inject: ['lieferzeitenTrackingService'],

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
            showTrackingModal: false,
            isTrackingLoading: false,
            trackingError: '',
            trackingEvents: [],
            activeTracking: null,
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

        hasViewAccess() {
            return this.acl.can('lieferzeiten.viewer') || this.acl.can('admin');
        },

        hasEditAccess() {
            return this.acl.can('lieferzeiten.editor') || this.acl.can('admin');
        },
        isOrderOpen(order) {
            return order.parcels.some((parcel) => !parcel.closed);
        },

        getEditableOrder(order) {
            if (!this.editableOrders[order.id]) {
                this.$set(this.editableOrders, order.id, {
                    ...order,
                    originalLieferterminLieferantDays: order.lieferterminLieferantDays,
                    originalNeuerLieferterminDays: order.neuerLieferterminDays,
                    additionalDeliveryRequest: order.additionalDeliveryRequest || null,
                });
            }

            this.notifyInitiatorIfClosed(this.editableOrders[order.id]);

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

        resolveTrackingEntries(order, position) {
            const carrier = String(position.trackingCarrier || order.trackingCarrier || '').toLowerCase();
            return (position.packages || []).map((pkg) => {
                if (typeof pkg === 'string') {
                    return { number: pkg, carrier };
                }

                return {
                    number: String(pkg?.number || ''),
                    carrier: String(pkg?.carrier || carrier).toLowerCase(),
                };
            }).filter((entry) => entry.number !== '');
        },

        async openTrackingHistory(entry) {
            if (!entry?.number || !entry?.carrier) {
                this.trackingError = this.$t('lieferzeiten.tracking.missingCarrier');
                this.showTrackingModal = true;
                return;
            }

            this.activeTracking = entry;
            this.showTrackingModal = true;
            this.isTrackingLoading = true;
            this.trackingError = '';
            this.trackingEvents = [];

            try {
                const response = await this.lieferzeitenTrackingService.history(entry.carrier, entry.number);
                if (response?.ok === false) {
                    this.trackingError = response?.message || this.$t('lieferzeiten.tracking.loadError');
                    return;
                }
                this.trackingEvents = Array.isArray(response?.events) ? response.events : [];
            } catch (error) {
                this.trackingError = error?.response?.data?.message || error?.message || this.$t('lieferzeiten.tracking.loadError');
            } finally {
                this.isTrackingLoading = false;
            }
        },

        closeTrackingModal() {
            this.showTrackingModal = false;
            this.isTrackingLoading = false;
            this.trackingError = '';
            this.trackingEvents = [];
            this.activeTracking = null;
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
            if (!this.hasEditAccess()) {
                return;
            }
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
            if (!this.hasEditAccess()) {
                return;
            }
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
            if (!this.hasEditAccess()) {
                return;
            }
            this.pushHistory(order, 'commentHistory', order.comment || '-');
            this.updateAudit(order, this.$t('lieferzeiten.audit.savedComment'));
        },

        requestAdditionalDeliveryDate(order) {
            if (!this.hasEditAccess()) {
                return;
            }
            const initiator = this.$t('lieferzeiten.additionalRequest.defaultInitiator');
            order.additionalDeliveryRequest = {
                requestedAt: new Date().toISOString(),
                initiator,
                notifiedAt: order.additionalDeliveryRequest?.notifiedAt || null,
            };

            this.pushHistory(order, 'commentHistory', this.$t('lieferzeiten.additionalRequest.historyEntry'));
            this.updateAudit(order, this.$t('lieferzeiten.additionalRequest.auditCreated'));
            this.createNotificationSuccess({
                title: this.$t('lieferzeiten.additionalRequest.notificationTitle'),
                message: this.$t('lieferzeiten.additionalRequest.notificationRequested', { initiator }),
            });

            this.notifyInitiatorIfClosed(order);
        },

        notifyInitiatorIfClosed(order) {
            const request = order.additionalDeliveryRequest;
            if (!request || request.notifiedAt || this.isOrderOpen(order)) {
                return;
            }

            request.notifiedAt = new Date().toISOString();
            this.updateAudit(order, this.$t('lieferzeiten.additionalRequest.auditClosed'));

            this.createNotificationInfo({
                title: this.$t('lieferzeiten.additionalRequest.notificationTitle'),
                message: this.$t('lieferzeiten.additionalRequest.notificationClosed', {
                    initiator: request.initiator || this.$t('lieferzeiten.additionalRequest.defaultInitiator'),
                }),
            });
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
