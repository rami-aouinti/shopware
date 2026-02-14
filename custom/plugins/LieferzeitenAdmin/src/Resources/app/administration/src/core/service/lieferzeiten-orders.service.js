const { ApiService } = Shopware.Classes;

class LieferzeitenOrdersService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'lieferzeiten');
        this.name = 'lieferzeitenOrdersService';
    }

    async getOrders(params = {}) {
        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/orders`, {
            params,
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        const rows = data?.data ?? data ?? [];

        if (!Array.isArray(rows)) {
            return [];
        }

        return rows.map((row) => ({
            ...row,
            orderNumber: row.orderNumber || row.bestellnummer || null,
            san6OrderNumber: row.san6OrderNumber || row.san6 || null,
            san6Position: row.san6Position || row.san6Pos || null,
            quantity: row.quantity || null,
            paymentMethod: row.paymentMethod || null,
            paymentDate: row.paymentDate || null,
            trackingSummary: row.trackingSummary || row.sendenummer || null,
            shippingAssignmentType: row.shippingAssignmentType || row.shipping_assignment_type || null,
            sourceSystem: row.sourceSystem || row.domain || null,
            domain: row.domain || row.sourceSystem || null,
        }));
    }

    async getOrderDetails(paketId) {
        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/orders/${encodeURIComponent(paketId)}/details`, {
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data ?? {};

        return data?.data || data;
    }


    async getStatistics(params = {}) {
        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/statistics`, {
            params,
            headers: this.getBasicHeaders(),
        });

        return ApiService.handleResponse(response) ?? response?.data ?? {};
    }



    async getDemoDataStatus() {
        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/demo-data/status`, {
            headers: this.getBasicHeaders(),
        });

        return ApiService.handleResponse(response) ?? response?.data ?? {};
    }

    async toggleDemoData() {
        const response = await this.httpClient.post(`_action/${this.getApiBasePath()}/demo-data/toggle`, {}, {
            headers: this.getBasicHeaders(),
        });

        return ApiService.handleResponse(response) ?? response?.data ?? {};
    }

    async seedDemoData(reset = false) {
        const response = await this.httpClient.post(`_action/${this.getApiBasePath()}/demo-data`, { reset }, {
            headers: this.getBasicHeaders(),
        });

        return ApiService.handleResponse(response) ?? response?.data ?? {};
    }

    async getSalesChannelLieferzeiten(salesChannelId) {
        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/sales-channel/${encodeURIComponent(salesChannelId)}/lieferzeiten`, {
            headers: this.getBasicHeaders(),
        });

        return ApiService.handleResponse(response) ?? response?.data ?? {};
    }

    async updateLieferterminLieferant(positionId, payload) {
        return this.post(`position/${positionId}/liefertermin-lieferant`, payload);
    }

    async updateNeuerLiefertermin(positionId, payload) {
        return this.post(`position/${positionId}/neuer-liefertermin`, payload);
    }

    async updateNeuerLieferterminByPaket(paketId, payload) {
        return this.post(`paket/${paketId}/neuer-liefertermin`, payload);
    }

    async updateComment(positionId, payload) {
        return this.post(`position/${positionId}/comment`, payload);
    }

    async createAdditionalDeliveryRequest(positionId, initiator) {
        return this.post(`position/${positionId}/additional-delivery-request`, { initiator });
    }

    async updatePaketStatus(paketId, payload) {
        return this.post(`paket/${paketId}/status`, payload);
    }

    async post(path, payload) {
        const response = await this.httpClient.post(`_action/${this.getApiBasePath()}/${path}`, payload, {
            headers: this.getBasicHeaders(),
        });

        return ApiService.handleResponse(response) ?? response?.data;
    }
}

Shopware.Application.addServiceProvider('lieferzeitenOrdersService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    const httpClient = initContainer?.httpClient ?? container.httpClient;
    const loginService = container.loginService ?? Shopware.Service('loginService');

    return new LieferzeitenOrdersService(httpClient, loginService);
});
