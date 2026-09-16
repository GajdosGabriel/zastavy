import axios from 'axios';
import { reactive } from 'vue';

export const createRequestActivity = () => reactive({
    pendingRequests: 0,
    get isLoading() { return this.pendingRequests > 0; },
});

// Počítame transporty. Aj zrušenie a chyba prejdú tým istým finally;
// chyba pred spustením adaptéra nevytvorí falošný aktívny request.
export function installRequestActivity(client, activity) {
    client.interceptors.request.use((config) => {
        const adapter = axios.getAdapter(config.adapter);
        config.adapter = async (request) => {
            activity.pendingRequests++;
            try {
                return await adapter(request);
            } finally {
                activity.pendingRequests--;
            }
        };
        return config;
    });
}
