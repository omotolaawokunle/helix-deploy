<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue'
import { CheckIcon, ChevronsUpDownIcon, PlusIcon } from '@lucide/vue'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { getInitials } from '@/lib/utils'

const CreateOrgModal = defineAsyncComponent(
  () => import('@/components/layout/CreateOrgModal.vue'),
)

const authStore = useAuthStore()
const isCreateModalOpen = ref(false)
const isSwitching = ref(false)

const currentOrgName = computed(() => authStore.currentOrg?.name ?? 'Select organization')

async function handleSelect(orgId: string): Promise<void> {
  if (orgId === authStore.currentOrg?.id || isSwitching.value) {
    return
  }

  isSwitching.value = true

  try {
    await authStore.switchOrg(orgId)
  } finally {
    isSwitching.value = false
  }
}
</script>

<template>
  <div data-testid="org-switcher">
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <Button
          variant="outline"
          class="w-full justify-between gap-2 border-sidebar-border bg-background/60 px-2 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
          :disabled="isSwitching"
        >
          <span class="flex min-w-0 items-center gap-2">
            <Avatar class="size-6">
              <AvatarFallback class="text-[10px]">
                {{ getInitials(currentOrgName) }}
              </AvatarFallback>
            </Avatar>
            <span class="truncate text-left text-sm font-medium text-sidebar-foreground">{{ currentOrgName }}</span>
          </span>
          <ChevronsUpDownIcon class="size-4 shrink-0 opacity-50" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" class="w-56">
        <DropdownMenuLabel>Organizations</DropdownMenuLabel>
        <DropdownMenuSeparator />

        <DropdownMenuItem
          v-for="organization in authStore.organizations"
          :key="organization.id"
          class="cursor-pointer"
          data-testid="org-option"
          @select="handleSelect(organization.id)"
        >
          <CheckIcon
            class="mr-2 size-4"
            :class="organization.id === authStore.currentOrg?.id ? 'opacity-100' : 'opacity-0'"
          />
          <span class="truncate">{{ organization.name }}</span>
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        <DropdownMenuItem
          class="cursor-pointer"
          data-testid="new-org-option"
          @select="isCreateModalOpen = true"
        >
          <PlusIcon class="mr-2 size-4" />
          New Organization
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>

    <CreateOrgModal
      v-if="isCreateModalOpen"
      v-model:open="isCreateModalOpen"
      @created="authStore.loadOrganizations()"
    />
  </div>
</template>
