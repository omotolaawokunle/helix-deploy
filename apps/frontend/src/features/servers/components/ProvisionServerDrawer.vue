<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { isAxiosError } from 'axios'
import { InfoIcon, ShieldCheckIcon } from '@lucide/vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useActiveOrg } from '@/composables/useActiveOrg'
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
  fetchProvisioningTemplates,
} from '@/features/provisioning/api'
import type { ProvisioningTemplateRecord } from '@/features/provisioning/types'
import { formatProvisioningTemplateName } from '@/features/provisioning/constants'
import {
  provisionServer,
} from '@/features/servers/api'
import {
  NODE_VERSIONS,
  PHP_VERSIONS,
  PROVISIONING_SCRIPTS,
  SCRIPT_ESTIMATED_MINUTES,
  type ProvisioningScript,
} from '@/features/servers/types'

interface Props {
  open: boolean
  serverId: string
  detectedServices?: string[]
}

const props = withDefaults(defineProps<Props>(), {
  detectedServices: () => [],
})

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const router = useRouter()
const { orgId } = useActiveOrg()

const templates = ref<ProvisioningTemplateRecord[]>([])
const templatesLoadedForOrgId = ref<string | null>(null)
const isLoadingTemplates = ref(false)
const selectedScripts = ref<Set<ProvisioningScript>>(new Set())
const phpVersion = ref<string>('8.3')
const nodeVersion = ref<number>(20)
const isSubmitting = ref(false)
const apiError = ref<string | null>(null)

const scriptLabels: Record<ProvisioningScript, string> = {
  'create-deploy-user': 'Deploy user',
  nginx: 'Nginx',
  php: 'PHP',
  mysql: 'MySQL',
  postgresql: 'PostgreSQL',
  redis: 'Redis',
  nodejs: 'Node.js',
  python: 'Python',
  supervisor: 'Supervisor',
  docker: 'Docker',
  certbot: 'Certbot (Let\'s Encrypt)',
}

const estimatedMinutes = computed(() =>
  [...selectedScripts.value].reduce(
    (total, script) => total + (SCRIPT_ESTIMATED_MINUTES[script] ?? 0),
    0,
  ),
)

const showPhpSelector = computed(() => selectedScripts.value.has('php'))
const showNodeSelector = computed(() => selectedScripts.value.has('nodejs'))

const detectedServiceLabels = computed((): string[] =>
  props.detectedServices.map((service) => scriptLabels[service as ProvisioningScript] ?? service),
)

const skippedSelectedCount = computed((): number =>
  [...selectedScripts.value].filter((script) => props.detectedServices.includes(script)).length,
)

function toggleScript(script: ProvisioningScript): void {
  const next = new Set(selectedScripts.value)

  if (next.has(script)) {
    next.delete(script)
  } else {
    next.add(script)
  }

  selectedScripts.value = next
}

function resetDrawerState(): void {
  selectedScripts.value = new Set()
  phpVersion.value = '8.3'
  nodeVersion.value = 20
  apiError.value = null
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      resetDrawerState()
      return
    }

    void ensureTemplatesLoaded()
  },
)

async function ensureTemplatesLoaded(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    templates.value = []
    templatesLoadedForOrgId.value = null
    return
  }

  if (templatesLoadedForOrgId.value === activeOrgId && templates.value.length > 0) {
    return
  }

  isLoadingTemplates.value = true

  try {
    templates.value = await fetchProvisioningTemplates(activeOrgId)
    templatesLoadedForOrgId.value = activeOrgId
  } catch {
    templates.value = []
    templatesLoadedForOrgId.value = null
  } finally {
    isLoadingTemplates.value = false
  }
}

function templateLabel(template: ProvisioningTemplateRecord): string {
  return formatProvisioningTemplateName(template.name)
}

function applyTemplate(template: ProvisioningTemplateRecord): void {
  selectedScripts.value = new Set(template.services as ProvisioningScript[])

  const php = template.options.phpVersion

  if (typeof php === 'string') {
    phpVersion.value = php
  }

  const node = template.options.nodeVersion

  if (typeof node === 'number') {
    nodeVersion.value = node
  }
}

function resolveProvisionError(error: unknown): string {
  if (isAxiosError(error) && error.response?.status === 403) {
    return 'Provisioning is disabled while this server is in Observe mode.'
  }

  return 'Unable to start provisioning. Please try again.'
}

