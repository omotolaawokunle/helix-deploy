<script setup lang="ts">
import { CheckIcon, CopyIcon } from '@lucide/vue'
import { useClipboard } from '@vueuse/core'
import { ref, watch } from 'vue'
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
  tokenName: string
  plainTextToken: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  closed: []
}>()

const { copy, copied } = useClipboard()
const copiedRecently = ref(false)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      copiedRecently.value = false
      emit('closed')
    }
  },
)

async function handleCopy(): Promise<void> {
  if (props.plainTextToken === '') {
    return
  }

  await copy(props.plainTextToken)
  copiedRecently.value = true
  setTimeout(() => {
    copiedRecently.value = false
  }, 2000)
}

function handleOpenChange(value: boolean): void {
  emit('update:open', value)
}
</script>

<template>
  <Sheet :open="open" @update:open="handleOpenChange">
    <SheetContent
      side="right"
      class="flex w-full flex-col sm:max-w-lg"
      data-testid="api-token-created-sheet"
    >
      <SheetHeader>
        <SheetTitle>Token created</SheetTitle>
        <SheetDescription>
          Copy <span class="font-medium text-foreground">{{ tokenName }}</span> now.
          HelixDeploy will not show this token again.
        </SheetDescription>
      </SheetHeader>

      <SheetBody class="space-y-4">
        <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-sm text-foreground">
          Store this token in your secrets manager. Anyone with it can access the API as you.
        </div>

        <div class="rounded-lg border border-border bg-muted p-4">
          <pre
            class="overflow-x-auto whitespace-pre-wrap break-all font-mono text-xs text-foreground"
            data-testid="api-token-plaintext"
          >{{ plainTextToken }}</pre>
        </div>
      </SheetBody>

      <SheetFooter>
        <Button
          type="button"
          variant="outline"
          data-testid="api-token-copy"
          @click="handleCopy"
        >
          <CopyIcon class="mr-2 size-4" aria-hidden="true" />
          {{ copied || copiedRecently ? 'Copied!' : 'Copy token' }}
        </Button>
        <Button type="button" @click="handleOpenChange(false)">
          <CheckIcon class="mr-2 size-4" aria-hidden="true" />
          Done
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
