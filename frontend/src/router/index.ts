import { createRouter, createWebHistory } from 'vue-router'
import AccountPage from '../pages/AccountPage.vue'
import DashboardPage from '../pages/DashboardPage.vue'
import HealthPage from '../pages/HealthPage.vue'
import CollectionPage from '../pages/CollectionPage.vue'
import CollectionItemDetailPage from '../pages/CollectionItemDetailPage.vue'
import BindersPage from '../pages/BindersPage.vue'
import CartPage from '../pages/CartPage.vue'
import CatalogCardDetailPage from '../pages/CatalogCardDetailPage.vue'
import CatalogCardsPage from '../pages/CatalogCardsPage.vue'
import CatalogSetDetailPage from '../pages/CatalogSetDetailPage.vue'
import CatalogSetsPage from '../pages/CatalogSetsPage.vue'
import LoginPage from '../pages/LoginPage.vue'
import MarketplacePage from '../pages/MarketplacePage.vue'
import MyListingsPage from '../pages/MyListingsPage.vue'
import OrdersPage from '../pages/OrdersPage.vue'
import RegistrationPage from '../pages/RegistrationPage.vue'
import StoragePage from '../pages/StoragePage.vue'
import UserProfilePage from '../pages/UserProfilePage.vue'
import VerifyEmailPage from '../pages/VerifyEmailPage.vue'
import WishlistsPage from '../pages/WishlistsPage.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: { name: 'dashboard' } },
    { path: '/login', name: 'login', component: LoginPage },
    { path: '/register', name: 'register', component: RegistrationPage },
    { path: '/verify-email', name: 'verify-email', component: VerifyEmailPage },
    { path: '/health', name: 'health', component: HealthPage },
    { path: '/catalog/sets', name: 'catalog-sets', component: CatalogSetsPage },
    { path: '/catalog/sets/:id', name: 'catalog-set', component: CatalogSetDetailPage },
    { path: '/catalog/cards', name: 'catalog-cards', component: CatalogCardsPage },
    { path: '/catalog/cards/:id', name: 'catalog-card', component: CatalogCardDetailPage },
    { path: '/marketplace', name: 'marketplace', component: MarketplacePage },
    { path: '/marketplace/listings/:id', name: 'marketplace-listing', component: MarketplacePage },
    { path: '/users/:id', name: 'user-profile', component: UserProfilePage },
    { path: '/account', name: 'account', component: AccountPage, meta: { requiresAuth: true } },
    { path: '/dashboard', name: 'dashboard', component: DashboardPage, meta: { requiresAuth: true } },
    { path: '/collection', name: 'collection', component: CollectionPage, meta: { requiresAuth: true } },
    { path: '/collection/:id', name: 'collection-item', component: CollectionItemDetailPage, meta: { requiresAuth: true } },
    { path: '/storage', name: 'storage', component: StoragePage, meta: { requiresAuth: true } },
    { path: '/binders', name: 'binders', component: BindersPage, meta: { requiresAuth: true } },
    { path: '/wishlists', name: 'wishlists', component: WishlistsPage, meta: { requiresAuth: true } },
    { path: '/wishlists/new', name: 'wishlist-create', component: WishlistsPage, meta: { requiresAuth: true } },
    { path: '/wishlists/:id', name: 'wishlist-detail', component: WishlistsPage, meta: { requiresAuth: true } },
    { path: '/my-listings', name: 'my-listings', component: MyListingsPage, meta: { requiresAuth: true } },
    { path: '/cart', name: 'cart', component: CartPage, meta: { requiresAuth: true } },
    { path: '/orders', name: 'orders', component: OrdersPage, meta: { requiresAuth: true } },
  ],
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !localStorage.getItem('cardnest.authToken')) return { name: 'login' }
})
