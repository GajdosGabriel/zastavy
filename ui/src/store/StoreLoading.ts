import { reactive } from "vue";

interface LoadingState {
    isLoading: boolean;
}

// Prierezový globálny loading flag. Zámerne NIE Pinia store:
// mutuje sa priamo v axios interceptoroch (axiosInstance.js), teda mimo
// Vue/Pinia lifecycle. Ponechaný ako typovaný reactive singleton – identické
// API (`loadingStore.isLoading`) pre všetkých konzumentov aj interceptory.
const loadingStore = reactive<LoadingState>({
    isLoading: false,
});

export default loadingStore;
