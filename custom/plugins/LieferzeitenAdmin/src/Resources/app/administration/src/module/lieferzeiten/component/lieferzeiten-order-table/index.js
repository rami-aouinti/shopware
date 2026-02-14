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

const INTERNAL_SHIPPING_LABEL = 'Versand durch First Medical';

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
            statusUpdateLoadingByOrder: {},
            detailsLoadingByOrder: {},
            additionalRequestTaskStatusByPosition: {},
            additionalRequestTaskInitialized: false,
        };
    },


    watch: {
        orders: {
            handler(newOrders) {
                this.handleAdditionalDeliveryRequestTaskTransitions(newOrders);
            },
            immediate: true,
        },
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

        canUpdateOrderStatus(order) {
            return this.hasEditAccess() && !!order?.id;
        },

        statusOptions() {
            return [7, 8].map((code) => ({
                value: code,
                label: this.$t(BUSINESS_STATUS_SNIPPETS[String(code)]),
            }));
        },

        statusOptionLabel(code) {
            const snippet = BUSINESS_STATUS_SNIPPETS[String(code)] || 'lieferzeiten.businessStatus.unknown';
            return `${code} · ${this.$t(snippet)}`;
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

                const parcels = (order.parcels || []).map((parcel) => {
                    const parcelNewRange = this.resolveParcelInitialRange(parcel, newRange);

                    return {
                        ...parcel,
                        supplierRange: { ...supplierRange },
                        neuerLieferterminRange: { ...parcelNewRange },
                        originalNeuerLieferterminRange: { ...parcelNewRange },
                        statusDisplay: this.resolveParcelStatus(parcel),
                    };
                });

                const commentTargetPositionId = this.resolveCommentTargetPositionId(positions);

                this.$set(this.editableOrders, order.id, {
                    ...order,
                    san6OrderNumberDisplay: this.resolveSan6OrderNumber(order),
                    san6PositionDisplay: this.resolveSan6Position(order, positions),
                    quantityDisplay: this.resolveQuantity(order, positions),
                    orderDateDisplay: this.resolveOrderDate(order),
                    paymentMethodDisplay: this.resolvePaymentMethod(order),
                    paymentDateDisplay: this.resolvePaymentDate(order),
                    customerNamesDisplay: this.resolveCustomerNames(order),
                    positionsCountDisplay: positions.length,
                    packageStatusDisplay: this.resolvePackageStatus(order),
                    trackingSummaryDisplay: this.resolveTrackingSummaryDisplay(order),
                    latestShippingDeadlineDisplay: this.formatDateTime(this.resolveDeadlineValue(order, [
                        'latestShippingDeadline',
                        'spaetester_versand',
                        'spaetesterVersand',
                        'latestShippingDate',
                    ])),
                    shippingDateDisplay: this.resolveShippingDate(order),
                    latestDeliveryDeadlineDisplay: this.formatDateTime(this.resolveDeadlineValue(order, [
                        'latestDeliveryDeadline',
                        'spaeteste_lieferung',
                        'spaetesteLieferung',
                        'latestDeliveryDate',
                    ])),
                    deliveryDateDisplay: this.resolveDeliveryDate(order),
                    lieferterminLieferantRange: supplierRange,
                    neuerLieferterminRange: newRange,
                    originalLieferterminLieferantRange: { ...supplierRange },
                    originalNeuerLieferterminRange: { ...newRange },
                    comment: this.resolveInitialComment(order),
                    commentTargetPositionId,
                    additionalDeliveryRequest: order.additionalDeliveryRequest || null,
                    additionalDeliveryRequestTasksByPosition: this.mapAdditionalDeliveryRequestTasksByPosition(positions),
                    latestShippingDeadline: this.resolveDeadlineValue(order, ['spaetester_versand', 'spaetesterVersand', 'latestShippingDeadline']),
                    latestDeliveryDeadline: this.resolveDeadlineValue(order, ['spaeteste_lieferung', 'spaetesteLieferung', 'latestDeliveryDeadline']),
                    selectedManualStatus: [7, 8].includes(Number(order?.status)) ? Number(order.status) : 7,
                });
            }

            return this.editableOrders[order.id];
        },

        resolveInitialComment(order) {
            const rawComment = this.pickFirstDefined(order, ['currentComment', 'comment']);

            if (rawComment === null || rawComment === undefined) {
                return '';
            }

            return String(rawComment);
        },

        isOpenPosition(position) {
            const normalized = String(position?.status || '').trim().toLowerCase();

            if (position?.closed === true) {
                return false;
            }

            return !['closed', 'done', 'completed', 'shipped', 'delivered', '8'].includes(normalized);
        },

        resolveCommentTargetPositionId(positions) {
            if (!Array.isArray(positions) || positions.length === 0) {
                return null;
            }

            const firstOpenPosition = positions.find((position) => this.isOpenPosition(position) && position?.id);
            if (firstOpenPosition?.id) {
                return firstOpenPosition.id;
            }

            return positions.find((position) => !!position?.id)?.id || null;
        },

        canSaveComment(order) {
            return this.hasEditAccess() && !!this.getValidCommentTargetPositionId(order);
        },

        getValidCommentTargetPositionId(order) {
            if (!order || !Array.isArray(order.positions)) {
                return null;
            }

            const currentTargetPositionId = order.commentTargetPositionId;
            if (currentTargetPositionId
                && order.positions.some((position) => position?.id === currentTargetPositionId)) {
                return currentTargetPositionId;
            }

            return this.resolveCommentTargetPositionId(order.positions);
        },

        ensureCommentTargetPositionId(order) {
            const validPositionId = this.getValidCommentTargetPositionId(order);

            if (order && order.commentTargetPositionId !== validPositionId) {
                this.$set(order, 'commentTargetPositionId', validPositionId);
            }

            return validPositionId;
        },


        resolveParcelInitialRange(parcel, fallbackRange) {
            const from = this.normalizeDate(parcel?.neuerLieferterminFrom || parcel?.neuer_liefertermin_from || parcel?.neuerLiefertermin?.from);
            const to = this.normalizeDate(parcel?.neuerLieferterminTo || parcel?.neuer_liefertermin_to || parcel?.neuerLiefertermin?.to || parcel?.neuerLiefertermin);

            if (from && to) {
                return { from, to };
            }

            return { ...fallbackRange };
        },

        resolveParcelStatus(parcel) {
            const status = String(parcel?.status || '').trim();

            if (status === '') {
                return parcel?.closed ? this.$t('lieferzeiten.status.closed') : this.$t('lieferzeiten.status.open');
            }

            return status;
        },

        isParcelEditableByStatus(parcel) {
            const status = String(parcel?.status || '').trim().toLowerCase();

            if (parcel?.closed === true) {
                return false;
            }

            return !['closed', 'done', 'completed', 'shipped', 'delivered', '8'].includes(status);
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

        async toggleOrder(orderId) {
            if (this.expandedOrderIds.includes(orderId)) {
                this.expandedOrderIds = this.expandedOrderIds.filter((id) => id !== orderId);
                return;
            }

            await this.loadOrderDetails(orderId);

            this.expandedOrderIds = [...this.expandedOrderIds, orderId];
        },

        hasOrderDetails(order) {
            return Array.isArray(order?.positions)
                && Array.isArray(order?.parcels)
                && Array.isArray(order?.lieferterminLieferantHistory)
                && Array.isArray(order?.neuerLieferterminHistory)
                && Array.isArray(order?.commentHistory);
        },

        mergeOrderDetails(orderId, details) {
            const source = this.editableOrders[orderId] || this.orders.find((item) => item?.id === orderId);
            if (!source || !details) {
                return;
            }

            const mergedOrder = {
                ...source,
                ...details,
            };

            this.$delete(this.editableOrders, orderId);
            const editable = this.getEditableOrder(mergedOrder);

            this.$set(this.editableOrders, orderId, {
                ...editable,
                lieferterminLieferantHistory: details.lieferterminLieferantHistory || [],
                neuerLieferterminHistory: details.neuerLieferterminHistory || [],
                commentHistory: details.commentHistory || [],
                trackingSummaryDisplay: this.resolveTrackingSummaryDisplay(editable),
            });

            this.handleAdditionalDeliveryRequestTaskTransitions(Object.values(this.editableOrders));
        },

        async loadOrderDetails(orderId) {
            if (!orderId) {
                return;
            }

            const existing = this.editableOrders[orderId] || this.orders.find((order) => order.id === orderId);
            if (this.hasOrderDetails(existing)) {
                return;
            }

            this.$set(this.detailsLoadingByOrder, orderId, true);
            try {
                const details = await this.lieferzeitenOrdersService.getOrderDetails(orderId);
                this.mergeOrderDetails(orderId, details);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error?.response?.data?.message || error?.message || this.$t('global.default.error'),
                });
            } finally {
                this.$set(this.detailsLoadingByOrder, orderId, false);
            }
        },

        isDetailsLoading(orderId) {
            return this.detailsLoadingByOrder[orderId] === true;
        },

        isExpanded(orderId) {
            return this.expandedOrderIds.includes(orderId);
        },

        parcelSummary(order) {
            const parcels = Array.isArray(order?.parcels) ? order.parcels : [];
            const openParcels = parcels.filter((parcel) => !parcel.closed).length;
            return `${openParcels}/${parcels.length}`;
        },

        displayOrDash(value) {
            if (value === null || value === undefined) {
                return '-';
            }

            const normalized = String(value).trim();
            return normalized === '' ? '-' : normalized;
        },

        resolveSan6OrderNumber(order) {
            return this.pickFirstDefined(order, ['san6OrderNumber']);
        },

        resolveSan6Position(order, positions) {
            if (!positions.length) {
                return this.displayOrDash(this.pickFirstDefined(order, ['san6Position', 'san6Pos']));
            }

            const values = positions
                .map((position) => this.pickFirstDefined(position, ['number']))
                .filter((value) => value !== null && value !== undefined && String(value).trim() !== '');

            return values.length ? values.join(', ') : '-';
        },

        resolveQuantity(order, positions) {
            if (!positions.length) {
                return this.displayOrDash(this.pickFirstDefined(order, ['quantity', 'positionsCount']));
            }

            const total = positions.reduce((acc, position) => {
                const raw = this.pickFirstDefined(position, ['quantity', 'orderedQuantity', 'menge']);
                const numeric = Number(raw);
                return Number.isFinite(numeric) ? acc + numeric : acc;
            }, 0);

            return total > 0 ? String(total) : '-';
        },

        resolveOrderDate(order) {
            return this.formatDate(this.pickFirstDefined(order, ['orderDate']));
        },

        resolvePaymentMethod(order) {
            return this.pickFirstDefined(order, ['paymentMethod']) || '-';
        },

        resolvePaymentDate(order) {
            return this.formatDate(this.pickFirstDefined(order, ['paymentDate']));
        },

        resolveCustomerNames(order) {
            const primaryParts = [
                this.pickFirstDefined(order, ['customerFirstName', 'customer_first_name']),
                this.pickFirstDefined(order, ['customerLastName', 'customer_last_name']),
            ];

            const additionalParts = [
                this.pickFirstDefined(order, ['customerAdditionalName', 'customer_additional_name']),
                this.pickFirstDefined(order, ['customerNames', 'customer_names']),
            ];

            const nameParts = [...primaryParts, ...additionalParts]
                .map((value) => (value === null || value === undefined ? '' : String(value).trim()))
                .filter((value) => value !== '');

            if (nameParts.length > 0) {
                return nameParts.join(' ');
            }

            return this.pickFirstDefined(order, ['customerEmail', 'customer_email']) || '-';
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
            const rawStatus = this.pickFirstDefined(order, ['packageStatus', 'status']);

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
            return this.formatDateTime(this.pickFirstDefined(order, ['latestShippingDate']));
        },

        resolveShippingDate(order) {
            return this.formatDate(this.pickFirstDefined(order, ['shippingDate', 'businessDateFrom']));
        },

        resolveLatestDeliveryAt(order) {
            return this.formatDateTime(this.pickFirstDefined(order, ['latestDeliveryDate']));
        },

        resolveDeliveryDate(order) {
            return this.formatDate(this.pickFirstDefined(order, ['deliveryDate', 'businessDateTo', 'calculatedDeliveryDate']));
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

        formatHistoryEntry(entry) {
            if (typeof entry === 'string') {
                return entry;
            }

            if (!entry || typeof entry !== 'object') {
                return '-';
            }

            const actor = String(entry.lastChangedBy || entry.user || entry.actor || 'system').trim() || 'system';
            const changedAt = this.formatDateTime(entry.lastChangedAt || entry.createdAt || entry.timestamp || null);

            if (entry.label) {
                return changedAt ? `${entry.label}: ${actor} · ${changedAt}` : `${entry.label}: ${actor}`;
            }

            return changedAt ? `${actor} · ${changedAt}` : actor;
        },

        resolveAuditDisplay(order) {
            if (!order || typeof order !== 'object') {
                return '-';
            }

            const actor = String(order.lastChangedBy || order.user || '').trim();
            const changedAt = this.formatDateTime(order.lastChangedAt || order.updatedAt || order.currentUpdatedAt || null);

            if (actor && changedAt) {
                return `${actor} · ${changedAt}`;
            }

            if (actor) {
                return actor;
            }

            if (changedAt) {
                return changedAt;
            }

            return order.audit || '-';
        },

        resolveTrackingEntries(order, position) {
            if (Array.isArray(position?.trackingEntries) && position.trackingEntries.length > 0) {
                return position.trackingEntries.map((entry) => ({
                    number: String(entry?.number || '').trim(),
                    carrier: String(entry?.carrier || '').trim().toLowerCase(),
                })).filter((entry) => entry.number !== '');
            }

            const carrier = String(position.trackingCarrier || order.trackingCarrier || '').toLowerCase();
            const packageEntries = Array.isArray(position.packages) ? position.packages : [];
            const fallbackPackages = packageEntries.length === 0
                ? [position.sendenummer || order.sendenummer || '']
                : packageEntries;

            return fallbackPackages.map((pkg) => {
                if (typeof pkg === 'string') {
                    const number = String(pkg || '').trim();
                    if (number.toLowerCase() === INTERNAL_SHIPPING_LABEL.toLowerCase()) {
                        return { number, carrier: 'internal', isInternal: true };
                    }

                    return { number, carrier };
                }

                const number = String(pkg?.number || '').trim();
                if (number.toLowerCase() === INTERNAL_SHIPPING_LABEL.toLowerCase()) {
                    return { number, carrier: 'internal', isInternal: true };
                }

                return {
                    number,
                    carrier: String(pkg?.carrier || carrier).toLowerCase(),
                };
            }).filter((entry) => entry.number !== '');
        },

        resolveTrackingSummaryDisplay(order) {
            const entries = this.resolveOrderTrackingEntries(order)
                .filter((entry) => ['dhl', 'gls'].includes(entry.carrier));

            if (entries.length > 0) {
                return entries.map((entry) => entry.number).join(', ');
            }

            if (this.isInternalShippingMode(order)) {
                return 'Versand durch First Medical';
            }

            return '-';
        },

        isInternalShippingMode(order) {
            const candidates = [
                order?.shippingMode,
                order?.shipping_mode,
                order?.shippingType,
                order?.shipping_type,
                order?.shippingMethod,
                order?.shipping_method,
                order?.versandart,
                order?.versandArt,
                order?.versand_art,
            ].filter((value) => value !== null && value !== undefined);

            if (order?.internalShipping === true || order?.isInternalShipping === true) {
                return true;
            }

            return candidates.some((value) => {
                const normalized = String(value).toLowerCase();
                return normalized.includes('internal')
                    || normalized.includes('intern')
                    || normalized.includes('first medical')
                    || normalized.includes('hausversand');
            });
        },

        async openTrackingHistory(entry) {
            if (entry?.isInternal) {
                this.activeTracking = entry;
                this.trackingError = this.$t('lieferzeiten.tracking.internalShipmentInfo');
                this.trackingEvents = [];
                this.isTrackingLoading = false;
                this.showTrackingModal = true;

                return;
            }

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


        resolveLastChangedBy(order) {
            const changedBy = this.pickFirstDefined(order, ['lastChangedBy', 'user']);

            return changedBy ? String(changedBy).trim() : null;
        },

        resolveBusinessStatusDisplay(order) {
            const businessStatus = order?.business_status || order?.businessStatus || null;
            const statusCode = String(businessStatus?.code || order?.status || '').trim();
            const statusLabel = String(
                businessStatus?.label
                || order?.business_status_label
                || order?.statusLabel
                || '',
            ).trim();

            if (!statusCode && !statusLabel) {
                return null;
            }

            if (!statusCode) {
                return statusLabel;
            }

            if (!statusLabel) {
                return statusCode;
            }

            return `${statusCode} · ${statusLabel}`;
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
            return this.shippingLabelForPosition(order, null);
        },

        shippingLabelForPosition(order, position) {
            const shippingType = this.normalizeShippingType(order?.shippingAssignmentType || order?.versandart);
            const orderedQuantity = this.parseQuantity(position?.orderedQuantity);
            const shippedQuantity = this.parseQuantity(position?.shippedQuantity);

            if (shippingType === 'unknown') {
                return this.$t('lieferzeiten.shipping.unclear');
            }

            if (shippingType === 'gesamt') {
                return this.$t('lieferzeiten.shipping.completeShipment');
            }

            if (shippingType === 'teil') {
                return `${this.$t('lieferzeiten.shipping.partialShipment')} ${this.positionQuantitySuffix(shippedQuantity, orderedQuantity)}`;
            }

            if (shippingType === 'trennung') {
                return `${this.$t('lieferzeiten.shipping.splitPosition')} ${this.positionQuantitySuffix(shippedQuantity, orderedQuantity)}`;
            }

            return this.$t('lieferzeiten.shipping.unclear');
        },

        normalizeShippingType(value) {
            const normalized = String(value || '').trim().toLowerCase();

            if (normalized === '') {
                return 'unknown';
            }

            if (['gesamt', 'complete', 'all_in_one', 'full'].includes(normalized)) {
                return 'gesamt';
            }

            if (['teil', 'partial', 'teillieferung', 'partial_shipment'].includes(normalized)) {
                return 'teil';
            }

            if (['trennung', 'split', 'split_position'].includes(normalized)) {
                return 'trennung';
            }

            return normalized;
        },

        parseQuantity(value) {
            if (value === null || value === undefined || value === '') {
                return null;
            }

            const numeric = Number(value);
            if (!Number.isFinite(numeric) || numeric < 0) {
                return null;
            }

            return Math.round(numeric);
        },

        positionQuantitySuffix(shippedQuantity, orderedQuantity) {
            const shipped = shippedQuantity ?? 0;
            const ordered = orderedQuantity ?? 0;

            return `${shipped}/${ordered} ${this.$t('lieferzeiten.shipping.pieces')}`;
        },

        positionQuantityDisplay(position) {
            const orderedQuantity = this.parseQuantity(position?.orderedQuantity);
            const shippedQuantity = this.parseQuantity(position?.shippedQuantity);

            if (orderedQuantity !== null || shippedQuantity !== null) {
                return this.positionQuantitySuffix(shippedQuantity, orderedQuantity);
            }

            const fallbackQuantity = this.pickFirstDefined(position, ['quantity', 'menge']);

            return this.displayOrDash(fallbackQuantity);
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

            if (!this.isParcelEditableByStatus(target)
                || !this.isRangeValid(supplierRange, 1, 14)
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
            if (!this.canSaveComment(order)) {
                return;
            }

            const positionId = this.ensureCommentTargetPositionId(order);

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

        async requestAdditionalDeliveryDate(order, positionId) {
            if (!this.hasEditAccess()) {
                return;
            }

            if (!positionId) {
                this.createNotificationError({ title: this.$t('global.default.error'), message: 'Missing position id' });
                return;
            }

            const initiator = this.resolveAdditionalRequestInitiator();

            try {
                await this.lieferzeitenOrdersService.createAdditionalDeliveryRequest(positionId, initiator);
                this.createNotificationSuccess({
                    title: this.$t('lieferzeiten.additionalRequest.notificationTitle'),
                    message: this.$t('lieferzeiten.additionalRequest.notificationRequested', {
                        initiator: initiator?.display || this.$t('lieferzeiten.additionalRequest.defaultInitiator'),
                    }),
                });

                await this.reloadOrder(order);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error?.response?.data?.message || error?.message || this.$t('global.default.error'),
                });
            }
        },


        async saveOrderStatus(order) {
            if (!this.canUpdateOrderStatus(order)) {
                return;
            }

            const targetStatus = Number(order.selectedManualStatus);
            if (![7, 8].includes(targetStatus)) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten.status.manual.invalid'),
                });
                return;
            }

            this.$set(this.statusUpdateLoadingByOrder, order.id, true);

            try {
                await this.lieferzeitenOrdersService.updatePaketStatus(order.id, {
                    status: targetStatus,
                    updatedAt: this.resolveConcurrencyToken(order),
                });

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten.status.manual.updated', { status: targetStatus }),
                });

                await this.reloadOrder(order);
            } catch (error) {
                if (this.handleConflictError(error, order)) {
                    return;
                }

                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error?.response?.data?.message || error?.message || this.$t('global.default.error'),
                });
            } finally {
                this.$set(this.statusUpdateLoadingByOrder, order.id, false);
            }
        },

        mapAdditionalDeliveryRequestTasksByPosition(positions) {
            if (!Array.isArray(positions) || positions.length === 0) {
                return {};
            }

            return positions.reduce((accumulator, position) => {
                if (!position?.id) {
                    return accumulator;
                }

                accumulator[position.id] = position.additionalDeliveryRequestTask || null;
                return accumulator;
            }, {});
        },

        extractAdditionalDeliveryRequestTaskStatusByPosition(orders) {
            const result = {};
            const normalizedOrders = Array.isArray(orders) ? orders : [];

            normalizedOrders.forEach((order) => {
                const positions = Array.isArray(order?.positions) ? order.positions : [];
                positions.forEach((position) => {
                    if (!position?.id) {
                        return;
                    }

                    const task = position.additionalDeliveryRequestTask;
                    if (!task || typeof task !== 'object') {
                        return;
                    }

                    result[position.id] = {
                        status: typeof task.status === 'string' ? task.status.trim().toLowerCase() : null,
                        closedAt: task.closedAt || null,
                        initiator: task.initiator || null,
                    };
                });
            });

            return result;
        },

        isAdditionalDeliveryRequestTaskClosed(status) {
            return ['done', 'cancelled'].includes(String(status || '').trim().toLowerCase());
        },

        handleAdditionalDeliveryRequestTaskTransitions(orders) {
            const currentStatusByPosition = this.extractAdditionalDeliveryRequestTaskStatusByPosition(orders);

            if (!this.additionalRequestTaskInitialized) {
                this.additionalRequestTaskStatusByPosition = currentStatusByPosition;
                this.additionalRequestTaskInitialized = true;
                return;
            }

            Object.entries(currentStatusByPosition).forEach(([positionId, currentTask]) => {
                const previousTask = this.additionalRequestTaskStatusByPosition[positionId] || null;
                const movedToClosed = previousTask
                    && !this.isAdditionalDeliveryRequestTaskClosed(previousTask.status)
                    && this.isAdditionalDeliveryRequestTaskClosed(currentTask.status);

                if (!movedToClosed) {
                    return;
                }

                this.createNotificationInfo({
                    title: this.$t('lieferzeiten.additionalRequest.notificationTitle'),
                    message: this.$t('lieferzeiten.additionalRequest.notificationClosed', {
                        initiator: currentTask.initiator || this.$t('lieferzeiten.additionalRequest.defaultInitiator'),
                    }),
                });
            });

            this.additionalRequestTaskStatusByPosition = currentStatusByPosition;
        },

        resolveAdditionalRequestInitiator() {
            const contextUser = Shopware?.Context?.api?.user || null;
            const sessionUser = Shopware?.Store?.get?.('session')?.currentUser || Shopware?.State?.get?.('session')?.currentUser || null;
            const user = contextUser || sessionUser;

            const userId = typeof user?.id === 'string' ? user.id.trim() : '';
            const fullName = [user?.firstName, user?.lastName]
                .filter((part) => typeof part === 'string' && part.trim() !== '')
                .join(' ')
                .trim();
            const readableName = fullName || String(user?.username || user?.email || '').trim();

            if (!userId && !readableName) {
                return null;
            }

            return {
                userId: userId || null,
                display: readableName || this.$t('lieferzeiten.additionalRequest.defaultInitiator'),
            };
        },

        async reloadOrder(order) {
            if (typeof this.onReloadOrder === 'function') {
                await this.onReloadOrder(order);
                return;
            }

        },
    },
});
