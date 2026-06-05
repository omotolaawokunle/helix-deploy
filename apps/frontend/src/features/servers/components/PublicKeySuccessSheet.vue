<script setup lang="ts">
import { CheckIcon, CopyIcon } from '@lucide/vue'
import { useClipboard } from '@vueuse/core'
import { computed, ref } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

interface Props {
  open: boolean
  publicKey: string
  sshUser?: string
  title?: string
  description?: string
}

const props = withDefaults(defineProps<Props>(), {
  sshUser: 'deploy',
  title: 'Server registered',
})

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { copy, copied } = useClipboard()
const copiedRecently = ref(false)

const authorizedKeysPath = computed(
  (): string => `/home/${props.sshUser}/.ssh/authorized_keys`,
)

const displayDescription = computed((): string => {
  if (props.description !== undefined) {
    return props.description
  }

  return `Add this public key for user ${props.sshUser} on the server. HelixDeploy will verify the connection automatically once the key is in place.`
})

async function handleCopy(publicKey: string): Promise<void> {
  await copy(publicKey)
  copiedRecently.value = true
  setTimeout(() => {
    copiedRecently.value = false
  }, 2000)
}
</script>

<template>
  <Sheet :open="open" @update:open="emit('update:open', $event)">
    <SheetContent
      side="right"
      class="flex w-full flex-col sm:max-w-lg"
      data-testid="public-key-success-sheet"
    >
      <SheetHeader>
        <SheetTitle>{{ props.title }}</SheetTitle>
        <SheetDescription>
          {{ displayDescription }}
        </SheetDescription>
      </SheetHeader>

      <SheetBody class="space-y-5">
        <div class="rounded-lg border border-border bg-muted p-4">
          <pre
            class="overflow-x-auto whitespace-pre-wrap break-all font-mono text-xs text-foreground"
            data-testid="public-key-value"
          >{{ publicKey }}</pre>
        </div>

        <div class="space-y-2">
          <h3 class="text-sm font-medium text-foreground">
            Add the key on your server
          </h3>
          <ol class="list-decimal space-y-2 pl-4 text-sm text-muted-foreground">
            <li>
              SSH into the server as a user that can write to
              <span class="font-mono text-foreground">{{ authorizedKeysPath }}</span>.
            </li>
            <li>
              Append the public key above to that file on its own line.
            </li>
            <li>
              Ensure permissions are correct:
              <span class="font-mono text-foreground">chmod 700 ~/.ssh</span>
              and
              <span class="font-mono text-foreground">chmod 600 ~/.ssh/authorized_keys</span>.
            </li>
          </ol>
        </div>
      </SheetBody>

      <SheetFooter>
        <Button
          type="button"
          variant="outline"
          @click="handleCopy(publicKey)"
        >
          <CopyIcon class="mr-2 size-4" />
          {{ copied || copiedRecently ? 'Copied!' : 'Copy public key' }}
        </Button>
        <Button type="button" @click="emit('update:open', false)">
          <CheckIcon class="mr-2 size-4" />
          Done
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