async function handleSubmit(): Promise<void> {
  if (selectedScripts.value.size === 0) {
    apiError.value = 'Select at least one service to install.'
    return
  }

  isSubmitting.value = true
  apiError.value = null

  try {
    const response = await provisionServer(props.serverId, {
      scripts: [...selectedScripts.value],
      options: {
        ...(showPhpSelector.value ? { phpVersion: phpVersion.value } : {}),
        ...(showNodeSelector.value ? { nodeVersion: nodeVersion.value } : {}),
      },
    })

    emit('update:open', false)

    await router.push({
      path: `/servers/${props.serverId}/provisioning`,
      query: { runId: response.jobId },
    })
  } catch (error: unknown) {
    apiError.value = resolveProvisionError(error)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Sheet :open="open" @update:open="emit('update:open', $event)">
    <SheetContent side="right" class="flex w-full flex-col sm:max-w-lg">
      <SheetHeader>
        <SheetTitle>Provision server</SheetTitle>
        <SheetDescription>
          Install missing services only. Existing nginx configs and packages are detected and left in place.
        </SheetDescription>
      </SheetHeader>

      <SheetBody class="space-y-6">
        <Alert
          class="border-primary/20 bg-primary/5 text-foreground *:data-[slot=alert-description]:text-muted-foreground"
          data-testid="provision-safe-stack-alert"
        >
          <ShieldCheckIcon class="text-primary" aria-hidden="true" />
          <AlertTitle>Safe for imported servers</AlertTitle>
          <AlertDescription>
            HelixDeploy skips services already on the box, preserves existing nginx configuration, and
            disables Apache if it would conflict with nginx on port 80.
          </AlertDescription>
        </Alert>

        <div
          v-if="detectedServiceLabels.length > 0"
          class="rounded-lg border border-border bg-muted/30 px-4 py-3"
          data-testid="provision-detected-services"
        >
          <p class="text-sm font-medium text-foreground">
            Already detected on this server
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            These services will be skipped if selected again.
          </p>
          <div class="mt-3 flex flex-wrap gap-1.5">
            <Badge
              v-for="service in detectedServiceLabels"
              :key="service"
              variant="secondary"
              class="capitalize"
            >
              {{ service }}
            </Badge>
          </div>
        </div>

        <div class="space-y-2">
          <Label>Quick templates</Label>
          <div v-if="isLoadingTemplates" class="flex flex-wrap gap-2">
            <Skeleton v-for="index in 4" :key="index" class="h-8 w-28 rounded-md" />
          </div>
          <div v-else-if="templates.length > 0" class="flex flex-wrap gap-2">
            <Button
              v-for="template in templates"
              :key="template.id"
              type="button"
              variant="outline"
              size="sm"
              @click="applyTemplate(template)"
            >
              {{ templateLabel(template) }}
            </Button>
          </div>
          <p v-else class="text-sm text-muted-foreground">
            No templates available.
          </p>
        </div>

        <div class="space-y-3">
          <Label>Services</Label>
          <div class="grid grid-cols-2 gap-2">
            <label
              v-for="script in PROVISIONING_SCRIPTS"
              :key="script"
              class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-3 text-sm text-foreground transition-colors duration-200 hover:bg-muted/50"
              :class="selectedScripts.has(script) ? 'border-primary bg-primary/5' : ''"
            >
              <input
                type="checkbox"
                class="size-4 rounded border-input"
                :checked="selectedScripts.has(script)"
                @change="toggleScript(script)"
              >
              <span class="flex min-w-0 flex-1 items-center justify-between gap-2">
                <span>{{ scriptLabels[script] }}</span>
                <Badge
                  v-if="detectedServices.includes(script)"
                  variant="outline"
                  class="shrink-0 text-[10px] uppercase tracking-wide"
                >
                  Detected
                </Badge>
              </span>
            </label>
          </div>
        </div>

        <div v-if="showPhpSelector" class="space-y-2">
          <Label>PHP version</Label>
          <Select v-model="phpVersion">
            <SelectTrigger>
              <SelectValue placeholder="Select PHP version" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="version in PHP_VERSIONS"
                :key="version"
                :value="version"
              >
                PHP {{ version }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div v-if="showNodeSelector" class="space-y-2">
          <Label>Node.js version</Label>
          <Select
            :model-value="String(nodeVersion)"
            @update:model-value="nodeVersion = Number($event)"
          >
            <SelectTrigger>
              <SelectValue placeholder="Select Node version" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="version in NODE_VERSIONS"
                :key="version"
                :value="String(version)"
              >
                Node {{ version }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-1 text-sm text-muted-foreground">
          <p>Estimated time: ~{{ estimatedMinutes }} min</p>
          <p v-if="skippedSelectedCount > 0" class="flex items-start gap-1.5">
            <InfoIcon class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            <span>
              {{ skippedSelectedCount }} selected service{{ skippedSelectedCount === 1 ? '' : 's' }}
              already detected and will be skipped on the server.
            </span>
          </p>
        </div>

        <p v-if="apiError" class="text-sm text-destructive" role="alert">
          {{ apiError }}
        </p>
      </SheetBody>

      <SheetFooter>
        <Button
          type="button"
          variant="outline"
          @click="emit('update:open', false)"
        >
          Cancel
        </Button>
        <Button
          type="button"
          :disabled="isSubmitting || selectedScripts.size === 0"
          @click="handleSubmit"
        >
          {{ isSubmitting ? 'Starting…' : 'Start provisioning' }}
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
