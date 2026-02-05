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
                {
                    property: 'orderNumber',
                    dataIndex: 'orderNumber',
                    sortBy: 'orderNumber',
                    label: 'BestellNr',
                    sortable: true,
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'customerName',
                    dataIndex: 'customerName',
                    sortBy: 'customerName',
                    label: 'Kundenname',
                    sortable: true,
                    allowResize: true,
                },
                {
                    property: 'orderReference',
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
                    property: 'statusLabel',
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
        this.loadOrders();
    },

    methods: {
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
            const fontSize = 10;
            const lineHeight = 18;
            const pageWidth = 595;
            const pageHeight = 842;
            const marginX = 30;
            const marginTop = 40;
            const startY = pageHeight - marginTop;
            const tableTop = startY - (lineHeight * 2);

            const columnWidths = this.getPdfColumnWidths(rows, pageWidth - marginX * 2);
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

            const maxCharsForWidth = (width) => Math.floor((width - 8) / (fontSize * 0.6));
            rows.forEach((row, rowIndex) => {
                const textY = tableTop - ((rowIndex + 1) * lineHeight) + 6;
                row.forEach((value, columnIndex) => {
                    const cellX = xPositions[columnIndex] + 4;
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
            const minWidths = [60, 120, 55, 85, 65, 110, 40, 60];
            const baseWidths = maxLengths.map((length, index) => Math.max(minWidths[index], length * 6));
            const totalWidth = baseWidths.reduce((sum, width) => sum + width, 0);
            const scale = totalWidth > availableWidth ? (availableWidth / totalWidth) : 1;
            return baseWidths.map((width) => Math.floor(width * scale));
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
