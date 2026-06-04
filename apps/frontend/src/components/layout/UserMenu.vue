<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  LogOutIcon,
  MonitorIcon,
  MoonIcon,
  SunIcon,
  UserIcon,
} from '@lucide/vue'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useTheme } from '@/composables/useTheme'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { getInitials } from '@/lib/utils'
import type { ThemePreference } from '@/lib/theme'

const authStore = useAuthStore()
const router = useRouter()
const { preference, setPreference } = useTheme()

const userName = computed(() => authStore.user?.name ?? 'User')

const roleLabel = computed(() => {
  const role = authStore.currentRole

  if (role === null) {
    return 'Member'
  }

  return role.charAt(0).toUpperCase() + role.slice(1)
})

const themeLabel = computed(() => {
  const labels: Record<ThemePreference, string> = {
    light: 'Light',
    dark: 'Dark',
    system: 'System',
  }

  return labels[preference.value]
})

async function handleLogout(): Promise<void> {
  await authStore.logout()
  await router.push('/login')
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" class="h-auto w-full justify-start gap-3 px-2 py-2">
        <Avatar class="size-8">
          <AvatarFallback>{{ getInitials(userName) }}</AvatarFallback>
        </Avatar>
        <span class="flex min-w-0 flex-1 flex-col items-start gap-0.5">
          <span class="truncate text-sm font-medium">{{ userName }}</span>
          <Badge variant="secondary" class="text-[10px]">
            {{ roleLabel }}
          </Badge>
        </span>
      </Button>
    </DropdownMenuTrigger>

    <DropdownMenuContent align="start" class="w-56">
      <DropdownMenuLabel>Account</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuItem class="cursor-pointer">
        <UserIcon class="mr-2 size-4" />
        Profile
      </DropdownMenuItem>

      <DropdownMenuSub>
        <DropdownMenuSubTrigger class="cursor-pointer">
          <MonitorIcon class="mr-2 size-4" />
          Theme
          <span class="ml-auto text-xs text-muted-foreground">{{ themeLabel }}</span>
        </DropdownMenuSubTrigger>
        <DropdownMenuSubContent>
          <DropdownMenuRadioGroup
            :model-value="preference"
            @update:model-value="setPreference($event as ThemePreference)"
          >
            <DropdownMenuRadioItem value="light" class="cursor-pointer">
              <SunIcon class="mr-2 size-4" />
              Light
            </DropdownMenuRadioItem>
            <DropdownMenuRadioItem value="dark" class="cursor-pointer">
              <MoonIcon class="mr-2 size-4" />
              Dark
            </DropdownMenuRadioItem>
            <DropdownMenuRadioItem value="system" class="cursor-pointer">
              <MonitorIcon class="mr-2 size-4" />
              System
            </DropdownMenuRadioItem>
          </DropdownMenuRadioGroup>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuSeparator />
      <DropdownMenuItem class="cursor-pointer" @select="handleLogout">
        <LogOutIcon class="mr-2 size-4" />
        Logout
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
