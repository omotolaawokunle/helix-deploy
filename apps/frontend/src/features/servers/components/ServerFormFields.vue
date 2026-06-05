<script setup lang="ts">
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
import { Textarea } from '@/components/ui/textarea'
import type { ServerProvider } from '@/types'

interface ProviderOption {
  value: ServerProvider
  label: string
}

interface Props {
  hostname: string
  ipAddress: string
  sshPort: number
  sshUser: string
  provider: ServerProvider
  projectId?: string
  environmentId?: string
  authMethod: 'generate' | 'import'
  privateKey: string
  projects: Array<{ id: string; name: string }>
  environments: Array<{ id: string; name: string }>
  providerOptions: ProviderOption[]
}

defineProps<Props>()

const emit = defineEmits<{
  'update:hostname': [value: string]
  'update:ipAddress': [value: string]
  'update:sshPort': [value: number]
  'update:sshUser': [value: string]
  'update:provider': [value: ServerProvider]
  'update:projectId': [value: string | undefined]
  'update:environmentId': [value: string | undefined]
  'update:authMethod': [value: 'generate' | 'import']
  'update:privateKey': [value: string]
}>()
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-2">
      <Label for="hostname">Hostname</Label>
      <Input
        id="hostname"
        :model-value="hostname"
        placeholder="web-01.example.com"
        @update:model-value="emit('update:hostname', String($event))"
      />
    </div>

    <div class="space-y-2">
      <Label for="ip-address">IP address</Label>
      <Input
        id="ip-address"
        :model-value="ipAddress"
        placeholder="203.0.113.10"
        @update:model-value="emit('update:ipAddress', String($event))"
      />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="space-y-2">
        <Label for="ssh-port">SSH port</Label>
        <Input
          id="ssh-port"
          type="number"
          :model-value="String(sshPort)"
          @update:model-value="emit('update:sshPort', Number($event))"
        />
      </div>
      <div class="space-y-2">
        <Label for="ssh-user">SSH user</Label>
        <Input
          id="ssh-user"
          :model-value="sshUser"
          @update:model-value="emit('update:sshUser', String($event))"
        />
      </div>
    </div>

    <div class="space-y-2">
      <Label>Provider</Label>
      <Select
        :model-value="provider"
        @update:model-value="emit('update:provider', $event as ServerProvider)"
      >
        <SelectTrigger>
          <SelectValue placeholder="Select provider" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="option in providerOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="projects.length > 0" class="space-y-2">
      <Label>Project (optional)</Label>
      <Select
        :model-value="projectId ?? 'none'"
        @update:model-value="emit('update:projectId', $event === 'none' ? undefined : String($event))"
      >
        <SelectTrigger>
          <SelectValue placeholder="No project" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="none">
            No project
          </SelectItem>
          <SelectItem
            v-for="project in projects"
            :key="project.id"
            :value="project.id"
          >
            {{ project.name }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="environments.length > 0" class="space-y-2">
      <Label>Environment (optional)</Label>
      <Select
        :model-value="environmentId ?? 'none'"
        @update:model-value="emit('update:environmentId', $event === 'none' ? undefined : String($event))"
      >
        <SelectTrigger>
          <SelectValue placeholder="No environment" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="none">
            No environment
          </SelectItem>
          <SelectItem
            v-for="environment in environments"
            :key="environment.id"
            :value="environment.id"
          >
            {{ environment.name }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="space-y-3">
      <Label>Authentication</Label>
      <div
        class="flex flex-col gap-2"
        role="group"
        aria-label="Authentication method"
      >
        <Button
          type="button"
          class="h-auto min-h-10 w-full justify-start whitespace-normal px-3 py-2.5 text-left text-sm"
          :variant="authMethod === 'generate' ? 'default' : 'outline'"
          @click="emit('update:authMethod', 'generate')"
        >
          Generate SSH key pair
        </Button>
        <Button
          type="button"
          class="h-auto min-h-10 w-full justify-start whitespace-normal px-3 py-2.5 text-left text-sm"
          :variant="authMethod === 'import' ? 'default' : 'outline'"
          @click="emit('update:authMethod', 'import')"
        >
          Use existing private key
        </Button>
      </div>
    </div>

    <div v-if="authMethod === 'import'" class="space-y-2">
      <Label for="private-key">Private key</Label>
      <Textarea
        id="private-key"
        :model-value="privateKey"
        class="font-mono text-xs"
        :rows="6"
        placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"
        @update:model-value="emit('update:privateKey', String($event))"
      />
    </div>
  </div>
</template>
