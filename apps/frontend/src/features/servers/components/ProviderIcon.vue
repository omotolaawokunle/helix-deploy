<script setup lang="ts">
import {
  CloudIcon,
  DropletIcon,
  ServerIcon,
  TerminalIcon,
} from '@lucide/vue'
import { ServerProvider } from '@/types'

interface Props {
  provider: ServerProvider | string
  class?: string
}

const props = defineProps<Props>()

const providerLabels: Record<string, string> = {
  [ServerProvider.Hetzner]: 'H',
  [ServerProvider.DigitalOcean]: 'DO',
  [ServerProvider.Aws]: 'AWS',
  [ServerProvider.Vultr]: 'V',
  [ServerProvider.Generic]: '',
  [ServerProvider.Linode]: 'L',
}
</script>

<template>
  <span
    class="inline-flex size-8 items-center justify-center rounded-md bg-muted text-muted-foreground"
    :class="props.class"
    :title="String(provider)"
  >
    <span
      v-if="provider === ServerProvider.Hetzner"
      class="text-sm font-bold"
    >
      {{ providerLabels[ServerProvider.Hetzner] }}
    </span>
    <DropletIcon
      v-else-if="provider === ServerProvider.DigitalOcean"
      class="size-4"
    />
    <CloudIcon
      v-else-if="provider === ServerProvider.Aws"
      class="size-4"
    />
    <ServerIcon
      v-else-if="provider === ServerProvider.Vultr || provider === ServerProvider.Linode"
      class="size-4"
    />
    <TerminalIcon
      v-else
      class="size-4"
    />
  </span>
</template>
