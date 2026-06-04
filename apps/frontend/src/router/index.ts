import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'

const guestRouteNames = new Set(['login', 'register', 'verify-email'])

const authenticatedRoutes: RouteRecordRaw[] = [
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/features/monitoring/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/servers',
    name: 'servers',
    component: () => import('@/features/servers/pages/ServersIndexPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/servers/:id',
    name: 'server-detail',
    component: () => import('@/features/servers/pages/ServerDetailPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/servers/:id/provisioning',
    name: 'server-provisioning',
    component: () => import('@/features/servers/pages/ProvisioningLogPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/servers/:id/sites/:siteId',
    name: 'server-site-detail',
    component: () => import('@/features/sites/pages/SiteDetailPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/deployments/:id',
    name: 'deployment-detail',
    component: () => import('@/features/deployments/pages/DeploymentDetailPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/projects',
    name: 'projects',
    component: () => import('@/features/projects/views/ProjectListView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/projects/:id',
    name: 'project-detail',
    component: () => import('@/features/projects/views/ProjectDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/pipelines',
    name: 'pipelines',
    component: () => import('@/features/pipelines/views/PipelineListView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/pipelines/:id',
    name: 'pipeline-detail',
    component: () => import('@/features/pipelines/views/PipelineDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/audit',
    name: 'audit',
    component: () => import('@/features/audit/views/AuditView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/settings/organization',
    name: 'organization-settings',
    component: () => import('@/features/organizations/views/OrganizationSettingsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/settings/team',
    name: 'team-settings',
    component: () => import('@/features/organizations/views/TeamSettingsView.vue'),
    meta: { requiresAuth: true },
  },
]

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/features/auth/pages/LoginPage.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/features/auth/pages/RegisterPage.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: () => import('@/features/auth/pages/EmailVerificationPage.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: authenticatedRoutes,
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, _from, next) => {
  const authStore = useAuthStore(pinia)

  if (to.meta.requiresAuth === true && !authStore.isAuthenticated) {
    next('/login')
    return
  }

  if (to.meta.requiresAuth === false && guestRouteNames.has(String(to.name)) && authStore.isAuthenticated) {
    next('/dashboard')
    return
  }

  next()
})

export default router
