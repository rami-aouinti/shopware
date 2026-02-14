import template from './lieferzeiten-order-table.html.twig';
import './lieferzeiten-order-table.scss';

const BUSINESS_STATUS_SNIPPETS = {
    '1': 'lieferzeiten.businessStatus.new',
    '2': 'lieferzeiten.businessStatus.inClarification',
    '3': 'lieferzeiten.businessStatus.awaitingSupplier',
    '4': 'lieferzeiten.businessStatus.partiallyAvailable',
    '5': 'lieferzeiten.businessStatus.readyForShipping',
    '6': 'lieferzeiten.businessStatus.partiallyShipped',
    '7': 'lieferzeiten.businessStatus.shipped',
    '8': 'lieferzeiten.businessStatus.closed',
};

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
        editableBusinessStatuses() {
            return [7, 8].map((status) => ({
                value: String(status),
                label: `${status} · ${this.$t(BUSINESS_STATUS_SNIPPETS[String(status)])}`,
            }));
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

        resolveBusinessStatus(order) {
            const businessStatus = order?.business_status || order?.businessStatus || null;
            const code = Number(businessStatus?.code ?? order?.status);

            if (!Number.isInteger(code) || code < 1 || code > 8) {
                return '-';
            }

            const labelKey = businessStatus?.labelKey || `lieferzeiten.businessStatus.${code}`;

            return `${code} - ${this.$t(labelKey)}`;
        },

        isOrderOpen(order) {
            const parcels = Array.isArray(order?.parcels) ? order.parcels : [];
            return parcels.some((parcel) => !parcel.closed);
        },

        getEditableOrder(order) {
            if (!this.editableOrders[order.id]) {
                const supplierRange = this.resolveInitialRange(order, 'lieferterminLieferant', 14);
                const newRange = this.resolveInitialRange(order, 'neuerLiefertermin', 4);

                const positions = (order.positions || []).map((position) => ({
                    ...position,
                    lieferterminLieferantRange: { ...supplierRange },
                    originalLieferterminLieferantRange: { ...supplierRange },
                }));

                const parcels = (order.parcels || []).map((parcel) => ({
                    ...parcel,
                    supplierRange: { ...supplierRange },
                    neuerLieferterminRange: { ...newRange },
                    originalNeuerLieferterminRange: { ...newRange },
                }));

                this.$set(this.editableOrders, order.id, {
                    ...order,
                    san6OrderNumberDisplay: this.resolveSan6OrderNumber(order),
                    san6PositionDisplay: this.resolveSan6Position(positions),
                    quantityDisplay: this.resolveQuantity(positions),
                    orderDateDisplay: this.resolveOrderDate(order),
                    paymentMethodDisplay: this.resolvePaymentMethod(order),
                    paymentDateDisplay: this.resolvePaymentDate(order),
                    customerNamesDisplay: this.resolveCustomerNames(order),
                    positionsCountDisplay: positions.length,
                    packageStatusDisplay: this.resolvePackageStatus(order),
                    latestShippingAtDisplay: this.resolveLatestShippingAt(order),
                    shippingDateDisplay: this.resolveShippingDate(order),
                    latestDeliveryAtDisplay: this.resolveLatestDeliveryAt(order),
                    deliveryDateDisplay: this.resolveDeliveryDate(order),
                    lieferterminLieferantRange: supplierRange,
                    neuerLieferterminRange: newRange,
                    originalLieferterminLieferantRange: { ...supplierRange },
                    originalNeuerLieferterminRange: { ...newRange },
                    additionalDeliveryRequest: order.additionalDeliveryRequest || null,
                    latestShippingDeadline: this.resolveDeadlineValue(order, ['spaetester_versand', 'spaetesterVersand', 'latestShippingDeadline']),
                    latestDeliveryDeadline: this.resolveDeadlineValue(order, ['spaeteste_lieferung', 'spaetesteLieferung', 'latestDeliveryDeadline']),
                    selectedBusinessStatus: this.resolveEditableBusinessStatus(order),
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

        resolvePackageStatus(order) {
            const rawStatus = this.pickFirstDefined(order, [
                'packageStatus',
                'paketStatus',
                'paket_status',
                'status',
            ]);

            if (!rawStatus || String(rawStatus).trim() === '') {
                return null;
            }

            const normalized = String(rawStatus).trim();
            const labels = {
                open: this.$t('lieferzeiten.status.open'),
                closed: this.$t('lieferzeiten.status.closed'),
                pending: 'Pending',
                shipped: 'Shipped',
                delivered: 'Delivered',
            };

            return labels[normalized.toLowerCase()] || normalized;
        },

        resolveLatestShippingAt(order) {
            return this.formatDateTime(this.pickFirstDefined(order, [
                'latestShippingDate',
                'spaetesterVersand',
                'spaetester_versand',
                'shippingDateTo',
                'shipping_date_to',
            ]));
        },

        resolveShippingDate(order) {
            return this.formatDate(this.pickFirstDefined(order, [
                'shippingDate',
                'shipping_date',
                'versandDatum',
                'versand_datum',
                'businessDateFrom',
                'business_date_from',
                'shippedAt',
            ]));
        },

        resolveLatestDeliveryAt(order) {
            return this.formatDateTime(this.pickFirstDefined(order, [
                'latestDeliveryDate',
                'spaetesteLieferung',
                'spaeteste_lieferung',
                'deliveryDateTo',
                'delivery_date_to',
            ]));
        },

        resolveDeliveryDate(order) {
            return this.formatDate(this.pickFirstDefined(order, [
                'deliveryDate',
                'delivery_date',
                'lieferDatum',
                'liefer_datum',
                'businessDateTo',
                'business_date_to',
                'deliveredAt',
                'calculatedDeliveryDate',
                'calculated_delivery_date',
            ]));
        },

        pickFirstDefined(source, keys) {
            if (!source || !Array.isArray(keys)) {
                return null;
            }

            for (const key of keys) {
                if (Object.prototype.hasOwnProperty.call(source, key) && source[key] !== null && source[key] !== undefined) {
                    return source[key];
                }
            }

            return null;
        },

        formatDate(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '-';
            }

            return date.toLocaleDateString('de-DE', { timeZone: 'Europe/Berlin' });
        },

        formatDateTime(value) {
            if (!value) {
                return null;
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return null;
            }

            return date.toLocaleString('de-DE', {
                timeZone: 'Europe/Berlin',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            });
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

        businessStatusLabel(order) {
            const statusCode = String(order?.businessStatus?.code || order?.status || '').trim();
            const snippetKey = BUSINESS_STATUS_SNIPPETS[statusCode] || 'lieferzeiten.businessStatus.unknown';
            const fallbackLabel = String(order?.businessStatus?.label || '').trim();
            const translatedLabel = this.$t(snippetKey);
            const resolvedLabel = translatedLabel === snippetKey ? (fallbackLabel || this.$t('lieferzeiten.businessStatus.unknown')) : translatedLabel;

            return `${statusCode || '-'} · ${resolvedLabel}`;
        },

        resolveEditableBusinessStatus(order) {
            const statusCode = Number(order?.businessStatus?.code ?? order?.status ?? 0);

            if (statusCode === 7 || statusCode === 8) {
                return String(statusCode);
            }

            return null;
        },

        canSaveBusinessStatus(order) {
            if (!this.hasEditAccess()) {
                return false;
            }

            const nextStatus = Number(order?.selectedBusinessStatus || 0);
            if (![7, 8].includes(nextStatus)) {
                return false;
            }

            const currentStatus = Number(order?.businessStatus?.code ?? order?.status ?? 0);

            return nextStatus !== currentStatus;
        },

        async saveBusinessStatus(order) {
            if (!this.hasEditAccess()) {
                return;
            }

            const paketId = order?.id || null;
            const status = Number(order?.selectedBusinessStatus || 0);
            if (!paketId || ![7, 8].includes(status)) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten.statusChange.invalidSelection'),
                });
                return;
            }

            try {
                await this.lieferzeitenOrdersService.updateOrderStatus(paketId, status);
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten.statusChange.saved'),
                });
                await this.reloadOrder(order);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error?.response?.data?.message || error?.message || this.$t('global.default.error'),
                });
            }
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

        hasValidNeuerLieferterminRange(order) {
            return Array.isArray(order?.parcels)
                && order.parcels.some((parcel) => this.isRangeValid(parcel.neuerLieferterminRange, 1, 4));
        },

        canSaveLiefertermin(order, target) {
            return this.isRangeValid(target.lieferterminLieferantRange, 1, 14)
                && this.isRangeChanged(target.lieferterminLieferantRange, target.originalLieferterminLieferantRange)
                && this.hasValidNeuerLieferterminRange(order);
        },

        canSaveNeuerLiefertermin(target) {
            const supplierRange = target.supplierRange;
            const newRange = target.neuerLieferterminRange;

            if (!this.isRangeValid(supplierRange, 1, 14)
                || !this.isRangeValid(newRange, 1, 4)
                || !this.isRangeChanged(newRange, target.originalNeuerLieferterminRange)) {
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

        async saveLiefertermin(order, position) {
            if (!this.hasEditAccess()) {
                return;
            }

            if (!this.canSaveLiefertermin(order, position)) {
                this.createNotificationWarning({
                    title: this.$t('global.default.warning'),
                    message: this.$t('lieferzeiten.validation.supplierSaveRequiresCombinedRange'),
                });

                return;
            }

            const positionId = position?.id || null;
            if (!positionId) {
                this.createNotificationError({ title: this.$t('global.default.error'), message: 'Missing position id' });
                return;
            }

            try {
                await this.lieferzeitenOrdersService.updateLieferterminLieferant(positionId, {
                    from: position.lieferterminLieferantRange.from,
                    to: position.lieferterminLieferantRange.to,
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

        async saveNeuerLiefertermin(order, parcel) {
            if (!this.hasEditAccess() || !this.canSaveNeuerLiefertermin(parcel)) {
                return;
            }

            const paketId = parcel?.id || null;
            if (!paketId) {
                this.createNotificationError({ title: this.$t('global.default.error'), message: 'Missing paket id' });
                return;
            }

            try {
                await this.lieferzeitenOrdersService.updateNeuerLieferterminByPaket(paketId, {
                    from: parcel.neuerLieferterminRange.from,
                    to: parcel.neuerLieferterminRange.to,
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

            const positionId = order.commentTargetPositionId;
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

            const positionId = order.commentTargetPositionId;
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

        async reloadOrder(order) {
            if (typeof this.onReloadOrder === 'function') {
                await this.onReloadOrder(order);
                return;
            }

            this.updateAudit(order, this.$t('lieferzeiten.audit.savedComment'));
        },
    },
});
