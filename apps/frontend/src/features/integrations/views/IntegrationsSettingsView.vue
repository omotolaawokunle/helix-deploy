<script setup lang="ts">
import { computed } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import CloudflareConnectionPanel from '@/features/integrations/components/CloudflareConnectionPanel.vue'
import DigitalOceanConnectionPanel from '@/features/integrations/components/DigitalOceanConnectionPanel.vue'

const authStore = useAuthStore()
const { orgId } = useActiveOrg()

const canManage = computed(() => authStore.isAdmin)
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Integrations"
      description="Connect external services your organization uses for DNS, credentials, and deployment workflows."
    />

    <CloudflareConnectionPanel
      v-if="orgId !== null"
      :organization-id="orgId"
      :can-manage="canManage"
    />

    <DigitalOceanConnectionPanel
      v-if="orgId !== null"
      :organization-id="orgId"
      :can-manage="canManage"
    />
  </div>
</template>
