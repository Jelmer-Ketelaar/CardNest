import { createRouter, createWebHistory } from 'vue-router'
import DashboardPage from '../pages/DashboardPage.vue'
import CollectionPage from '../pages/CollectionPage.vue'
import BindersPage from '../pages/BindersPage.vue'
import CartPage from '../pages/CartPage.vue'
import LoginPage from '../pages/LoginPage.vue'
import MarketplacePage from '../pages/MarketplacePage.vue'
import MyListingsPage from '../pages/MyListingsPage.vue'
import OrdersPage from '../pages/OrdersPage.vue'
import RegistrationPage from '../pages/RegistrationPage.vue'
import StoragePage from '../pages/StoragePage.vue'
import WishlistsPage from '../pages/WishlistsPage.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: { name: 'dashboard' } },
    { path: '/login', name: 'login', component: LoginPage },
    { path: '/register', name: 'register', component: RegistrationPage },
    { path: '/marketplace', name: 'marketplace', component: MarketplacePage },
    { path: '/dashboard', name: 'dashboard', component: DashboardPage, meta: { requiresAuth: true } },
    { path: '/collection', name: 'collection', component: CollectionPage, meta: { requiresAuth: true } },
    { path: '/storage', name: 'storage', component: StoragePage, meta: { requiresAuth: true } },
    { path: '/binders', name: 'binders', component: BindersPage, meta: { requiresAuth: true } },
    { path: '/wishlists', name: 'wishlists', component: WishlistsPage, meta: { requiresAuth: true } },
    { path: '/my-listings', name: 'my-listings', component: MyListingsPage, meta: { requiresAuth: true } },
    { path: '/cart', name: 'cart', component: CartPage, meta: { requiresAuth: true } },
    { path: '/orders', name: 'orders', component: OrdersPage, meta: { requiresAuth: true } },
  ],
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !localStorage.getItem('cardnest.authToken')) return { name: 'login' }
})
