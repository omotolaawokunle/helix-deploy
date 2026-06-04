<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { deleteSite, updateSite } from '@/features/sites/api'
import type { Site } from '@/types'

interface Props {
  site: Site
}

const props = defineProps<Props>()

const emit = defineEmits<{
  updated: [site: Site]
}>()

const router = useRouter()

const deployBranch = ref('')
const deployScript = ref('')
const runMigrations = ref(false)
const dockerImage = ref('')
const dockerRegistry = ref('')
const dockerComposePath = ref('')
const isSaving = ref(false)
const isDeleteDialogOpen = ref(false)

watch(
  () => props.site,
  (site) => {
    deployBranch.value = site.deployBranch
    deployScript.value = site.deployScript ?? ''
    runMigrations.value = site.runMigrations
    dockerImage.value = site.dockerImage ?? ''
    dockerRegistry.value = site.dockerRegistry ?? ''
    dockerComposePath.value = site.dockerComposePath ?? ''
  },
  { immediate: true },
)

async function handleSave(): Promise<void> {
  isSaving.value = true

  try {
    const updated = await updateSite(props.site.id, {
      deployBranch: deployBranch.value,
      deployScript: deployScript.value,
      runMigrations: runMigrations.value,
      dockerImage: dockerImage.value || null,
      dockerRegistry: dockerRegistry.value || null,
      dockerComposePath: dockerComposePath.value || null,
    })
    emit('updated', updated)
    toast.success('Site settings saved.')
  } catch {
    toast.error('Unable to save site settings.')
  } finally {
    isSaving.value = false
  }
}

async function handleDelete(): Promise<void> {
  try {
    await deleteSite(props.site.id)
    toast.success('Site deleted.')
    await router.push(`/servers/${props.site.serverId}`)
  } catch {
    toast.error('Unable to delete site.')
  }
}
</script>

<template>
  <div class="space-y-8">
    <form class="panel space-y-4 p-6" @submit.prevent="handleSave">
      <h2 class="section-label">
        Deployment
      </h2>
      <div class="space-y-2">
        <Label for="deploy-branch">Deploy branch</Label>
        <Input id="deploy-branch" v-model="deployBranch" />
      </div>
      <div class="space-y-2">
        <Label for="deploy-script">Deploy script</Label>
        <Textarea id="deploy-script" v-model="deployScript" rows="8" class="font-mono text-sm" />
      </div>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="runMigrations" type="checkbox" class="rounded border-input">
        Run migrations on deploy
      </label>

      <h2 class="section-label pt-4">
        Docker
      </h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="docker-image">Image</Label>
          <Input id="docker-image" v-model="dockerImage" />
        </div>
        <div class="space-y-2">
          <Label for="docker-registry">Registry</Label>
          <Input id="docker-registry" v-model="dockerRegistry" />
        </div>
        <div class="space-y-2 sm:col-span-2">
          <Label for="docker-compose">Compose path</Label>
          <Input id="docker-compose" v-model="dockerComposePath" />
        </div>
      </div>

      <Button type="submit" :disabled="isSaving">
        Save settings
      </Button>
    </form>

    <section class="rounded-lg border border-destructive/40 bg-destructive/5 p-6">
      <h2 class="text-sm font-semibold text-destructive">
        Danger Zone
      </h2>
      <p class="mt-2 text-sm text-muted-foreground">
        Permanently delete this site and its configuration from HelixDeploy.
      </p>
      <Button
        type="button"
        variant="destructive"
        class="mt-4"
        @click="isDeleteDialogOpen = true"
      >
        Delete Site
      </Button>
    </section>

    <ConfirmDestructiveDialog
      v-model:open="isDeleteDialogOpen"
      title="Delete site"
      :description="`This will permanently delete ${site.domain}. This cannot be undone.`"
      :confirm-text="site.domain"
      confirm-button-label="Delete site"
      @confirm="handleDelete"
    />
  </div>
</template>
