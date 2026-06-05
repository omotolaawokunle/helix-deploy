<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import ConnectionWaitStrip from '@/components/common/ConnectionWaitStrip.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { Badge } from '@/components/ui/badge'
import ProviderIcon from '@/features/servers/components/ProviderIcon.vue'
import { ServerStatus, type Server } from '@/types'

interface Props {
  server: Server
}

const props = defineProps<Props>()

const isConnecting = computed(() => props.server.status === ServerStatus.Connecting)

const environmentName = computed(
  () => props.server.environment?.label ?? props.server.environment?.name ?? 'development',
)

const isProduction = computed(() => props.server.environment?.isProduction ?? false)
</script>

<template>
  <RouterLink
    :to="`/servers/${server.id}`"
    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
  >
    <article
      class="panel group cursor-pointer p-5 transition-colors duration-200 hover:border-primary/30 hover:bg-accent/30"
      data-testid="server-card"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
          <h3 class="truncate text-base font-semibold group-hover:text-primary">
            {{ server.hostname }}
          </h3>
          <p class="mt-0.5 font-mono text-xs text-muted-foreground">
            {{ server.ipAddress }}
          </p>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-2">
          <EnvironmentBadge
            :environment="environmentName"
            :is-production="isProduction"
          />
          <ProviderIcon :provider="server.provider" class="size-7" />
        </div>
      </div>

      <div
        v-if="server.project || (server.tags?.length ?? 0) > 0"
        class="mt-3 flex flex-wrap gap-2"
      >
        <Badge v-if="server.project" variant="secondary" class="text-xs font-normal">
          {{ server.project.name }}
        </Badge>
        <Badge
          v-for="tag in server.tags ?? []"
          :key="tag"
          variant="outline"
          class="text-xs font-normal"
        >
          {{ tag }}
        </Badge>
      </div>

      <ConnectionWaitStrip
        v-if="isConnecting"
        label="Connecting…"
        data-testid="server-connecting-skeleton"
      />

      <div class="mt-4 flex items-center justify-between border-t pt-4">
        <StatusBadge :status="server.status" type="server" />
        <span
          v-if="server.managementMode === 'observe'"
          class="text-xs text-muted-foreground"
        >
          Observe
        </span>
      </div>
    </article>
  </RouterLink>
</template>
