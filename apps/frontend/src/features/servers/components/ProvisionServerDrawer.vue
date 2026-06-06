<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
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
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const router = useRouter()
const { orgId } = useActiveOrg()

const templates = ref<ProvisioningTemplateRecord[]>([])
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

function toggleScript(script: ProvisioningScript): void {
  const next = new Set(selectedScripts.value)

  if (next.has(script)) {
    next.delete(script)
  } else {
    next.add(script)
  }

  selectedScripts.value = next
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      void loadTemplates()
    }
  },
)

async function loadTemplates(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    templates.value = []

    return
  }

  isLoadingTemplates.value = true

  try {
    templates.value = await fetchProvisioningTemplates(activeOrgId)
  } catch {
    templates.value = []
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
  } catch {
    apiError.value = 'Unable to start provisioning. Please try again.'
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
          Select services to install on this server.
        </SheetDescription>
      </SheetHeader>

      <SheetBody class="space-y-6">
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
              class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-3 text-sm text-foreground transition-colors hover:bg-muted/50"
              :class="selectedScripts.has(script) ? 'border-primary bg-primary/5' : ''"
            >
              <input
                type="checkbox"
                class="size-4 rounded border-input"
                :checked="selectedScripts.has(script)"
                @change="toggleScript(script)"
              >
              {{ scriptLabels[script] }}
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

        <p class="text-sm text-muted-foreground">
          Estimated time: ~{{ estimatedMinutes }} min
        </p>

        <p v-if="apiError" class="text-sm text-destructive">
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
