import { createRouter, createWebHistory } from 'vue-router';
import { APP_NAME } from '../constants';
import product from './product';
import order from './order';
import customer from './customer';
import stock from './stock';
import user from './user';
import announcement from './announcement';
import admin from './admin';
import useStoreErrors from '../store/StoreErrors';
import useStoreOrders from '../store/StoreOrders';
import useUser from '../store/StoreUsers';
import { applyRouteSeo, DEFAULT_TITLE, DEFAULT_DESCRIPTION } from '../models/seo';



const routes = [
    ...product,
    ...order,
    ...customer,
    ...stock,
    ...user,
    ...announcement,
    ...admin,
    {
        path: '/',
        name: 'public.index',
        components: {
            default: () => import('../components/Home.vue'),
            // navigation: () => import('../components/pages/navigationMain.vue')
        },
        meta: {
            title: DEFAULT_TITLE,
            description: DEFAULT_DESCRIPTION,
        }
    },
    {
        path: '/dashboard',
        name: 'dashboard.index',
        components: {
            default: () => import('../components/pages/dashboard.vue'),
        },
        meta: {
            title: 'Dashboard'
        }
    },
    {
        path: '/admin',
        name: 'admin.index',
        components: {
            default: () => import('../components/pages/dashboard.vue'),
        },
        meta: {
            title: 'Administrácia',
            superAdminOnly: true,
        }
    },
    {
        path: '/product/:productId/show/:productSlug',
        name: 'public.products.show',
        components: {
            default: () => import('../components/product/PublicProductShow.vue'),
        },
        // Titulok, popis aj structured data doplní komponent po načítaní produktu.
        meta: {
            title: APP_NAME,
        }
    },
    {
        path: '/obchodne-podmienky',
        name: 'public.obchodne.podmienky',
        components: {
            default: () => import('../components/pages/obchodnePodmienky.vue'),
        },
        meta: {
            title: 'Obchodné podmienky',
            description: 'Obchodné podmienky nákupu vlajok a zástav — objednávka, dodanie, platba, '
                + 'reklamácie a odstúpenie od zmluvy.',
        }
    },
    {
        path: '/ochrana-osobnych-udajov',
        name: 'public.ochranaOsobnychUdajov',
        components: {
            default: () => import('../components/pages/ochranaOsobnychUdajov.vue'),
        },
        meta: {
            title: 'Ochrana osobných údajov',
            description: 'Aké osobné údaje spracúvame pri objednávke, na aký účel a ako dlho, '
                + 'a aké práva máte podľa GDPR.',
        }
    },
    {
        path: '/kontakt',
        name: 'public.contactUs',
        components: {
            default: () => import('../components/pages/contactUs.vue'),
        },
        meta: {
            title: 'Kontakt',
            description: 'Kontakt na predajcu vlajok a zástav: obchod@zastavy-vlajky.sk, '
                + 'tel. 0905 320 616. Poradíme obciam, školám aj firmám s výberom.',
        }
    },

    {
        path: '/kosik',
        name: 'public.cart.index',
        components: {
            default: () => import('../components/checkout/CartIndex.vue'),
        },
        meta: {
            title: 'Nákupný košík'
        }
    },

    {
        path: '/objednavka-odoslana',
        name: 'public.thankYouForOrder.show',
        components: {
            default: () => import('../components/layout/thankYouForOrder.vue'),
        },
        meta: {
            title: 'Objednávka odoslaná'
        }
    },

    {
        path: '/objednavka/:uuid',
        name: 'public.order.show',
        components: {
            default: () => import('../components/pages/PublicOrderView.vue'),
        },
        meta: {
            title: 'Detail objednávky'
        }
    },

    {
        path: '/login',
        name: 'public.login.index',
        components: {
            default: () => import('../components/auth/loginIndex.vue'),
        },
        meta: {
            title: 'Prihlásenie',
            guestOnly: true,
        }
    },

    {
        path: '/register',
        name: 'public.register.index',
        components: {
            default: () => import('../components/auth/register.vue'),
        },
        meta: {
            title: 'Registrácia',
            guestOnly: true,
        }
    },

    {
        path: '/forgot-password',
        name: 'public.forgotPassword',
        components: {
            default: () => import('../components/auth/ForgotPassword.vue'),
        },
        meta: {
            title: 'Zabudnuté heslo',
            guestOnly: true,
        }
    },

    {
        path: '/reset-password',
        name: 'public.resetPassword',
        components: {
            default: () => import('../components/auth/ResetPassword.vue'),
        },
        meta: {
            title: 'Nastaviť nové heslo',
            guestOnly: true,
        }
    },


    {
        path: '/:pathMatch(.*)*', name: 'Stranka-sa-nenasla',
        component: () => import('../components/pages/notFound.vue'),
        meta: {
            title: 'Stránka sa nenašla'
        }
    }
]


const router = createRouter({
    history: createWebHistory(),
    linkActiveClass: "nav_link_active",
    routes,
    // linkActiveClass: 'active',
    scrollBehavior(to, from, savedPosition) {
        // always scroll to top
        return { top: 0, behavior: 'smooth' }
    }
})

router.beforeResolve(async (to, from, next) => {
    // Get the page title from the route meta data that we have defined
    // See further down below for how we setup this data

    useStoreErrors().resetErrors();
    useStoreOrders().resetOrder();

    // Pinia store — hodnoty cez store (getUser je getter, bez .value).
    const usersStore = useUser();

    if (localStorage.getItem('authToken') && !usersStore.getUser?.isAuth) {
        await usersStore.fetchUser();
    }

    if (to.meta.guestOnly && usersStore.getUser?.isAuth) {
        next({ name: 'dashboard.index' });
        return;
    }

    if (to.meta.superAdminOnly && !usersStore.getUser?.roles?.some((role) => ['super-admin', 'admin'].includes(role))) {
        next({
            name: usersStore.getUser?.isAuth ? 'dashboard.index' : 'public.login.index'
        });
        return;
    }
    next()
})

// Titulok, popis, canonical, OG a structured data — <head> sa inak medzi routami nemení.
router.afterEach((to) => {
    applyRouteSeo(to);
})


export default router;
