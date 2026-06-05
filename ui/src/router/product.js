import { PAGE_PRODUCT } from '../constants';
const product = [
    {
        path: '/products',
        name: PAGE_PRODUCT.ROUTE,
        components: {
            default: () => import('../components/product/ProductIndex.vue'),
        },
        meta: {
            title: 'Zoznam tovaru'
        }
    },
    {
        path: '/products/:productId/show',
        name: 'products.show',
        components: {
            default: () => import('../components/product/PublicProductShow.vue'),
        },
        meta: {
            title: 'Zobraziť položku'
        }
    },
    {
        path: '/products/create',
        name: 'products.create',
        components: {
            default: () => import('../components/product/ProductForm.vue'),
        },
        meta: {
            title: 'Pridať nový tovar'
        }

    },
    {
        path: '/products/:productId/edit',
        name: 'products.edit',
        components: {
            default: () => import('../components/product/ProductForm.vue'),
        },
        meta: {
            title: 'Upraviť položku'
        }
    }
]

export default product;
