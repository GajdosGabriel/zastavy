import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import useErrors from "./StoreErrors";
import useNavigation from "./StoreNavigation";
import router from "../router";
import { markSession } from "../authSession";
import useCustomers from "./StoreCustomers";
import useCheckouts, { CUSTOMER_STORAGE_KEY } from "./StoreCheckouts";

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
        token: null,
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
            try {
                const response = await axiosInstance.get("/user");

                const data = response.data?.data ?? { isAuth: false, order: {} };

                this.user = data;
                this.userOrder = data;
                markSession(Boolean(data.isAuth));
                useNavigation().setMainNavigation(data.navigation?.main);

                this.updateUserIsAuth(Boolean(data.isAuth));
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

                // Token prišiel v httpOnly cookie, JS ho nevidí; držíme len príznak, že má zmysel sa pýtať na /user.
                markSession(true);

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
                await axiosInstance.post("/register", form);
                markSession(true);

                this.fetchUser();
                router.push({ name: "public.index" });
            } catch (error) {
                useErrors().setErrors(error);
                console.error("Chyba pri registracii:", error);
            }
        },

        updateUserIsAuth(isLoggedIn: boolean): void {
            if (!this.user) {
                this.user = { isAuth: isLoggedIn, order: {} };
                return;
            }

            this.user.isAuth = isLoggedIn;
        },

        clearAuth(): void {
            markSession(false);
            localStorage.removeItem('authToken'); // zvyšok zo starého ukladania tokenu
            localStorage.removeItem('token');
            delete axiosInstance.defaults.headers.common['Authorization'];
            this.token = null;
            this.user = {
                isAuth: false,
                order: {},
            };
            this.userOrder = {};
            useNavigation().resetNavigation();
            // Na zdieľanom počítači nesmú po odhlásení ostať osobné údaje predchádzajúceho používateľa.
            localStorage.removeItem(CUSTOMER_STORAGE_KEY);
            useCustomers().resetCustomer();
            useCheckouts().resetDelivery();
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
