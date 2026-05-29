import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'

const guestRouteNames = new Set(['login', 'register', 'verify-email'])

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/features/auth/views/LoginView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/features/auth/views/RegisterView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: () => import('@/features/auth/views/VerifyEmailView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/features/monitoring/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/servers',
    name: 'servers',
    component: () => import('@/features/servers/views/ServerListView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/servers/:id',
    name: 'server-detail',
    component: () => import('@/features/servers/views/ServerDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/servers/:id/sites/:siteId',
    name: 'server-site-detail',
    component: () => import('@/features/servers/views/ServerSiteView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/deployments/:id',
    name: 'deployment-detail',
    component: () => import('@/features/deployments/views/DeploymentDetailView.vue'),
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
