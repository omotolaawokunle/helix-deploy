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
import { Input } from '@/components/ui/input'
import {
  fetchProvisioningServiceVersions,
  fetchProvisioningTemplates,
} from '@/features/provisioning/api'
import type {
  ProvisioningServiceVersionCatalog,
  ProvisioningTemplateRecord,
  ServiceVersionDefinition,
} from '@/features/provisioning/types'
import { formatProvisioningTemplateName } from '@/features/provisioning/constants'
import {
  provisionServer,
} from '@/features/servers/api'
import type { ProvisionServerPayload } from '@/features/servers/types'
import {
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
const versionCatalog = ref<ProvisioningServiceVersionCatalog>({})
const templatesLoadedForOrgId = ref<string | null>(null)
const isLoadingTemplates = ref(false)
const isLoadingVersions = ref(false)
const selectedScripts = ref<Set<ProvisioningScript>>(new Set())
const versionSelections = ref<Record<string, string>>({})
const redisPassword = ref('')
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

const activeVersionDefinitions = computed((): ServiceVersionDefinition[] => {
  return [...selectedScripts.value]
    .map((script) => versionCatalog.value[script])
    .filter((definition): definition is ServiceVersionDefinition => definition !== undefined)
})

const showRedisPassword = computed(() => selectedScripts.value.has('redis'))

const detectedServiceLabels = computed((): string[] =>
  props.detectedServices.map((service) => scriptLabels[service as ProvisioningScript] ?? service),
)

const skippedSelectedCount = computed((): number =>
  [...selectedScripts.value].filter((script) => props.detectedServices.includes(script)).length,
)

function ensureVersionDefault(definition: ServiceVersionDefinition): void {
  if (versionSelections.value[definition.optionKey] !== undefined) {
    return
  }

  versionSelections.value = {
    ...versionSelections.value,
    [definition.optionKey]: String(definition.default),
  }
}

function toggleScript(script: ProvisioningScript): void {
  const next = new Set(selectedScripts.value)

  if (next.has(script)) {
    next.delete(script)
  } else {
    next.add(script)
    const definition = versionCatalog.value[script]

    if (definition !== undefined) {
      ensureVersionDefault(definition)
    }
  }

  selectedScripts.value = next
}

function resetDrawerState(): void {
  selectedScripts.value = new Set()
  versionSelections.value = {}
  redisPassword.value = ''
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
    void ensureVersionsLoaded()
  },
)

async function ensureVersionsLoaded(): Promise<void> {
  if (Object.keys(versionCatalog.value).length > 0) {
    return
  }

  isLoadingVersions.value = true

  try {
    versionCatalog.value = await fetchProvisioningServiceVersions()
  } catch {
    versionCatalog.value = {}
  } finally {
    isLoadingVersions.value = false
  }
}

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

  const nextSelections: Record<string, string> = {}

  for (const script of selectedScripts.value) {
    const definition = versionCatalog.value[script]

    if (definition === undefined) {
      continue
    }

    const templateValue = template.options[definition.optionKey]

    nextSelections[definition.optionKey] = templateValue !== undefined && templateValue !== null
      ? String(templateValue)
      : String(definition.default)
  }

  versionSelections.value = nextSelections

  const templateRedisPassword = template.options.redisPassword

  redisPassword.value = typeof templateRedisPassword === 'string' ? templateRedisPassword : ''
}

function updateVersionSelection(optionKey: string, value: unknown): void {
  versionSelections.value = {
    ...versionSelections.value,
    [optionKey]: String(value),
  }
}

function buildProvisionOptions(): ProvisionServerPayload['options'] {
  const options: NonNullable<ProvisionServerPayload['options']> = {}

  for (const definition of activeVersionDefinitions.value) {
    const value = versionSelections.value[definition.optionKey] ?? String(definition.default)

    if (definition.optionKey === 'nodeVersion') {
      options.nodeVersion = Number(value)
    } else if (definition.optionKey === 'phpVersion') {
      options.phpVersion = value
    } else if (definition.optionKey === 'postgresqlVersion') {
      options.postgresqlVersion = value
    } else if (definition.optionKey === 'mysqlVersion') {
      options.mysqlVersion = value
    } else if (definition.optionKey === 'pythonVersion') {
      options.pythonVersion = value
    }
  }

  if (showRedisPassword.value && redisPassword.value.trim() !== '') {
    options.redisPassword = redisPassword.value.trim()
  }

  return options
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
      options: buildProvisionOptions(),
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

        <div
          v-for="definition in activeVersionDefinitions"
          :key="definition.optionKey"
          class="space-y-2"
        >
          <Label>{{ definition.label }} version</Label>
          <Select
            :model-value="versionSelections[definition.optionKey] ?? String(definition.default)"
            :disabled="isLoadingVersions"
            @update:model-value="(value) => updateVersionSelection(definition.optionKey, value)"
          >
            <SelectTrigger>
              <SelectValue :placeholder="`Select ${definition.label} version`" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="version in definition.values"
                :key="String(version)"
                :value="String(version)"
              >
                {{ definition.label }} {{ version }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div v-if="showRedisPassword" class="space-y-2">
          <Label for="redis-password">Redis password (optional)</Label>
          <Input
            id="redis-password"
            v-model="redisPassword"
            type="password"
            autocomplete="new-password"
            placeholder="Auto-generated if left blank"
          />
          <p class="text-xs text-muted-foreground">
            Minimum 8 characters when provided. Stored as a server credential.
          </p>
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
