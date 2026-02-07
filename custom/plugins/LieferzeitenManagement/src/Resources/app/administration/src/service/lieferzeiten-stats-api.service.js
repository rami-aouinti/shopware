const { Application } = Shopware;

class LieferzeitenStatsApiService extends Shopware.Classes.ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'lieferzeiten-management') {
        super(httpClient, loginService, apiEndpoint);
        this.basicConfig = {
            timeout: 30000,
            version: Shopware.Context.api.apiVersion,
        };
    }

    getStats() {
        return this.httpClient
            .get(`_action/${this.getApiBasePath()}/stats`, {
                ...this.basicConfig,
                headers: this.getBasicHeaders(),
            })
            .then((response) => Shopware.Classes.ApiService.handleResponse(response));
    }
}

Application.addServiceProvider('lieferzeitenStatsApiService', (container) => {
    return new LieferzeitenStatsApiService(
        container.httpClient,
        container.loginService,
    );
});
