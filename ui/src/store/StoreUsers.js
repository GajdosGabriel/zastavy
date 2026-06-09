import { computed, reactive, readonly } from "vue";
import axiosInstance from "../axiosInstance";
import useErrors from "./StoreErrors";
import useNavigation from "./StoreNavigation";
import router from "../router";

const { setErrors } = useErrors();
const { setMainNavigation, resetNavigation } = useNavigation();

const defaultState = {
    user: {
        isAuth: false,
        order: {}
    },
    userOrder: {},
    filterCounter: {},
    token: localStorage.getItem('authToken')
};

const state = reactive(defaultState);

const getters = {
    getUser: computed(() => state.user),
    getUserOrder: computed(() => state.user.order),
    getFilterCounter: computed(() => state.filterCounter),
    getToken: computed(() => state.token),
};

const actions = {
    fetchUser: async () => {
        const token = localStorage.getItem('authToken');

        if (!token) {
            delete axiosInstance.defaults.headers.common['Authorization'];
        }

        try {
            const response = await axiosInstance.get("/user");

            state.user = response.data.data;
            state.userOrder = response.data.data;
            state.token = token;
            setMainNavigation(response.data.data?.navigation?.main);

            actions.updateUserIsAuth(Boolean(response.data.data?.isAuth));
        } catch (error) {
            if (error.response?.status === 401) {
                actions.clearAuth();
                return;
            }

            throw error;
        }
    },

    logout: async () => {
        try {
            await axiosInstance.post('/logout');
            actions.clearAuth();
            console.log('Odhlasenie uspesne');
        } catch (error) {
            if (error.response?.status === 401) {
                actions.clearAuth();
                return;
            }

            console.error('Chyba pri odhlaseni:', error);
        }
    },

    login: async (form) => {
        try {
            const response = await axiosInstance.post('/login', form);

            const token = response.data.token;
            localStorage.setItem('authToken', token);
            state.token = token;
            axiosInstance.defaults.headers.common['Authorization'] = `Bearer ${token}`;

            console.log('Prihlasenie uspesne');

            await actions.fetchUser();

            return true;
        } catch (error) {
            setErrors(error);
            console.error('Chyba pri prihlasovani:', error);

            return false;
        }
    },

    register: async (form) => {
        try {
            const response = await axiosInstance.post("/register", form);
            const token = response.data.token ?? response.data;

            localStorage.setItem('authToken', token);
            state.token = token;
            axiosInstance.defaults.headers.common['Authorization'] = `Bearer ${token}`;

            actions.fetchUser();
            router.push({ name: "public.index" });
        } catch (error) {
            setErrors(error);
            console.error("Chyba pri registracii:", error);
        }
    },

    updateUserIsAuth: (isLoggedIn) => {
        state.user.isAuth = isLoggedIn;
    },

    clearAuth: () => {
        localStorage.removeItem('authToken');
        localStorage.removeItem('token');
        delete axiosInstance.defaults.headers.common['Authorization'];
        state.token = null;
        state.user = {
            isAuth: false,
            order: {}
        };
        state.userOrder = {};
        resetNavigation();
    },

    resetModelUrl: (url) => {
        console.log(url);
    },

    forgotPassword: async (email) => {
        try {
            const response = await axiosInstance.post('/forgot-password', { email });
            return { success: true, message: response.data.message };
        } catch (error) {
            setErrors(error);
            return { success: false };
        }
    },

    resetPassword: async (form) => {
        try {
            const response = await axiosInstance.post('/reset-password', form);
            return { success: true, message: response.data.message };
        } catch (error) {
            setErrors(error);
            return { success: false };
        }
    },
};

export default () => ({
    state: readonly(state),
    ...getters,
    ...actions,
});
