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
            selectedOrders: [],
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
            selectedOrder: null,
            showDetailModal: false,
            page: 1,
            limit: 50,
            sortBy: 'date',
            sortDirection: 'DESC',
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

            if (!searchTerm) {
                return channelOrders;
            }

            return channelOrders.filter((order) => {
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
            const orders = [...this.filteredOrders];

            if (!this.sortBy) {
                return orders;
            }

            const direction = this.sortDirection === 'DESC' ? -1 : 1;

            return orders.sort((first, second) => {
                const firstValue = this.normalizeSortValue(first[this.sortBy]);
                const secondValue = this.normalizeSortValue(second[this.sortBy]);

                if (firstValue < secondValue) {
                    return -1 * direction;
                }
                if (firstValue > secondValue) {
                    return 1 * direction;
                }
                return 0;
            });
        },
        paginatedOrders() {
            const start = (this.page - 1) * this.limit;
            return this.sortedOrders.slice(start, start + this.limit);
        },
    },

    created() {
        this.loadOrders();
    },

    methods: {
        async loadOrders() {
            this.isLoading = true;
            this.page = 1;
            this.selectedOrders = [];

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
        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
        },
        onSort({ sortBy, sortDirection }) {
            this.sortBy = sortBy;
            this.sortDirection = sortDirection;
            this.page = 1;
        },
        onSearch() {
            this.page = 1;
        },
        onSelectionChange(selection) {
            const items = Array.isArray(selection)
                ? selection
                : selection?.selection ?? selection?.items ?? selection?.data ?? [];
            this.selectedOrders = Array.isArray(items) ? items : [];
        },
        normalizeSortValue(value) {
            if (value === null || value === undefined) {
                return '';
            }
            if (this.sortBy === 'date') {
                const parsedDate = new Date(String(value).replace(' ', 'T'));
                return Number.isNaN(parsedDate.getTime()) ? value : parsedDate.getTime();
            }
            if (typeof value === 'number') {
                return value;
            }
            return String(value).toLowerCase();
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
            if (!this.selectedOrders.length) {
                this.createNotificationWarning({
                    title: 'Keine Bestellungen ausgewählt',
                    message: 'Bitte wählen Sie mindestens eine Bestellung aus.',
                });
                return;
            }

            const lines = this.buildExportLines();
            const pdfData = this.buildSimplePdf(lines);
            this.downloadBlob('external-orders.pdf', 'application/pdf', pdfData);
        },
        exportSelectedOrdersExcel() {
            if (!this.selectedOrders.length) {
                this.createNotificationWarning({
                    title: 'Keine Bestellungen ausgewählt',
                    message: 'Bitte wählen Sie mindestens eine Bestellung aus.',
                });
                return;
            }

            const rows = this.buildExportRows();
            const csv = this.buildCsv(rows);
            this.downloadBlob('external-orders.csv', 'text/csv;charset=utf-8;', csv);
        },
        buildExportRows() {
            const header = [
                'BestellNr',
                'Kundenname',
                'AuftragsNr',
                'Email',
                'Datum',
                'Bestellstatus',
            ];

            const rows = this.selectedOrders.map((order) => ([
                order.orderNumber,
                order.customerName,
                order.orderReference,
                order.email,
                order.date,
                order.statusLabel,
            ]));

            return [header, ...rows];
        },
        buildExportLines() {
            const rows = this.buildExportRows();
            return rows.map((row) => row.map((value) => String(value ?? '')).join(' | '));
        },
        buildCsv(rows) {
            const escapeValue = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
            return rows.map((row) => row.map(escapeValue).join(',')).join('\n');
        },
        buildSimplePdf(lines) {
            const fontSize = 10;
            const lineHeight = 14;
            const startX = 50;
            const startY = 800 - lineHeight;

            const escapedLines = lines.map((line) => this.escapePdfText(line));
            const textLines = escapedLines.map((line, index) => {
                if (index === 0) {
                    return `(${line}) Tj`;
                }
                return `0 -${lineHeight} Td (${line}) Tj`;
            }).join('\n');

            const stream = [
                'BT',
                `/F1 ${fontSize} Tf`,
                `1 0 0 1 ${startX} ${startY} Tm`,
                textLines,
                'ET',
            ].join('\n');

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
