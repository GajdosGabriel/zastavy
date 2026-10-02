import axios from "axios";
import { installRequestActivity } from './models/httpActivity';
import loadingStore from "./store/StoreLoading";
import { URL_BASE_API } from "./constants";

const axiosInstance = axios.create({
    baseURL: URL_BASE_API, // 👈 Backend API URL
    withCredentials: true, // prihlasovací token je v httpOnly cookie
    headers: {
        'Content-Type': 'application/json',
    },
});

axiosInstance.interceptors.request.use((config) => {
    // Pri relatívnom URL_BASE_API (/api, dev proxy) už URL obsahuje prefix; baseURL by ho zdvojil.
    if (URL_BASE_API.startsWith('/') && config.url?.startsWith(`${URL_BASE_API}/`)) {
        config.baseURL = '';
    }

    if (config.data instanceof FormData) {
        delete config.headers['Content-Type'];
        delete config.headers['content-type'];

        if (typeof config.headers.delete === 'function') {
            config.headers.delete('Content-Type');
        }
    }

    return config;
}, (error) => {
    return Promise.reject(error);
});

installRequestActivity(axiosInstance, loadingStore);

export default axiosInstance;
