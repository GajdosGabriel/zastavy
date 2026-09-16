import axios from "axios";
import { installRequestActivity } from './models/httpActivity';
import loadingStore from "./store/StoreLoading";
import { URL_BASE_API } from "./constants";

// Získanie tokenu z localStorage (alebo iného úložiska)
const token = localStorage.getItem('authToken');

const axiosInstance = axios.create({
    baseURL: URL_BASE_API, // 👈 Backend API URL
    headers: {
        'Content-Type': 'application/json',
    },
});

// Ak existuje token, pridá ho do hlavičky
if (token) {
    axiosInstance.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

// Middleware na automatické pridanie tokenu do každého requestu
axiosInstance.interceptors.request.use((config) => {
    const storedToken = localStorage.getItem('authToken');
    if (storedToken) {
        config.headers.Authorization = `Bearer ${storedToken}`;
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
