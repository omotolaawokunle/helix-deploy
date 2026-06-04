<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'

const authStore = useAuthStore()
const router = useRouter()
const isResending = ref(false)
const resendMessage = ref<string | null>(null)

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    await authStore.init()
  }

  if (authStore.user?.emailVerifiedAt !== null && authStore.user?.emailVerifiedAt !== undefined) {
    await router.replace('/dashboard')
  }
})

async function handleResend(): Promise<void> {
  isResending.value = true
  resendMessage.value = null

  try {
    await authStore.resendVerification()
    resendMessage.value = 'Verification email sent.'
  } catch {
    resendMessage.value = 'Unable to resend verification email.'
  } finally {
    isResending.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <Card class="border shadow-sm">
      <CardHeader>
        <CardTitle>Verify your email</CardTitle>
        <CardDescription>
          Check your inbox — click the link to verify your email before accessing HelixDeploy.
        </CardDescription>
      </CardHeader>

      <CardContent class="space-y-4">
        <p class="text-sm text-muted-foreground">
          We sent a verification link to
          <span class="font-medium text-foreground">{{ authStore.user?.email ?? 'your email' }}</span>.
        </p>

        <Button
          type="button"
          variant="outline"
          data-testid="resend-verification"
          :disabled="isResending"
          @click="handleResend"
        >
          {{ isResending ? 'Sending…' : 'Resend verification' }}
        </Button>

        <p v-if="resendMessage" class="text-sm text-muted-foreground">
          {{ resendMessage }}
        </p>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
