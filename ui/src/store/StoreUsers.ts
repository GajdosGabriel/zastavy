import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import useErrors from "./StoreErrors";
import useNavigation from "./StoreNavigation";
import router from "../router";

interface AuthUser {
    isAuth: boolean;
    order: Record<string, any>;
    roles?: string[];
    can?: Record<string, any>;
    navigation?: Record<string, any>;
    [key: string]: any;
}

interface UsersState {
    user: AuthUser;
    userOrder: Record<string, any>;
    filterCounter: Record<string, any>;
    token: string | null;
}

export const useUsers = defineStore('users', {
    state: (): UsersState => ({
        user: {
            isAuth: false,
            order: {},
        },
        userOrder: {},
        filterCounter: {},
        token: localStorage.getItem('authToken'),
    }),

    getters: {
        getUser: (s): AuthUser => s.user,
        getUserOrder: (s): Record<string, any> => s.user.order,
        getUserCan: (s): Record<string, any> => s.user.can ?? {},
        getFilterCounter: (s): Record<string, any> => s.filterCounter,
        getToken: (s): string | null => s.token,
    },

    actions: {
        async fetchUser(): Promise<void> {
            const token = localStorage.getItem('authToken');

            if (!token) {
                delete axiosInstance.defaults.headers.common['Authorization'];
            }

            try {
                const response = await axiosInstance.get("/user");

                this.user = response.data.data;
                this.userOrder = response.data.data;
                this.token = token;
                useNavigation().setMainNavigation(response.data.data?.navigation?.main);

                this.updateUserIsAuth(Boolean(response.data.data?.isAuth));
            } catch (error: any) {
                if (error.response?.status === 401) {
                    this.clearAuth();
                    return;
                }

                throw error;
            }
        },

        async logout(): Promise<void> {
            try {
                await axiosInstance.post('/logout');
                this.clearAuth();
                console.log('Odhlasenie uspesne');
            } catch (error: any) {
                if (error.response?.status === 401) {
                    this.clearAuth();
                    return;
                }

                console.error('Chyba pri odhlaseni:', error);
            }
        },

        async login(form: Record<string, any>): Promise<boolean> {
            try {
                const response = await axiosInstance.post('/login', form);

                const token = response.data.token;
                localStorage.setItem('authToken', token);
                this.token = token;
                axiosInstance.defaults.headers.common['Authorization'] = `Bearer ${token}`;

                console.log('Prihlasenie uspesne');

                await this.fetchUser();

                return true;
            } catch (error) {
                useErrors().setErrors(error);
                console.error('Chyba pri prihlasovani:', error);

                return false;
            }
        },

        async register(form: Record<string, any>): Promise<void> {
            try {
                const response = await axiosInstance.post("/register", form);
                const token = response.data.token ?? response.data;

                localStorage.setItem('authToken', token);
                this.token = token;
                axiosInstance.defaults.headers.common['Authorization'] = `Bearer ${token}`;

                this.fetchUser();
                router.push({ name: "public.index" });
            } catch (error) {
                useErrors().setErrors(error);
                console.error("Chyba pri registracii:", error);
            }
        },

        updateUserIsAuth(isLoggedIn: boolean): void {
            this.user.isAuth = isLoggedIn;
        },

        clearAuth(): void {
            localStorage.removeItem('authToken');
            localStorage.removeItem('token');
            delete axiosInstance.defaults.headers.common['Authorization'];
            this.token = null;
            this.user = {
                isAuth: false,
                order: {},
            };
            this.userOrder = {};
            useNavigation().resetNavigation();
        },

        resetModelUrl(url: string): void {
            console.log(url);
        },

        async forgotPassword(email: string): Promise<{ success: boolean; message?: string }> {
            try {
                const response = await axiosInstance.post('/forgot-password', { email });
                return { success: true, message: response.data.message };
            } catch (error) {
                useErrors().setErrors(error);
                return { success: false };
            }
        },

        async resetPassword(form: Record<string, any>): Promise<{ success: boolean; message?: string }> {
            try {
                const response = await axiosInstance.post('/reset-password', form);
                return { success: true, message: response.data.message };
            } catch (error) {
                useErrors().setErrors(error);
                return { success: false };
            }
        },
    },
});

export default useUsers;
