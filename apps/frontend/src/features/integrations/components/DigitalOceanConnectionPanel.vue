<script setup lang="ts">
import DnsProviderConnectionPanel from '@/features/integrations/components/DnsProviderConnectionPanel.vue'

interface Props {
  organizationId: string
  canManage: boolean
}

defineProps<Props>()
</script>

<template>
  <DnsProviderConnectionPanel
    provider="digitalocean"
    :organization-id="organizationId"
    :can-manage="canManage"
  />
</template>
