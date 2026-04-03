import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import AuditQueueView from '@/views/AuditQueueView.vue'

function hasToken() {
  return !!localStorage.getItem('load_audit_token')
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/audit',
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/audit',
      name: 'audit',
      component: AuditQueueView,
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !hasToken()) {
    return '/login'
  }

  if (to.meta.guestOnly && hasToken()) {
    return '/audit'
  }

  return true
})

export default router
