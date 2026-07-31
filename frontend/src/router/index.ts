import { createRouter, createWebHistory } from 'vue-router'
import DashboardPage from '../pages/DashboardPage.vue'
import CollectionPage from '../pages/CollectionPage.vue'
import LoginPage from '../pages/LoginPage.vue'
import RegistrationPage from '../pages/RegistrationPage.vue'
import StoragePage from '../pages/StoragePage.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: { name: 'dashboard' } },
    { path: '/login', name: 'login', component: LoginPage },
    { path: '/register', name: 'register', component: RegistrationPage },
    { path: '/dashboard', name: 'dashboard', component: DashboardPage, meta: { requiresAuth: true } },
    { path: '/collection', name: 'collection', component: CollectionPage, meta: { requiresAuth: true } },
    { path: '/storage', name: 'storage', component: StoragePage, meta: { requiresAuth: true } },
  ],
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !localStorage.getItem('cardnest.authToken')) return { name: 'login' }
})
