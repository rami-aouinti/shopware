const { ApiService } = Shopware.Classes;

class LieferzeitenOrdersService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'lieferzeiten');
        this.name = 'lieferzeitenOrdersService';
    }

    async getOrders() {
        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/orders`, {
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        return data?.data ?? data ?? [];
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

    async updateLieferterminLieferant(positionId, payload) {
        return this.post(`position/${positionId}/liefertermin-lieferant`, payload);
    }

    async updateNeuerLiefertermin(positionId, payload) {
        return this.post(`position/${positionId}/neuer-liefertermin`, payload);
    }

    async updateComment(positionId, payload) {
        return this.post(`position/${positionId}/comment`, payload);
    }

    async createAdditionalDeliveryRequest(positionId, initiator) {
        return this.post(`position/${positionId}/additional-delivery-request`, { initiator });
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
