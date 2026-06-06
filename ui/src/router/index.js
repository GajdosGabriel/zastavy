import { createRouter, createWebHistory } from 'vue-router';
import { APP_NAME } from '../constants';
import product from './product';
import order from './order';
import customer from './customer';
import stock from './stock';
import user from './user';
import announcement from './announcement';
import useStoreErrors from '../store/StoreErrors';
import useStoreOrders from '../store/StoreOrders';
import useUser from '../store/StoreUsers';



const routes = [
    ...product,
    ...order,
    ...customer,
    ...stock,
    ...user,
    ...announcement,
    {
        path: '/',
        name: 'public.index',
        components: {
            default: () => import('../components/Home.vue'),
            // navigation: () => import('../components/pages/navigationMain.vue')
        },
        meta: {
            title: APP_NAME
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
        meta: {
            
        }
    },
    {
        path: '/obchodne-podmienky',
        name: 'public.obchodne.podmienky',
        components: {
            default: () => import('../components/pages/obchodnePodmienky.vue'),
        },
        meta: {
            title: 'Obchodné podmienky'
        }
    },
    {
        path: '/ochrana-osobnych-udajov',
        name: 'public.ochranaOsobnychUdajov',
        components: {
            default: () => import('../components/pages/ochranaOsobnychUdajov.vue'),
        },
        meta: {
            title: 'Ochrana osobných údajov'
        }
    },
    {
        path: '/kontakt',
        name: 'public.contactUs',
        components: {
            default: () => import('../components/pages/contactUs.vue'),
        },
        meta: {
            title: 'Kontaktné údaje'
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

    const { fetchUser, getUser } = useUser();

    if (localStorage.getItem('authToken') && !getUser.value?.isAuth) {
        await fetchUser();
    }

    if (to.meta.guestOnly && getUser.value?.isAuth) {
        next({ name: 'dashboard.index' });
        return;
    }

    if (to.meta.superAdminOnly && !getUser.value?.roles?.some((role) => ['super-admin', 'admin'].includes(role))) {
        next({
            name: getUser.value?.isAuth ? 'dashboard.index' : 'public.login.index'
        });
        return;
    }
    
    const title = to.meta.title

    //Take the title from the parameters
    const titleFromParams = to.params.pageTitle;
    // If the route has a title, set it as the page title of the document/page
    if (title) {
        document.title = title
    }
    // If we have a title from the params, extend the title with the title
    // from our params
    if (titleFromParams) {
        document.title = `${titleFromParams} - ${document.title}`;
    }
    // Continue resolving the route


    next()
})


export default router;
