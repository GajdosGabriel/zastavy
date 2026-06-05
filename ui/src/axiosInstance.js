import axios from "axios";
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
    loadingStore.isLoading = true; // Nastavíme loading na true
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
    loadingStore.isLoading = false;
    return Promise.reject(error);
});

// Middleware na response – nastavíme isLoading na false
axiosInstance.interceptors.response.use(
    (response) => {
        loadingStore.isLoading = false;
        return response;
    },
    (error) => {
        loadingStore.isLoading = false;
        return Promise.reject(error);
    }
);

export default axiosInstance;
