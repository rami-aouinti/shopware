import template from './lieferzeiten-order-table.html.twig';
import './lieferzeiten-order-table.scss';

Shopware.Component.register('lieferzeiten-order-table', {
    template,

    mixins: ['notification'],

    inject: ['lieferzeitenTrackingService', 'lieferzeitenOrdersService'],

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
        onReloadOrder: {
            type: Function,
            required: false,
            default: null,
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

        async saveLiefertermin(order) {
            if (!this.hasEditAccess() || !this.canSaveLiefertermin(order)) {
                return;
            }

            const positionId = this.getTargetPositionId(order);
            if (!positionId) {
                this.createNotificationError({ title: this.$t('global.default.error'), message: 'Missing position id' });
                return;
            }

            const value = Number(order.lieferterminLieferantDays);

            try {
                await this.lieferzeitenOrdersService.updateLieferterminLieferant(positionId, value);
                this.createNotificationSuccess({ title: this.$t('global.default.success'), message: this.$t('lieferzeiten.audit.savedSupplierDate') });
                await this.reloadOrder(order);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error?.response?.data?.message || error?.message || this.$t('global.default.error'),
                });
            }
        },

        async saveNeuerLiefertermin(order) {
            if (!this.hasEditAccess() || !this.canSaveNeuerLiefertermin(order)) {
                return;
            }

            const positionId = this.getTargetPositionId(order);
            if (!positionId) {
                this.createNotificationError({ title: this.$t('global.default.error'), message: 'Missing position id' });
                return;
            }

            const value = Number(order.neuerLieferterminDays);

            try {
                await this.lieferzeitenOrdersService.updateNeuerLiefertermin(positionId, value);
                this.createNotificationSuccess({ title: this.$t('global.default.success'), message: this.$t('lieferzeiten.audit.savedNewDate') });
                await this.reloadOrder(order);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error?.response?.data?.message || error?.message || this.$t('global.default.error'),
                });
            }
        },

        async saveComment(order) {
            if (!this.hasEditAccess()) {
                return;
            }

            const positionId = this.getTargetPositionId(order);
            if (!positionId) {
                this.createNotificationError({ title: this.$t('global.default.error'), message: 'Missing position id' });
                return;
            }

            try {
                await this.lieferzeitenOrdersService.updateComment(positionId, order.comment || '');
                this.createNotificationSuccess({ title: this.$t('global.default.success'), message: this.$t('lieferzeiten.audit.savedComment') });
                await this.reloadOrder(order);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error?.response?.data?.message || error?.message || this.$t('global.default.error'),
                });
            }
        },

        async requestAdditionalDeliveryDate(order) {
            if (!this.hasEditAccess()) {
                return;
            }

            const positionId = this.getTargetPositionId(order);
            if (!positionId) {
                this.createNotificationError({ title: this.$t('global.default.error'), message: 'Missing position id' });
                return;
            }

            const initiator = this.$t('lieferzeiten.additionalRequest.defaultInitiator');

            try {
                await this.lieferzeitenOrdersService.createAdditionalDeliveryRequest(positionId, initiator);
                this.createNotificationSuccess({
                    title: this.$t('lieferzeiten.additionalRequest.notificationTitle'),
                    message: this.$t('lieferzeiten.additionalRequest.notificationRequested', { initiator }),
                });

                await this.reloadOrder(order);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error?.response?.data?.message || error?.message || this.$t('global.default.error'),
                });
            }
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

        updateAudit(order, action) {
            order.audit = `${action} • ${new Date().toLocaleString('de-DE')}`;
        },

        getTargetPositionId(order) {
            return order?.positions?.[0]?.id || null;
        },

        async reloadOrder(order) {
            if (typeof this.onReloadOrder === 'function') {
                await this.onReloadOrder(order);
                return;
            }

            this.updateAudit(order, this.$t('lieferzeiten.audit.savedComment'));
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
