<script setup lang="ts">
import { computed } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import FirstVisitHint from '@/features/onboarding/components/FirstVisitHint.vue'
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
      description="Connect DNS providers to automate records when you create sites. Tokens are encrypted and never shown after save."
    />

    <FirstVisitHint
      v-if="orgId !== null"
      hint-id="integrations-settings"
      title="When to connect DNS"
      description="Connect Cloudflare or DigitalOcean here, assign zones to a project, then enable auto-create DNS on each site. Skip this page if you manage DNS records manually."
    />

    <div
      v-if="orgId !== null"
      class="grid gap-6 lg:grid-cols-2"
    >
      <CloudflareConnectionPanel
        class="animate-panel-in"
        :organization-id="orgId"
        :can-manage="canManage"
      />

      <DigitalOceanConnectionPanel
        class="animate-panel-in animate-panel-in-delay-1"
        :organization-id="orgId"
        :can-manage="canManage"
      />
    </div>
  </div>
</template>
