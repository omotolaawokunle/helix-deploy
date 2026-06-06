import { pinia } from "@/stores";
import { useAuthStore } from "@/stores/auth";
import {
  createRouter,
  createWebHistory,
  type RouteRecordRaw,
} from "vue-router";

const guestRouteNames = new Set([
  "login",
  "register",
  "verify-email",
  "accept-invitation",
]);

const authenticatedRoutes: RouteRecordRaw[] = [
  {
    path: "/dashboard",
    name: "dashboard",
    component: () => import("@/features/monitoring/views/DashboardView.vue"),
    meta: { requiresAuth: true, title: "Dashboard" },
  },
  {
    path: "/servers",
    name: "servers",
    component: () => import("@/features/servers/pages/ServersIndexPage.vue"),
    meta: { requiresAuth: true, title: "Servers" },
  },
  {
    path: "/servers/:id",
    name: "server-detail",
    component: () => import("@/features/servers/pages/ServerDetailPage.vue"),
    meta: { requiresAuth: true, title: "Server" },
  },
  {
    path: "/servers/:id/provisioning",
    name: "server-provisioning",
    component: () => import("@/features/servers/pages/ProvisioningLogPage.vue"),
    meta: { requiresAuth: true, title: "Provisioning" },
  },
  {
    path: "/servers/:id/sites/:siteId",
    name: "server-site-detail",
    component: () => import("@/features/sites/pages/SiteDetailPage.vue"),
    meta: { requiresAuth: true, title: "Site" },
  },
  {
    path: '/build-runners',
    name: 'build-runners',
    component: () => import('@/features/build-runners/pages/BuildRunnersIndexPage.vue'),
    meta: { requiresAuth: true, title: 'Build Runners' },
  },
  {
    path: '/deployments/:id',
    name: "deployment-detail",
    component: () =>
      import("@/features/deployments/pages/DeploymentDetailPage.vue"),
    meta: { requiresAuth: true, title: "Deployment" },
  },
  {
    path: "/projects",
    name: "projects",
    component: () => import("@/features/projects/views/ProjectListView.vue"),
    meta: { requiresAuth: true, title: "Projects" },
  },
  {
    path: "/projects/:id",
    name: "project-detail",
    component: () => import("@/features/projects/views/ProjectDetailView.vue"),
    meta: { requiresAuth: true, title: "Project" },
  },
  {
    path: "/pipelines",
    name: "pipelines",
    component: () => import("@/features/pipelines/views/PipelineListView.vue"),
    meta: { requiresAuth: true, title: "Pipelines" },
  },
  {
    path: "/pipelines/:id",
    name: "pipeline-detail",
    component: () =>
      import("@/features/pipelines/views/PipelineDetailView.vue"),
    meta: { requiresAuth: true, title: "Pipeline" },
  },
  {
    path: "/audit",
    name: "audit",
    component: () => import("@/features/audit/views/AuditView.vue"),
    meta: { requiresAuth: true, title: "Audit Log" },
  },
  {
    path: "/settings/organization",
    name: "organization-settings",
    component: () =>
      import("@/features/organizations/views/OrganizationSettingsView.vue"),
    meta: { requiresAuth: true, title: "Organization Settings" },
  },
  {
    path: '/settings/teams',
    name: 'teams',
    component: () => import('@/features/teams/views/TeamsIndexView.vue'),
    meta: { requiresAuth: true, title: 'Teams' },
  },
  {
    path: '/settings/teams/:id',
    name: 'team-detail',
    component: () => import('@/features/teams/views/TeamDetailView.vue'),
    meta: { requiresAuth: true, title: 'Team' },
  },
  {
    path: "/settings/team",
    name: "team-settings",
    component: () =>
      import("@/features/organizations/views/TeamSettingsView.vue"),
    meta: { requiresAuth: true, title: "Profile Settings" },
  },
];

const routes: RouteRecordRaw[] = [
  {
    path: "/login",
    name: "login",
    component: () => import("@/features/auth/pages/LoginPage.vue"),
    meta: { requiresAuth: false },
  },
  {
    path: "/register",
    name: "register",
    component: () => import("@/features/auth/pages/RegisterPage.vue"),
    meta: { requiresAuth: false },
  },
  {
    path: "/verify-email",
    name: "verify-email",
    component: () => import("@/features/auth/pages/EmailVerificationPage.vue"),
    meta: { requiresAuth: false },
  },
  {
    path: "/accept-invitation",
    name: "accept-invitation",
    component: () =>
      import("@/features/organizations/pages/AcceptInvitationPage.vue"),
    meta: { requiresAuth: false },
  },
  {
    path: "/",
    component: () => import("@/layouts/AppLayout.vue"),
    meta: { requiresAuth: true },
    children: [
      {
        path: "",
        redirect: "/dashboard",
      },
      ...authenticatedRoutes,
    ],
  },
  {
    path: "/:pathMatch(.*)*",
    name: "not-found",
    component: () => import("@/features/errors/views/NotFoundView.vue"),
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore(pinia);

  if (!authStore.isInitialized) {
    await authStore.init();
  }

  if (to.meta.requiresAuth === true) {
    if (!authStore.isAuthenticated) {
      next("/login");
      return;
    }

    if (!authStore.isEmailVerified) {
      next("/verify-email");
      return;
    }
  }

  if (
    to.meta.requiresAuth === false &&
    guestRouteNames.has(String(to.name)) &&
    authStore.isAuthenticated
  ) {
    if (
      String(to.name) === "verify-email" ||
      String(to.name) === "accept-invitation"
    ) {
      if (
        String(to.name) === "verify-email" &&
        authStore.isEmailVerified
      ) {
        next("/dashboard");
        return;
      }

      next();
      return;
    }

    if (!authStore.isEmailVerified) {
      next("/verify-email");
      return;
    }

    next("/dashboard");
    return;
  }

  next();
});

export default router;
