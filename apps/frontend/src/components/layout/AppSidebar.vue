<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  ActivityIcon,
  Building2Icon,
  ClipboardListIcon,
  CpuIcon,
  FolderKanbanIcon,
  GitBranchIcon,
  HexagonIcon,
  LayersIcon,
  ServerIcon,
  UserCircleIcon,
  UsersIcon,
} from '@lucide/vue'
import OrgSwitcher from '@/components/layout/OrgSwitcher.vue'
import UserMenu from '@/components/layout/UserMenu.vue'
import { useRoutePrefetch } from '@/composables/useRoutePrefetch'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { cn } from '@/lib/utils'

interface NavItem {
  label: string
  to: string
  icon: typeof ServerIcon
  visible?: boolean
}

const route = useRoute()
const authStore = useAuthStore()
const { prefetchRoute } = useRoutePrefetch()

const primaryNavItems = computed<NavItem[]>(() => [
  { label: 'Dashboard', to: '/dashboard', icon: ActivityIcon, visible: true },
  { label: 'Servers', to: '/servers', icon: ServerIcon, visible: true },
  { label: 'Build Runners', to: '/build-runners', icon: CpuIcon, visible: true },
  { label: 'Projects', to: '/projects', icon: FolderKanbanIcon, visible: true },
  { label: 'Pipelines', to: '/pipelines', icon: GitBranchIcon, visible: true },
  {
    label: 'Audit Log',
    to: '/audit',
    icon: ClipboardListIcon,
    visible: authStore.canViewAuditLog,
  },
])

const settingsNavItems = computed<NavItem[]>(() => [
  { label: 'Profile Settings', to: '/settings/team', icon: UserCircleIcon, visible: true },
  { label: 'Teams', to: '/settings/teams', icon: UsersIcon, visible: true },
  {
    label: 'Provisioning Templates',
    to: '/settings/provisioning-templates',
    icon: LayersIcon,
    visible: true,
  },
  {
    label: 'Organization Settings',
    to: '/settings/organization',
    icon: Building2Icon,
    visible: authStore.canManageOrgSettings,
  },
])

function isActive(path: string): boolean {
  return route.path === path || route.path.startsWith(`${path}/`)
}

function navLinkClass(active: boolean): string {
  return cn(
    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
    active
      ? 'bg-primary/10 text-primary'
      : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
  )
}
</script>

<template>
  <div class="flex h-full flex-col text-sidebar-foreground">
    <div class="flex items-center gap-2.5 px-4 py-5">
      <HexagonIcon class="size-6 text-primary" />
      <span class="text-lg font-semibold tracking-tight text-sidebar-foreground">HelixDeploy</span>
    </div>

    <div class="px-3 pb-4">
      <OrgSwitcher />
    </div>

    <nav class="flex-1 space-y-0.5 px-3">
      <RouterLink
        v-for="item in primaryNavItems.filter(entry => entry.visible !== false)"
        :key="item.to"
        :to="item.to"
        :class="navLinkClass(isActive(item.to))"
        @mouseenter="prefetchRoute(item.to)"
      >
        <component :is="item.icon" class="size-4 shrink-0" />
        {{ item.label }}
      </RouterLink>

      <p class="px-3 pb-2 pt-6 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        Settings
      </p>

      <RouterLink
        v-for="item in settingsNavItems.filter(entry => entry.visible !== false)"
        :key="item.to"
        :to="item.to"
        :class="navLinkClass(isActive(item.to))"
        @mouseenter="prefetchRoute(item.to)"
      >
        <component :is="item.icon" class="size-4 shrink-0" />
        {{ item.label }}
      </RouterLink>
    </nav>

    <div class="mt-auto border-t border-sidebar-border p-3">
      <UserMenu />
    </div>
  </div>
</template>
