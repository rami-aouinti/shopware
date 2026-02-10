const { ApiService } = Shopware.Classes;

class LieferzeitenOrdersService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'lieferzeiten');
        this.name = 'lieferzeitenOrdersService';
    }

    async getOrders() {
        const response = await this.httpClient.get('/api/_action/lieferzeiten/orders', {
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        return data?.data ?? data ?? [];
    }


    async getStatistics(params = {}) {
        const response = await this.httpClient.get('/api/_action/lieferzeiten/statistics', {
            params,
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
        const response = await this.httpClient.post(`/api/_action/lieferzeiten/${path}`, payload, {
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
