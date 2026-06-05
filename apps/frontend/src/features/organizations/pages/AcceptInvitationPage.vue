<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  acceptOrganizationInvitation,
  type AcceptInvitationParams,
} from '@/features/organizations/api'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

const status = ref<'loading' | 'success' | 'error' | 'auth-required'>('loading')
const errorMessage = ref<string | null>(null)
const organizationName = ref<string | null>(null)

const invitationParams = computed((): AcceptInvitationParams | null => {
  const { token, expires, signature } = route.query

  if (
    typeof token !== 'string'
    || token === ''
    || typeof expires !== 'string'
    || expires === ''
    || typeof signature !== 'string'
    || signature === ''
  ) {
    return null
  }

  return { token, expires, signature }
})

async function acceptInvitation(): Promise<void> {
  if (invitationParams.value === null) {
    status.value = 'error'
    errorMessage.value = 'Invitation link is missing a valid token or has expired.'

    return
  }

  try {
    const result = await acceptOrganizationInvitation(invitationParams.value)
    organizationName.value = result.organizationName
    await authStore.loadOrganizations()
    await authStore.resolveCurrentRole()
    status.value = 'success'
    toast.success(`You joined ${result.organizationName}.`)
  } catch {
    status.value = 'error'
    errorMessage.value = 'Unable to accept invitation. The link may be invalid, expired, or sent to a different email address.'
  }
}

onMounted(async () => {
  if (!authStore.isInitialized) {
    await authStore.init()
  }

  if (!authStore.isAuthenticated) {
    status.value = 'auth-required'

    return
  }

  if (!authStore.isEmailVerified) {
    await router.replace('/verify-email')

    return
  }

  await acceptInvitation()
})

async function goToLogin(): Promise<void> {
  await router.push({
    path: '/login',
    query: { redirect: route.fullPath },
  })
}

async function goToRegister(): Promise<void> {
  await router.push({
    path: '/register',
    query: { redirect: route.fullPath },
  })
}

async function goToDashboard(): Promise<void> {
  await router.push('/dashboard')
}
</script>

<template>
  <AuthLayout>
    <Card class="border shadow-sm">
      <CardHeader>
        <CardTitle>Organization invitation</CardTitle>
        <CardDescription>
          Accept your invitation to join a team on HelixDeploy.
        </CardDescription>
      </CardHeader>

      <CardContent class="space-y-4">
        <p v-if="status === 'loading'" class="text-sm text-muted-foreground">
          Accepting your invitation…
        </p>

        <template v-else-if="status === 'success'">
          <p class="text-sm text-muted-foreground">
            You are now a member of
            <span class="font-medium text-foreground">{{ organizationName }}</span>.
          </p>
          <Button type="button" @click="goToDashboard">
            Go to dashboard
          </Button>
        </template>

        <template v-else-if="status === 'auth-required'">
          <p class="text-sm text-muted-foreground">
            Sign in or create an account with the invited email address to accept this invitation.
          </p>
          <div class="flex flex-wrap gap-2">
            <Button type="button" @click="goToLogin">
              Sign in
            </Button>
            <Button type="button" variant="outline" @click="goToRegister">
              Create account
            </Button>
          </div>
        </template>

        <template v-else>
          <p class="text-sm text-destructive">
            {{ errorMessage }}
          </p>
          <Button type="button" variant="outline" @click="goToDashboard">
            Back to dashboard
          </Button>
        </template>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
