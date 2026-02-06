import template from './external-orders-list.html.twig';
import './external-orders-list.scss';

const { Component, Mixin } = Shopware;

Component.register('external-orders-list', {
    template,

    inject: ['externalOrderService', 'systemConfigApiService'],

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
            channelSources: {},
            channels: [
                {
                    id: 'b2b',
                    label: 'First-medical-shop.de',
                    logo: 'http://controlling.first-medical.de:8480/assets/images/b2b1.png',
                },
                {
                    id: 'ebay_de',
                    label: 'Ebay.DE',
                    logo: 'http://controlling.first-medical.de:8480/assets/images/ebay1.png',
                },
                {
                    id: 'kaufland',
                    label: 'KaufLand',
                    logo: 'http://controlling.first-medical.de:8480/assets/images/kaufland1.png',
                },
                {
                    id: 'ebay_at',
                    label: 'Ebay.AT',
                    logo: 'http://controlling.first-medical.de:8480/assets/images/ebayAT.png',
                },
                {
                    id: 'zonami',
                    label: 'Zonami',
                    logo: 'http://controlling.first-medical.de:8480/assets/images/zonami1.png',
                },
                {
                    id: 'peg',
                    label: 'PEG',
                    logo: 'http://controlling.first-medical.de:8480/assets/images/peg2.png',
                },
                {
                    id: 'bezb',
                    label: 'BEZB',
                    logo: 'http://controlling.first-medical.de:8480/assets/images/bezb1.png',
                },
            ],
            activeChannel: 'b2b',
            tableSearchTerm: '',
            columnFilterMatchMode: 'all',
            columnFilterMatchOptions: [
                { value: 'all', label: 'Match All' },
            ],
            columnFilterOperatorOptions: [
                { value: 'startsWith', label: 'Starts with' },
                { value: 'contains', label: 'Contains' },
                { value: 'equals', label: 'Equals' },
            ],
            columnFilters: {
                orderNumber: { value: '', operator: 'startsWith' },
                customerName: { value: '', operator: 'startsWith' },
                orderReference: { value: '', operator: 'startsWith' },
                email: { value: '', operator: 'startsWith' },
                date: { value: '', operator: 'startsWith' },
                statusLabel: { value: '', operator: 'startsWith' },
            },
            activeColumnFilter: null,
            selectedOrder: null,
            selectedOrders: [],
            showDetailModal: false,
            showTestMarkingModal: false,
            page: 1,
            limit: 10,
            limitOptions: [10, 25, 50, 100],
            sortBy: 'date',
            sortDirection: 'DESC',
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'order-number',
                    dataIndex: 'orderNumber',
                    sortBy: 'orderNumber',
                    label: 'BestellNr',
                    sortable: true,
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'customer-name',
                    dataIndex: 'customerName',
                    sortBy: 'customerName',
                    label: 'Kundenname',
                    sortable: true,
                    allowResize: true,
                },
                {
                    property: 'order-reference',
                    dataIndex: 'orderReference',
                    sortBy: 'orderReference',
                    label: 'AuftragsNr',
                    sortable: true,
                    allowResize: true,
                },
                {
                    property: 'email',
                    dataIndex: 'email',
                    sortBy: 'email',
                    label: 'Email',
                    sortable: true,
                    allowResize: true,
                },
                {
                    property: 'date',
                    dataIndex: 'date',
                    sortBy: 'date',
                    label: 'Datum',
                    sortable: true,
                    allowResize: true,
                },
                {
                    property: 'status-label',
                    dataIndex: 'statusLabel',
                    sortBy: 'statusLabel',
                    label: 'Bestellstatus',
                    sortable: true,
                    allowResize: true,
                },
                { property: 'actions', label: 'Ansicht', sortable: false, width: '90px' },
            ];
        },
        activeChannelLabel() {
            return this.channels.find((channel) => channel.id === this.activeChannel)?.label || '';
        },
        activeChannelSource() {
            return this.channelSources[this.activeChannel] || '';
        },
        filteredOrders() {
            const searchTerm = this.tableSearchTerm.trim().toLowerCase();
            const channelOrders = this.orders.filter((order) => {
                if (!order.channel) {
                    return true;
                }
                return order.channel === this.activeChannel;
            });

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

            const columnFiltered = this.applyColumnFilters(filtered);

            if (!searchTerm) {
                return columnFiltered;
            }

            return columnFiltered.filter((order) => {
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
        totalPages() {
            if (this.limit <= 0) {
                return 1;
            }
            return Math.max(1, Math.ceil(this.paginationTotal / this.limit));
        },
        visiblePages() {
            const maxVisible = 5;
            const total = this.totalPages;
            const current = this.page;

            if (total <= maxVisible) {
                return Array.from({ length: total }, (_, index) => index + 1);
            }

            const half = Math.floor(maxVisible / 2);
            let start = Math.max(1, current - half);
            let end = start + maxVisible - 1;

            if (end > total) {
                end = total;
                start = end - maxVisible + 1;
            }

            return Array.from({ length: end - start + 1 }, (_, index) => start + index);
        },
        limitSelectOptions() {
            return this.limitOptions.map((value) => ({
                value,
                label: String(value),
            }));
        },
        isAllSelected() {
            if (this.paginatedOrders.length === 0) {
                return false;
            }
            return this.paginatedOrders.every((order) => this.isOrderSelected(order));
        },
        hasSelectedOrders() {
            return this.selectedOrders.length > 0;
        },
        testMarkingOrders() {
            return this.selectedOrders;
        },
        unmarkedTestOrders() {
            const selectedKeys = new Set(this.selectedOrders.map((order) => this.getOrderKey(order)));
            return this.filteredOrders.filter((order) => !selectedKeys.has(this.getOrderKey(order)));
        },
    },

    watch: {
        tableSearchTerm() {
            this.page = 1;
        },
        activeChannel() {
            this.page = 1;
        },
        columnFilterMatchMode() {
            this.page = 1;
        },
        columnFilters: {
            handler() {
                this.page = 1;
            },
            deep: true,
        },
    },

    created() {
        this.initializePage();
    },

    methods: {
        async initializePage() {
            await this.loadOverviewConfiguration();
            await this.loadOrders();
        },
        async loadOverviewConfiguration() {
            try {
                const config = await this.systemConfigApiService.getValues('ExternalOrders');
                const getConfigValue = (key) => config?.[`ExternalOrders.config.${key}`] ?? '';

                this.channelSources = {
                    b2b: getConfigValue('sourceB2b'),
                    ebay_de: getConfigValue('sourceEbayDe'),
                    kaufland: getConfigValue('sourceKaufland'),
                    ebay_at: getConfigValue('sourceEbayAt'),
                    zonami: getConfigValue('sourceZonami'),
                    peg: getConfigValue('sourcePeg'),
                    bezb: getConfigValue('sourceBezb'),
                };
            } catch (error) {
                this.channelSources = {};
                this.createNotificationWarning({
                    title: 'Konfiguration konnte nicht geladen werden',
                    message: error?.message || 'Die Quellen der Übersichten konnten nicht geladen werden.',
                });
            }
        },
        async loadOrders() {
            this.isLoading = true;
            this.page = 1;

            try {
                const selectedChannel = this.activeChannel || null;
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
        onPageChange(payload) {
            if (typeof payload === 'number') {
                this.page = payload;
                return;
            }

            const { page, limit } = payload ?? {};
            const nextPage = typeof page === 'number' ? page : this.page;
            const nextLimit = typeof limit === 'number' ? limit : this.limit;

            this.page = nextPage;
            this.limit = nextLimit;
        },
        goToPreviousPage() {
            if (this.page <= 1) {
                return;
            }
            this.page -= 1;
        },
        goToFirstPage() {
            this.page = 1;
        },
        goToLastPage() {
            this.page = this.totalPages;
        },
        goToPage(pageNumber) {
            if (typeof pageNumber !== 'number') {
                return;
            }
            const nextPage = Math.max(1, Math.min(this.totalPages, pageNumber));
            this.page = nextPage;
        },
        goToNextPage() {
            if (this.page >= this.totalPages) {
                return;
            }
            this.page += 1;
        },
        onLimitChange(value) {
            const nextLimit = Number(value);
            if (Number.isNaN(nextLimit) || nextLimit <= 0) {
                return;
            }
            this.limit = nextLimit;
            this.page = 1;
        },
        onSearch() {
            this.page = 1;
        },
        resetFilters() {
            this.tableSearchTerm = '';
            this.columnFilterMatchMode = 'all';
            Object.keys(this.columnFilters).forEach((key) => {
                this.columnFilters[key].value = '';
                this.columnFilters[key].operator = 'startsWith';
            });
            this.page = 1;
        },
        toggleColumnFilter(columnKey) {
            if (this.activeColumnFilter === columnKey) {
                this.activeColumnFilter = null;
                return;
            }
            this.activeColumnFilter = columnKey;
        },
        applyColumnFilter() {
            this.page = 1;
            this.activeColumnFilter = null;
        },
        clearColumnFilter(columnKey) {
            if (!this.columnFilters[columnKey]) {
                return;
            }
            this.columnFilters[columnKey].value = '';
            this.columnFilters[columnKey].operator = 'startsWith';
            this.activeColumnFilter = null;
        },
        isColumnFilterActive(columnKey) {
            const filter = this.columnFilters[columnKey];
            return Boolean(filter && String(filter.value ?? '').trim());
        },
        applyColumnFilters(orders) {
            const activeFilters = Object.entries(this.columnFilters)
                .filter(([, filter]) => String(filter.value ?? '').trim().length > 0);

            if (!activeFilters.length) {
                return orders;
            }

            return orders.filter((order) => activeFilters.every(([columnKey, filter]) => {
                const value = this.getColumnFilterValue(order, columnKey);
                return this.matchesColumnFilter(value, filter);
            }));
        },
        getColumnFilterValue(order, columnKey) {
            return order?.[columnKey] ?? '';
        },
        matchesColumnFilter(value, filter) {
            const candidate = String(value ?? '').toLowerCase();
            const needle = String(filter.value ?? '').toLowerCase();
            const operator = filter.operator ?? 'startsWith';

            if (!needle) {
                return true;
            }

            if (operator === 'equals') {
                return candidate === needle;
            }

            if (operator === 'contains') {
                return candidate.includes(needle);
            }

            return candidate.startsWith(needle);
        },
        normalizeSortValue(value, sortBy = this.sortBy) {
            if (value === null || value === undefined) {
                return '';
            }
            if (String(sortBy).toLowerCase().includes('date')) {
                const parsedDate = this.parseOrderDate(value);
                return Number.isNaN(parsedDate) ? String(value) : parsedDate;
            }
            if (typeof value === 'number') {
                return value;
            }
            const normalized = String(value).trim();
            if (/^-?\d+(\.\d+)?$/.test(normalized)) {
                return Number(normalized);
            }
            return normalized.toLowerCase();
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
        onSelectionChange(selection) {
            if (Array.isArray(selection)) {
                this.selectedOrders = selection;
                return;
            }

            if (selection && typeof selection === 'object') {
                this.selectedOrders = Object.values(selection);
                return;
            }

            this.selectedOrders = [];
        },
        toggleSelectAll(event) {
            const isChecked = event?.target?.checked;
            if (!isChecked) {
                const currentKeys = new Set(this.paginatedOrders.map((order) => this.getOrderKey(order)));
                this.selectedOrders = this.selectedOrders.filter((order) => !currentKeys.has(this.getOrderKey(order)));
                return;
            }

            const merged = new Map(this.selectedOrders.map((order) => [this.getOrderKey(order), order]));
            this.paginatedOrders.forEach((order) => {
                merged.set(this.getOrderKey(order), order);
            });
            this.selectedOrders = Array.from(merged.values());
        },
        toggleOrderSelection(order, event) {
            const isChecked = event?.target?.checked;
            const key = this.getOrderKey(order);
            if (!key) {
                return;
            }

            if (isChecked) {
                const merged = new Map(this.selectedOrders.map((item) => [this.getOrderKey(item), item]));
                merged.set(key, order);
                this.selectedOrders = Array.from(merged.values());
                return;
            }

            this.selectedOrders = this.selectedOrders.filter((item) => this.getOrderKey(item) !== key);
        },
        isOrderSelected(order) {
            const key = this.getOrderKey(order);
            return this.selectedOrders.some((item) => this.getOrderKey(item) === key);
        },
        getSortIndicator(sortBy) {
            if (this.sortBy !== sortBy) {
                return '↕';
            }
            return this.sortDirection === 'ASC' ? '↑' : '↓';
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
        openTestMarkingModal() {
            if (!this.hasSelectedOrders) {
                return;
            }
            this.showTestMarkingModal = true;
        },
        closeTestMarkingModal() {
            this.showTestMarkingModal = false;
        },
        applyTestMarking() {
            this.showTestMarkingModal = false;
        },
        getOrderKey(order) {
            return order?.id ?? order?.orderNumber ?? '';
        },
        getOrderDisplayLabel(order) {
            const number = order?.orderNumber ? `#${order.orderNumber}` : '';
            const customer = order?.customerName ?? '';
            if (number && customer) {
                return `Order ${number} - ${customer}`;
            }
            if (number) {
                return `Order ${number}`;
            }
            return customer || 'Order';
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
        exportSelectedOrdersPdf() {
            const orders = this.getOrdersForExport();
            if (!orders.length) {
                this.createNotificationWarning({
                    title: 'Keine Bestellungen gefunden',
                    message: 'Für den aktuellen Filter sind keine Bestellungen verfügbar.',
                });
                return;
            }

            const pdfData = this.buildTablePdf(orders);
            this.downloadBlob('external-orders.pdf', 'application/pdf', pdfData);
        },
        exportSelectedOrdersExcel() {
            const orders = this.getOrdersForExport();
            if (!orders.length) {
                this.createNotificationWarning({
                    title: 'Keine Bestellungen gefunden',
                    message: 'Für den aktuellen Filter sind keine Bestellungen verfügbar.',
                });
                return;
            }

            const rows = this.buildExportRows(orders);
            const csv = this.buildCsv(rows);
            this.downloadBlob('external-orders.csv', 'text/csv;charset=utf-8;', csv);
        },
        getOrdersForExport() {
            return this.filteredOrders;
        },
        buildExportRows(orders) {
            const header = [
                'Bestellnummer',
                'Kundenname',
                'Land',
                'Zahlungsmethode',
                'Datum',
                'Bestellstatus',
                'Stück',
                'Summe',
            ];

            const rows = orders.map((order) => ([
                order.orderNumber ?? '',
                order.customerName ?? '',
                this.getOrderCountry(order),
                this.getOrderPaymentMethod(order),
                this.formatDateForExport(order.date),
                order.statusLabel ?? '',
                this.getOrderItemCount(order),
                this.formatNumberForExport(this.getOrderTotal(order)),
            ]));

            return [header, ...rows];
        },
        buildExportLines(orders) {
            const rows = this.buildExportRows(orders);
            const formattedRows = rows.map((row) => row.map((value) => String(value ?? '')));
            const columnWidths = formattedRows[0].map((_, index) => {
                const longest = Math.max(...formattedRows.map((row) => row[index].length));
                return Math.min(Math.max(longest, 6), 24);
            });

            const paddedRows = formattedRows.map((row) => row.map((value, index) => {
                const trimmed = value.length > columnWidths[index]
                    ? `${value.slice(0, columnWidths[index] - 1)}…`
                    : value;
                return trimmed.padEnd(columnWidths[index]);
            }));

            const headerLine = paddedRows[0].join(' | ');
            const separatorLine = columnWidths.map((width) => '-'.repeat(width)).join('-|-');
            const bodyLines = paddedRows.slice(1).map((row) => row.join(' | '));

            return [
                this.buildExportSummaryLine(orders),
                headerLine,
                separatorLine,
                ...bodyLines,
            ];
        },
        buildCsv(rows) {
            const delimiter = ';';
            const escapeValue = (value) => {
                const stringValue = String(value ?? '');
                const escapedValue = stringValue.replace(/"/g, '""');
                const needsEscaping = stringValue.includes(delimiter)
                    || stringValue.includes('\n')
                    || stringValue.includes('\r')
                    || stringValue.includes('"');
                return needsEscaping ? `"${escapedValue}"` : escapedValue;
            };

            const content = rows
                .map((row) => row.map(escapeValue).join(delimiter))
                .join('\r\n');

            return `\ufeff${content}`;
        },
        buildExportSummaryLine(orders) {
            const totalItems = orders.reduce((sum, order) => sum + this.getOrderItemCount(order), 0);
            const totalRevenue = orders.reduce((sum, order) => sum + this.getOrderTotal(order), 0);
            const dateRange = this.getExportDateRange(orders);
            const revenueLabel = this.formatCurrency(totalRevenue);

            return [
                'Shopbestellungen',
                dateRange ? `${dateRange}` : null,
                `${orders.length} Bestellungen`,
                `${revenueLabel} Gesamtumsatz`,
                `${totalItems} Stück`,
            ].filter(Boolean).join(' | ');
        },
        getExportDateRange(orders) {
            const timestamps = orders
                .map((order) => this.parseOrderDate(order.date))
                .filter((value) => Number.isFinite(value));

            if (!timestamps.length) {
                return '';
            }

            const min = new Date(Math.min(...timestamps));
            const max = new Date(Math.max(...timestamps));

            return `${this.formatDateForExport(min)} - ${this.formatDateForExport(max)}`;
        },
        parseOrderDate(value) {
            if (!value) {
                return NaN;
            }
            const normalized = String(value).replace(' ', 'T');
            const parsed = Date.parse(normalized);
            return Number.isNaN(parsed) ? NaN : parsed;
        },
        formatDateForExport(value) {
            if (!value) {
                return '';
            }
            if (value instanceof Date) {
                return value.toLocaleDateString('de-DE');
            }
            const parsed = this.parseOrderDate(value);
            if (Number.isNaN(parsed)) {
                return String(value);
            }
            return new Date(parsed).toISOString().slice(0, 10);
        },
        formatNumberForExport(value) {
            const numeric = typeof value === 'number' ? value : Number(value);
            if (!Number.isFinite(numeric)) {
                return '';
            }
            return new Intl.NumberFormat('de-DE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(numeric);
        },
        getOrderCountry(order) {
            return order?.billingAddress?.country
                ?? order?.shippingAddress?.country
                ?? order?.country
                ?? '';
        },
        getOrderPaymentMethod(order) {
            return order?.payment?.method
                ?? order?.paymentMethod
                ?? order?.payment
                ?? '';
        },
        getOrderItemCount(order) {
            return order?.totalItems ?? order?.itemsCount ?? order?.items?.length ?? 0;
        },
        getOrderTotal(order) {
            return order?.totalPrice
                ?? order?.totalRevenue
                ?? order?.amountTotal
                ?? order?.priceTotal
                ?? 0;
        },
        buildTablePdf(orders) {
            const rows = this.buildExportRows(orders);
            const summaryLine = this.buildExportSummaryLine(orders);
            const pageWidth = 595;
            const pageHeight = 842;
            const marginX = 30;
            const marginTop = 40;
            const startY = pageHeight - marginTop;
            const availableWidth = pageWidth - marginX * 2;
            const { columnWidths, scale } = this.getPdfColumnWidths(rows, availableWidth);
            const fontSize = Math.max(8, Math.floor(10 * scale));
            const lineHeight = Math.max(14, Math.round(fontSize * 1.8));
            const tableTop = startY - (lineHeight * 2);
            const totalTableWidth = columnWidths.reduce((sum, width) => sum + width, 0);
            const xPositions = columnWidths.reduce((positions, width) => {
                const last = positions[positions.length - 1];
                positions.push(last + width);
                return positions;
            }, [marginX]);

            const tableHeight = rows.length * lineHeight;
            const tableBottom = tableTop - tableHeight;

            const contentParts = [];
            contentParts.push('BT');
            contentParts.push(`/F1 ${fontSize} Tf`);
            contentParts.push(`1 0 0 1 ${marginX} ${startY} Tm`);
            contentParts.push(`(${this.escapePdfText(summaryLine)}) Tj`);
            contentParts.push('ET');

            contentParts.push('0.5 w');
            xPositions.forEach((x) => {
                contentParts.push(`${x} ${tableTop} m ${x} ${tableBottom} l S`);
            });
            for (let rowIndex = 0; rowIndex <= rows.length; rowIndex += 1) {
                const y = tableTop - (rowIndex * lineHeight);
                contentParts.push(`${marginX} ${y} m ${marginX + totalTableWidth} ${y} l S`);
            }

            const maxCharsForWidth = (width) => Math.floor((width - 6) / (fontSize * 0.55));
            rows.forEach((row, rowIndex) => {
                const textY = tableTop - ((rowIndex + 1) * lineHeight) + Math.round(fontSize * 0.6);
                row.forEach((value, columnIndex) => {
                    const cellX = xPositions[columnIndex] + 3;
                    const width = columnWidths[columnIndex];
                    const rawText = String(value ?? '');
                    const trimmed = this.trimPdfText(rawText, maxCharsForWidth(width));
                    contentParts.push('BT');
                    contentParts.push(`/F1 ${fontSize} Tf`);
                    contentParts.push(`1 0 0 1 ${cellX} ${textY} Tm`);
                    contentParts.push(`(${this.escapePdfText(trimmed)}) Tj`);
                    contentParts.push('ET');
                });
            });

            const stream = contentParts.join('\n');
            const objects = [];
            objects.push('1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');
            objects.push('2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n');
            objects.push('3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 5 0 R /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n');
            objects.push('4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n');
            objects.push(`5 0 obj\n<< /Length ${stream.length} >>\nstream\n${stream}\nendstream\nendobj\n`);

            let pdf = '%PDF-1.4\n';
            const offsets = [];
            const encoder = new TextEncoder();

            objects.forEach((object) => {
                offsets.push(encoder.encode(pdf).length);
                pdf += object;
            });

            const xrefStart = encoder.encode(pdf).length;
            pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
            offsets.forEach((offset) => {
                pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
            });
            pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF`;

            return encoder.encode(pdf);
        },
        getPdfColumnWidths(rows, availableWidth) {
            const maxLengths = rows[0].map((_, index) => Math.max(...rows.map((row) => String(row[index] ?? '').length)));
            const minWidths = [60, 120, 55, 95, 65, 110, 45, 70];
            const baseWidths = maxLengths.map((length, index) => Math.max(minWidths[index], length * 6));
            const totalWidth = baseWidths.reduce((sum, width) => sum + width, 0);
            const scale = totalWidth > availableWidth ? (availableWidth / totalWidth) : 1;
            return {
                columnWidths: baseWidths.map((width) => Math.floor(width * scale)),
                scale,
            };
        },
        trimPdfText(text, maxChars) {
            if (maxChars <= 0) {
                return '';
            }
            if (text.length <= maxChars) {
                return text;
            }
            if (maxChars === 1) {
                return '…';
            }
            return `${text.slice(0, maxChars - 1)}…`;
        },
        escapePdfText(text) {
            return String(text)
                .replace(/\\/g, '\\\\')
                .replace(/\(/g, '\\(')
                .replace(/\)/g, '\\)');
        },
        downloadBlob(filename, mimeType, content) {
            const blob = content instanceof Uint8Array
                ? new Blob([content], { type: mimeType })
                : new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        },

        getSortValue(order, sortBy) {
            if (!sortBy) {
                return '';
            }

            const value = order?.[sortBy];
            return this.normalizeSortValue(value, sortBy);
        },

        buildFakeOrders() {
            const perChannelCount = 100;
            const baseOrderNumber = 1009000;
            const baseReference = 446500;
            const baseDate = new Date('2025-12-30T09:31:00');
            const names = [
                'Andreas Nanke',
                'Lea Wagner',
                'Frank Sagert',
                'Sophie Bauer',
                'Noah Berg',
                'Julia Krüger',
                'Tim König',
                'Maja Keller',
                'Lina Hoffmann',
                'Jonas Richter',
                'Karsten Stieler',
                'Peter Scholl',
                'Jasmin Roth',
                'Oliver Hahn',
                'Mara Schulz',
                'Tobias Link',
                'Lukas Meier',
                'Svenja Graf',
                'Nina Krämer',
                'David Winter',
            ];
            const statuses = [
                { status: 'processing', label: 'Bezahlt / in Bearbeitung' },
                { status: 'shipped', label: 'Versendet' },
                { status: 'closed', label: 'Abgeschlossen' },
            ];
            const channelConfig = {
                b2b: { emailDomain: 'first-medical-shop.de', label: 'First-medical-shop.de' },
                ebay_de: { emailDomain: 'members.ebay.com', label: 'Ebay.DE' },
                ebay_at: { emailDomain: 'members.ebay.com', label: 'Ebay.AT' },
                kaufland: { emailDomain: 'members.kaufland.de', label: 'KaufLand' },
                zonami: { emailDomain: 'zonami.example', label: 'Zonami' },
                peg: { emailDomain: 'peg.example', label: 'PEG' },
                bezb: { emailDomain: 'bezb.example', label: 'BEZB' },
            };

            const formatDate = (date) => {
                const pad = (value) => String(value).padStart(2, '0');
                return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
            };

            const orders = [];
            const channels = this.channels.map((channel) => channel.id);

            channels.forEach((channelId, channelIndex) => {
                const config = channelConfig[channelId] ?? { emailDomain: 'example.com', label: channelId };
                for (let i = 0; i < perChannelCount; i += 1) {
                    const globalIndex = channelIndex * perChannelCount + i;
                    const orderNumber = String(baseOrderNumber + globalIndex);
                    const orderReference = String(baseReference + globalIndex);
                    const status = statuses[globalIndex % statuses.length];
                    const name = names[globalIndex % names.length];
                    const emailPrefix = name.toLowerCase().replace(/[^a-z0-9]+/g, '.');
                    const date = new Date(baseDate.getTime() - (globalIndex * 36 * 60 * 1000));

                    orders.push({
                        id: `order-${channelId}-${orderNumber}`,
                        channel: channelId,
                        orderNumber,
                        customerName: name,
                        orderReference,
                        email: `${emailPrefix}@${config.emailDomain}`,
                        date: formatDate(date),
                        status: status.status,
                        statusLabel: status.label,
                        totalItems: (globalIndex % 6) + 1,
                    });
                }
            });

            const totalItems = orders.reduce((sum, order) => sum + order.totalItems, 0);
            const totalRevenue = orders.reduce((sum, order) => sum + (order.totalItems * 79.5), 0);

            return {
                orders,
                summary: {
                    orderCount: orders.length,
                    totalRevenue: Number(totalRevenue.toFixed(2)),
                    totalItems,
                },
            };
        },

    },
});
