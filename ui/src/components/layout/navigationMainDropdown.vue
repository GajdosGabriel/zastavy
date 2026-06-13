<template>
    <div class="flex items-center">
        <div v-if="getUser?.isAuth" class="relative right" width="48">
            <button
                @click.stop="isShow"
                class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
            >
                <div>{{ getUser.firstName }}</div>

                <div class="ml-1">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
            </button>

            <Transition
                enter-active-class="duration-300 ease-out"
                enter-from-class="transform opacity-0 scale-75"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transform duration-200 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-75"
            >
                <div v-if="show" class="absolute right-0 mt-2 py-2 w-48 bg-white rounded-md shadow-xl z-20">
                    <template v-for="item in userMenu" :key="item.NAME">
                        <button
                            v-if="item.ACTION === 'logout'"
                            type="button"
                            @click="logoutUser"
                            class="block w-full text-left px-4 py-2 text-sm capitalize text-gray-700 hover:bg-blue-500 hover:text-white"
                        >
                            {{ item.NAME }}
                        </button>

                        <router-link
                            v-else
                            :to="{ name: item.ROUTE }"
                            @click="closeMenu"
                            class="block px-4 py-2 text-sm capitalize text-gray-700 hover:bg-blue-500 hover:text-white"
                        >
                            {{ item.NAME }}
                        </router-link>
                    </template>
                </div>
            </Transition>
        </div>

        <div class="-mr-2 flex items-center sm:hidden"></div>
    </div>
</template>

<script>
import { computed, ref } from "vue";
import router from "../../router";
import useUser from "../../store/StoreUsers";

export default {
    created() {
        window.addEventListener("click", this.closeOnOutsideClick);
        document.addEventListener("keyup", this.closeOnEscape);
    },

    beforeUnmount() {
        window.removeEventListener("click", this.closeOnOutsideClick);
        document.removeEventListener("keyup", this.closeOnEscape);
    },

    methods: {
        closeOnOutsideClick(e) {
            if (!this.$el.contains(e.target)) {
                this.show = false;
            }
        },

        closeOnEscape(evt) {
            if (evt.key === "Escape" || evt.keyCode === 27) {
                this.show = false;
            }
        },
    },

    setup() {
        const { logout, getUser } = useUser();
        const show = ref(false);
        const isShow = () => (show.value = !show.value);
        const closeMenu = () => (show.value = false);

        const userMenu = computed(() => getUser.value?.navigation?.userMenu || []);
        const guestMenuItem = computed(() => userMenu.value[0]);

        const logoutUser = async () => {
            await logout();
            router.push({ name: "public.index" });
            closeMenu();
        };

        return {
            show,
            isShow,
            closeMenu,
            logoutUser,
            getUser,
            userMenu,
            guestMenuItem,
        };
    },
};
</script>
