import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import usePaginator from "./StorePaginator";
import useQuery from "./StoreQuery";
import { PAGE_USER } from "../constants";
import useErrors from "./StoreErrors";
import useUsers from "./StoreUsers";

interface AdminUser {
    id?: number;
    status?: any;
    roles?: string[];
    permissions?: string[];
    customer_id?: number | null;
    [key: string]: any;
}

interface AdminUsersState {
    url: string;
    users: AdminUser[];
    user: AdminUser;
    roles: any[];
    statuses: any[];
    locales: any[];
    portalPermissions: any[];
    customers: any[];
}

export const useAdminUsers = defineStore('adminUsers', {
    state: (): AdminUsersState => ({
        url: PAGE_USER.URL,
        users: [],
        user: {},
        roles: [],
        statuses: [],
        locales: [],
        portalPermissions: [],
        customers: [],
    }),

    getters: {
        getUsers: (s): AdminUser[] => s.users,
        getUser: (s): AdminUser => s.user,
        getRoles: (s): any[] => s.roles,
        getStatuses: (s): any[] => s.statuses,
        getLocales: (s): any[] => s.locales,
        getPortalPermissions: (s): any[] => s.portalPermissions,
        getCustomers: (s): any[] => s.customers,
        canManageRoles(): boolean {
            return !!useUsers().getUser.value?.roles?.some((role: string) => ["super-admin"].includes(role));
        },
        canManagePermissions(): boolean {
            return !!useUsers().getUser.value?.roles?.some((role: string) => ["admin", "super-admin"].includes(role));
        },
        isPortalUser(): boolean {
            return !!this.user?.customer_id;
        },
    },

    actions: {
        async fetchUsers(): Promise<void> {
            try {
                const paginator = usePaginator();
                const response = await axiosInstance.get(this.url + useQuery().getQueryStringUrl);

                this.users = response.data.data;
                this.statuses = response.data.meta?.statuses || this.statuses;
                paginator.setPaginator(response.data.meta);
                paginator.setLinks(response.data.links);
            } catch (error) {
                this.users = [];
                useErrors().setErrors(error);
            }
        },

        async fetchUser(id: number | string): Promise<void> {
            try {
                const response = await axiosInstance.get(`${PAGE_USER.URL}/${id}`);

                this.user = response.data.data;
                this.roles = response.data.meta?.roles || [];
                this.statuses = response.data.meta?.statuses || [];
                this.locales = response.data.meta?.locales || [];
                this.portalPermissions = response.data.meta?.portal_permissions || [];
            } catch (error) {
                this.user = {};
                useErrors().setErrors(error);
            }
        },

        async updateUser(): Promise<boolean> {
            try {
                const payload: AdminUser = {
                    ...this.user,
                    status: this.user.status?.value || this.user.status,
                    active: this.user.active !== false,
                };

                if (this.canManageRoles) {
                    payload.roles = this.user.roles || [];
                } else {
                    delete payload.roles;
                }

                if (this.canManagePermissions && this.isPortalUser) {
                    payload.permissions = this.user.permissions || [];
                } else {
                    delete payload.permissions;
                }

                const response = await axiosInstance.put(`${PAGE_USER.URL}/${this.user.id}`, payload);

                this.user = response.data.data;
                this.roles = response.data.meta?.roles || this.roles;
                this.statuses = response.data.meta?.statuses || this.statuses;
                this.locales = response.data.meta?.locales || this.locales;
                this.portalPermissions = response.data.meta?.portal_permissions || this.portalPermissions;
                await this.fetchUsers();

                return true;
            } catch (error) {
                useErrors().setErrors(error);

                return false;
            }
        },

        async fetchCreateOptions(): Promise<void> {
            try {
                const response = await axiosInstance.get(`${PAGE_USER.URL}/create`);
                this.roles = response.data.meta?.roles || [];
                this.statuses = response.data.meta?.statuses || [];
                this.locales = response.data.meta?.locales || [];
                this.portalPermissions = response.data.meta?.portal_permissions || [];
                this.customers = response.data.meta?.customers || [];
                this.user = {
                    roles: [],
                    permissions: [],
                    prefix: '',
                    postfix: '',
                    position: '',
                    note: '',
                    locale: this.locales[0]?.value || '',
                    active: true,
                    status: this.statuses.find((s: any) => s.value === 'active')?.value
                        || this.statuses[0]?.value
                        || 'active',
                };
            } catch (error) {
                useErrors().setErrors(error);
            }
        },

        async storeUser(): Promise<boolean> {
            try {
                const payload: AdminUser = {
                    ...this.user,
                    active: this.user.active !== false,
                };

                // Status a role pri vytváraní smie posielať len super-admin; status ostatným určí API.
                if (this.canManageRoles) {
                    payload.status = this.user.status?.value || this.user.status;
                    payload.roles = this.user.roles || [];
                } else {
                    delete payload.status;
                    delete payload.roles;
                }

                if (this.canManagePermissions) {
                    payload.permissions = this.user.permissions || [];
                } else {
                    delete payload.permissions;
                }

                const response = await axiosInstance.post(PAGE_USER.URL, payload);
                this.user = response.data.data;
                return true;
            } catch (error) {
                useErrors().setErrors(error);
                return false;
            }
        },

        // Vlastná akcia (nezamieňať s paginátorovým setPaginator voľaným vo fetchUsers).
        setPaginator(url: string): void {
            this.url = url;
            this.fetchUsers();
        },

        resetUrl(): void {
            this.url = PAGE_USER.URL;
        },

        resetUser(): void {
            this.user = {};
        },
    },
});

export default useAdminUsers;
