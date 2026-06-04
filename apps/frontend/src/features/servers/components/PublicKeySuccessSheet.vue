<script setup lang="ts">
import { CheckIcon, CopyIcon } from '@lucide/vue'
import { useClipboard } from '@vueuse/core'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

interface Props {
  open: boolean
  publicKey: string
}

defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { copy, copied } = useClipboard()
const copiedRecently = ref(false)

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
    <SheetContent side="right" class="w-full sm:max-w-lg">
      <SheetHeader>
        <SheetTitle>Server registered</SheetTitle>
        <SheetDescription>
          Your server has been registered. To complete setup, add this SSH public key
          to your server's <code class="text-xs">authorized_keys</code> file:
        </SheetDescription>
      </SheetHeader>

      <div class="my-4 rounded-lg bg-muted p-4">
        <pre class="overflow-x-auto whitespace-pre-wrap break-all font-mono text-xs">{{ publicKey }}</pre>
      </div>

      <p class="text-sm text-muted-foreground">
        HelixDeploy will automatically verify the connection once the key is added.
      </p>

      <SheetFooter class="mt-6">
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
