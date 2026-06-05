<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PlusIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import EmptyState from '@/components/common/EmptyState.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { createSite, fetchServerSites, type CreateSitePayload } from '@/features/sites/api'
import { extractFieldErrors } from '@/lib/validation-errors'
import type { Site } from '@/types'

interface Props {
  serverId: string
}

const props = defineProps<Props>()

const sites = ref<Site[]>([])
const isLoading = ref(true)
const isAddOpen = ref(false)
const isSubmitting = ref(false)
const apiError = ref<string | null>(null)

const domain = ref('')
const runtime = ref('php')
const phpVersion = ref('8.3')
const appPort = ref('3000')
const deployBranch = ref('main')
const repositoryUrl = ref('')

const runtimeOptions = [
  { value: 'php', label: 'PHP' },
  { value: 'nodejs', label: 'Node.js' },
  { value: 'python', label: 'Python' },
  { value: 'go', label: 'Go' },
  { value: 'static', label: 'Static' },
  { value: 'docker', label: 'Docker' },
]

const requiresAppPort = computed(() =>
  ['nodejs', 'python', 'go', 'docker'].includes(runtime.value),
)

const requiresPhpVersion = computed(() => runtime.value === 'php')

async function loadSites(): Promise<void> {
  isLoading.value = true

  try {
    sites.value = await fetchServerSites(props.serverId)
  } finally {
    isLoading.value = false
  }
}

function resetForm(): void {
  domain.value = ''
  runtime.value = 'php'
  phpVersion.value = '8.3'
  appPort.value = '3000'
  deployBranch.value = 'main'
  repositoryUrl.value = ''
  apiError.value = null
}

function openAddSite(): void {
  resetForm()
  isAddOpen.value = true
}

async function handleCreate(): Promise<void> {
  isSubmitting.value = true
  apiError.value = null

  const payload: CreateSitePayload = {
    domain: domain.value.trim(),
    runtime: runtime.value,
    deployBranch: deployBranch.value.trim() || 'main',
  }

  if (requiresPhpVersion.value) {
    payload.phpVersion = phpVersion.value
  }

  if (requiresAppPort.value) {
    payload.appPort = Number(appPort.value)
  }

  if (repositoryUrl.value.trim() !== '') {
    payload.repositoryUrl = repositoryUrl.value.trim()
  }

  try {
    await createSite(props.serverId, payload)
    isAddOpen.value = false
    toast.success('Site provisioning started.')
    await loadSites()
  } catch (error: unknown) {
    const fieldErrors = extractFieldErrors(error)

    if (fieldErrors !== null) {
      apiError.value = Object.values(fieldErrors).flat().join(' ')
    } else {
      apiError.value = 'Unable to create site.'
    }
  } finally {
    isSubmitting.value = false
  }
}

watch(isAddOpen, (open) => {
  if (!open) {
    resetForm()
  }
})

onMounted(() => {
  void loadSites()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
      <p class="text-sm text-muted-foreground">
        Sites hosted on this server.
      </p>
      <Button type="button" size="sm" @click="openAddSite">
        <PlusIcon class="mr-2 size-4" />
        Add site
      </Button>
    </div>

    <EmptyState
      v-if="!isLoading && sites.length === 0"
      title="No sites on this server"
      description="Add a site to start deploying applications to this server."
      :icon="PlusIcon"
      @action="openAddSite"
    >
      <PlusIcon class="mr-2 size-4" />
      Add site
    </EmptyState>

    <div v-else class="panel overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Domain</TableHead>
            <TableHead>Branch</TableHead>
            <TableHead>Runtime</TableHead>
            <TableHead>Status</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="isLoading">
            <TableCell colspan="4" class="text-muted-foreground">
              Loading…
            </TableCell>
          </TableRow>
          <TableRow v-for="site in sites" :key="site.id">
            <TableCell>
              <RouterLink
                :to="`/servers/${serverId}/sites/${site.id}`"
                class="font-medium text-primary hover:underline"
              >
                {{ site.domain }}
              </RouterLink>
            </TableCell>
            <TableCell>{{ site.deployBranch }}</TableCell>
            <TableCell class="capitalize">
              {{ site.runtime }}
            </TableCell>
            <TableCell class="capitalize">
              {{ site.status }}
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isAddOpen">
      <SheetContent side="right" class="sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Add site</SheetTitle>
          <SheetDescription>
            Configure a new site. Provisioning runs in the background after you submit.
          </SheetDescription>
        </SheetHeader>

        <SheetBody class="space-y-4">
          <div class="space-y-2">
            <Label for="site-domain">Domain</Label>
            <Input
              id="site-domain"
              v-model="domain"
              placeholder="app.example.com"
              autocomplete="off"
            />
          </div>

          <div class="space-y-2">
            <Label>Runtime</Label>
            <Select v-model="runtime">
              <SelectTrigger>
                <SelectValue placeholder="Select runtime" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in runtimeOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="requiresPhpVersion" class="space-y-2">
            <Label for="site-php-version">PHP version</Label>
            <Input id="site-php-version" v-model="phpVersion" placeholder="8.3" />
          </div>

          <div v-if="requiresAppPort" class="space-y-2">
            <Label for="site-app-port">Application port</Label>
            <Input id="site-app-port" v-model="appPort" type="number" min="1" max="65535" />
          </div>

          <div class="space-y-2">
            <Label for="site-deploy-branch">Deploy branch</Label>
            <Input id="site-deploy-branch" v-model="deployBranch" placeholder="main" />
          </div>

          <div class="space-y-2">
            <Label for="site-repository-url">Repository URL</Label>
            <Input
              id="site-repository-url"
              v-model="repositoryUrl"
              placeholder="https://github.com/org/repo.git"
            />
          </div>

          <p v-if="apiError" class="text-sm text-destructive">
            {{ apiError }}
          </p>
        </SheetBody>

        <SheetFooter>
          <Button
            type="button"
            class="w-full"
            :disabled="isSubmitting || domain.trim() === ''"
            @click="handleCreate"
          >
            {{ isSubmitting ? 'Creating…' : 'Create site' }}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  </div>
</template>
