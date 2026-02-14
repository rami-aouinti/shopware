import template from './lieferzeiten-order-table.html.twig';
import './lieferzeiten-order-table.scss';

Shopware.Component.register('lieferzeiten-order-table', {
    template,

    mixins: ['notification'],

    inject: ['lieferzeitenTrackingService', 'lieferzeitenOrdersService', 'acl'],

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
            if (typeof this.acl?.can !== 'function') {
                return false;
            }

            return this.acl.can('lieferzeiten.viewer') || this.acl.can('admin');
        },

        hasEditAccess() {
            if (typeof this.acl?.can !== 'function') {
                return false;
            }

            return this.acl.can('lieferzeiten.editor') || this.acl.can('admin');
        },
        isOrderOpen(order) {
            return order.parcels.some((parcel) => !parcel.closed);
        },

        getEditableOrder(order) {
            if (!this.editableOrders[order.id]) {
                const supplierRange = this.resolveInitialRange(order, 'lieferterminLieferant', 14);
                const newRange = this.resolveInitialRange(order, 'neuerLiefertermin', 4);

                this.$set(this.editableOrders, order.id, {
                    ...order,
                    lieferterminLieferantRange: supplierRange,
                    neuerLieferterminRange: newRange,
                    originalLieferterminLieferantRange: { ...supplierRange },
                    originalNeuerLieferterminRange: { ...newRange },
                    additionalDeliveryRequest: order.additionalDeliveryRequest || null,
                    latestShippingDeadline: this.resolveDeadlineValue(order, ['spaetester_versand', 'spaetesterVersand', 'latestShippingDeadline']),
                    latestDeliveryDeadline: this.resolveDeadlineValue(order, ['spaeteste_lieferung', 'spaetesteLieferung', 'latestDeliveryDeadline']),
                });
            }

            this.notifyInitiatorIfClosed(this.editableOrders[order.id]);

            return this.editableOrders[order.id];
        },

        resolveInitialRange(order, fieldPrefix, fallbackMaxDays) {
            const from = this.normalizeDate(order[`${fieldPrefix}From`]);
            const to = this.normalizeDate(order[`${fieldPrefix}To`] || order[fieldPrefix]);

            if (from && to) {
                return { from, to };
            }

            const fallbackDays = Number(order[`${fieldPrefix}Days`]);
            if (Number.isInteger(fallbackDays) && fallbackDays >= 1 && fallbackDays <= fallbackMaxDays) {
                return this.buildRangeFromDayOffset(fallbackDays);
            }

            return { from: null, to: null };
        },

        normalizeDate(value) {
            if (!value) {
                return null;
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return null;
            }

            return date.toISOString().slice(0, 10);
        },

        buildRangeFromDayOffset(days) {
            const fromDate = new Date();
            fromDate.setDate(fromDate.getDate() + 1);
            const toDate = new Date();
            toDate.setDate(toDate.getDate() + days);

            return {
                from: fromDate.toISOString().slice(0, 10),
                to: toDate.toISOString().slice(0, 10),
            };
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

        resolveDeadlineValue(order, keys) {
            const value = keys.map((key) => order[key]).find((candidate) => candidate);

            if (!value) {
                return null;
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return null;
            }

            return date.toISOString();
        },

        formatDateTime(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '-';
            }

            return new Intl.DateTimeFormat('de-DE', {
                dateStyle: 'short',
                timeStyle: 'short',
            }).format(date);
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

        rangeToDays(range) {
            if (!range?.from || !range?.to) {
                return null;
            }

            const from = new Date(range.from);
            const to = new Date(range.to);
            if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) {
                return null;
            }

            return Math.floor((to.getTime() - from.getTime()) / 86400000) + 1;
        },

        isRangeValid(range, minDays, maxDays) {
            const days = this.rangeToDays(range);
            return Number.isInteger(days) && days >= minDays && days <= maxDays;
        },

        isRangeChanged(range, originalRange) {
            return (range?.from || null) !== (originalRange?.from || null)
                || (range?.to || null) !== (originalRange?.to || null);
        },

        canSaveLiefertermin(order) {
            return this.isRangeValid(order.lieferterminLieferantRange, 1, 14)
                && this.isRangeChanged(order.lieferterminLieferantRange, order.originalLieferterminLieferantRange);
        },

        canSaveNeuerLiefertermin(order) {
            const supplierRange = order.lieferterminLieferantRange;
            const newRange = order.neuerLieferterminRange;

            if (!this.isRangeValid(supplierRange, 1, 14)
                || !this.isRangeValid(newRange, 1, 4)
                || !this.isRangeChanged(newRange, order.originalNeuerLieferterminRange)) {
                return false;
            }

            return newRange.from >= supplierRange.from && newRange.to <= supplierRange.to;
        },



        resolveConcurrencyToken(order) {
            return order.updatedAt || order.lastChangedAt || order.currentUpdatedAt || null;
        },

        applyConflictRefresh(order, refresh) {
            if (!refresh || refresh.exists === false) {
                return;
            }

            if (refresh.updatedAt) {
                order.updatedAt = refresh.updatedAt;
            }
            if (refresh.lastChangedAt) {
                order.lastChangedAt = refresh.lastChangedAt;
            }
            if (Object.prototype.hasOwnProperty.call(refresh, 'comment')) {
                order.comment = refresh.comment || '';
            }

            if (refresh.lieferterminLieferant) {
                order.lieferterminLieferantRange = {
                    from: refresh.lieferterminLieferant.from || null,
                    to: refresh.lieferterminLieferant.to || null,
                };
                order.originalLieferterminLieferantRange = { ...order.lieferterminLieferantRange };
            }

            if (refresh.neuerLiefertermin) {
                order.neuerLieferterminRange = {
                    from: refresh.neuerLiefertermin.from || null,
                    to: refresh.neuerLiefertermin.to || null,
                };
                order.originalNeuerLieferterminRange = { ...order.neuerLieferterminRange };
            }
        },

        handleConflictError(error, order) {
            const data = error?.response?.data;
            if (data?.code !== 'CONCURRENT_MODIFICATION') {
                return false;
            }

            this.applyConflictRefresh(order, data?.refresh || null);
            this.createNotificationWarning({
                title: this.$t('global.default.warning'),
                message: data?.message || 'Conflit d’édition détecté. Ligne rafraîchie, veuillez réappliquer vos modifications.',
            });

            return true;
        },
        weekLabelFromDate(dateValue) {
            if (!dateValue) {
                return '-';
            }

            const base = new Date(dateValue);
            if (Number.isNaN(base.getTime())) {
                return '-';
            }

            const date = new Date(Date.UTC(base.getFullYear(), base.getMonth(), base.getDate()));
            const dayNum = date.getUTCDay() || 7;
            date.setUTCDate(date.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
            const week = Math.ceil((((date - yearStart) / 86400000) + 1) / 7);

            return `KW ${week}`;
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

            try {
                await this.lieferzeitenOrdersService.updateLieferterminLieferant(positionId, {
                    from: order.lieferterminLieferantRange.from,
                    to: order.lieferterminLieferantRange.to,
                    updatedAt: this.resolveConcurrencyToken(order),
                });
                this.createNotificationSuccess({ title: this.$t('global.default.success'), message: this.$t('lieferzeiten.audit.savedSupplierDate') });
                await this.reloadOrder(order);
            } catch (error) {
                if (this.handleConflictError(error, order)) {
                    return;
                }

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

            try {
                await this.lieferzeitenOrdersService.updateNeuerLiefertermin(positionId, {
                    from: order.neuerLieferterminRange.from,
                    to: order.neuerLieferterminRange.to,
                    updatedAt: this.resolveConcurrencyToken(order),
                });
                this.createNotificationSuccess({ title: this.$t('global.default.success'), message: this.$t('lieferzeiten.audit.savedNewDate') });
                await this.reloadOrder(order);
            } catch (error) {
                if (this.handleConflictError(error, order)) {
                    return;
                }

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
                await this.lieferzeitenOrdersService.updateComment(positionId, {
                    comment: order.comment || '',
                    updatedAt: this.resolveConcurrencyToken(order),
                });
                this.createNotificationSuccess({ title: this.$t('global.default.success'), message: this.$t('lieferzeiten.audit.savedComment') });
                await this.reloadOrder(order);
            } catch (error) {
                if (this.handleConflictError(error, order)) {
                    return;
                }

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
    },
});
