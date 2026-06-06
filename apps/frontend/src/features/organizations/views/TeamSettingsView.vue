<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import { AlertTriangleIcon } from '@lucide/vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import ApiTokensSection from '@/features/auth/components/ApiTokensSection.vue'
import { changePassword, updateProfile } from '@/features/auth/api'
import { PROFILE_TIMEZONE_OPTIONS } from '@/features/auth/constants'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

const authStore = useAuthStore()

const name = ref('')
const email = ref('')
const timezone = ref('UTC')
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const isSavingProfile = ref(false)
const isChangingPassword = ref(false)
const profileFieldErrors = ref<Record<string, string>>({})
const passwordFieldErrors = ref<Record<string, string>>({})
const emailVerificationRequired = ref(false)

const isEmailUnverified = computed(
  () => authStore.user?.emailVerifiedAt === null || authStore.user?.emailVerifiedAt === undefined,
)

function syncFormFromUser(): void {
  if (authStore.user === null) {
    return
  }

  name.value = authStore.user.name
  email.value = authStore.user.email
  timezone.value = authStore.user.timezone ?? 'UTC'
}

onMounted(() => {
  syncFormFromUser()
})

async function saveProfile(): Promise<void> {
  isSavingProfile.value = true
  profileFieldErrors.value = {}
  emailVerificationRequired.value = false

  try {
    const previousEmail = authStore.user?.email ?? ''
    await updateProfile({
      name: name.value.trim(),
      email: email.value.trim(),
      timezone: timezone.value,
    })
    await authStore.refreshUser()
    syncFormFromUser()

    if (authStore.user?.email !== previousEmail && authStore.user?.emailVerifiedAt === null) {
      emailVerificationRequired.value = true
      toast.message('Verify your new email address.', {
        description: 'We sent a verification link to your inbox.',
      })

      return
    }

    toast.success('Profile updated.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)

    if (fieldErrors !== null) {
      profileFieldErrors.value = Object.fromEntries(
        Object.entries(fieldErrors).map(([field, messages]) => [field, messages[0] ?? 'Invalid value.']),
      )
      toast.error('Unable to save profile.')
    } else {
      toast.error('Unable to save profile.')
    }
  } finally {
    isSavingProfile.value = false
  }
}

async function handleChangePassword(): Promise<void> {
  passwordFieldErrors.value = {}

  if (newPassword.value !== confirmPassword.value) {
    passwordFieldErrors.value.confirmPassword = 'Passwords do not match.'

    return
  }

  isChangingPassword.value = true

  try {
    await changePassword({
      currentPassword: currentPassword.value,
      password: newPassword.value,
      passwordConfirmation: confirmPassword.value,
    })
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    toast.success('Password changed.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)

    if (fieldErrors !== null) {
      passwordFieldErrors.value = {
        currentPassword: firstFieldError(fieldErrors, 'currentPassword') ?? '',
        password: firstFieldError(fieldErrors, 'password') ?? '',
      }
    }

    toast.error('Unable to change password.')
  } finally {
    isChangingPassword.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Profile settings"
      description="Your profile, API tokens, and password."
    />

    <Alert
      v-if="isEmailUnverified"
      variant="destructive"
      data-testid="email-verification-required"
    >
      <AlertTriangleIcon aria-hidden="true" />
      <AlertTitle>Email verification required</AlertTitle>
      <AlertDescription>
        Verify your email to access organization resources.
        <RouterLink to="/verify-email" class="ml-1 font-medium underline underline-offset-2">
          Open verification page
        </RouterLink>
      </AlertDescription>
    </Alert>

    <Alert
      v-if="emailVerificationRequired"
      data-testid="email-change-verification-sent"
    >
      <AlertTitle>Check your inbox</AlertTitle>
      <AlertDescription>
        Your email was updated. Confirm the new address before your next deployment.
      </AlertDescription>
    </Alert>

    <form
      class="panel max-w-lg space-y-4 p-6"
      data-testid="profile-form"
      @submit.prevent="saveProfile"
    >
      <h2 class="section-label">
        Profile
      </h2>
      <div class="space-y-2">
        <Label for="profile-name">Name</Label>
        <Input id="profile-name" v-model="name" autocomplete="name" />
        <p v-if="profileFieldErrors.name !== undefined" class="text-sm text-destructive">
          {{ profileFieldErrors.name }}
        </p>
      </div>
      <div class="space-y-2">
        <Label for="profile-email">Email</Label>
        <Input id="profile-email" v-model="email" type="email" autocomplete="email" />
        <p v-if="profileFieldErrors.email !== undefined" class="text-sm text-destructive">
          {{ profileFieldErrors.email }}
        </p>
      </div>
      <div class="space-y-2">
        <Label for="profile-timezone">Timezone</Label>
        <Select v-model="timezone">
          <SelectTrigger id="profile-timezone">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in PROFILE_TIMEZONE_OPTIONS"
              :key="option"
              :value="option"
            >
              {{ option }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="profileFieldErrors.timezone !== undefined" class="text-sm text-destructive">
          {{ profileFieldErrors.timezone }}
        </p>
      </div>
      <Button type="submit" :disabled="isSavingProfile">
        {{ isSavingProfile ? 'Saving…' : 'Save profile' }}
      </Button>
    </form>

    <form
      class="panel max-w-lg space-y-4 p-6"
      data-testid="password-form"
      @submit.prevent="handleChangePassword"
    >
      <h2 class="section-label">
        Password
      </h2>
      <div class="space-y-2">
        <Label for="current-password">Current password</Label>
        <Input
          id="current-password"
          v-model="currentPassword"
          type="password"
          autocomplete="current-password"
        />
        <p v-if="passwordFieldErrors.currentPassword !== ''" class="text-sm text-destructive">
          {{ passwordFieldErrors.currentPassword }}
        </p>
      </div>
      <div class="space-y-2">
        <Label for="new-password">New password</Label>
        <Input
          id="new-password"
          v-model="newPassword"
          type="password"
          autocomplete="new-password"
        />
        <p v-if="passwordFieldErrors.password !== ''" class="text-sm text-destructive">
          {{ passwordFieldErrors.password }}
        </p>
      </div>
      <div class="space-y-2">
        <Label for="confirm-password">Confirm new password</Label>
        <Input
          id="confirm-password"
          v-model="confirmPassword"
          type="password"
          autocomplete="new-password"
        />
        <p v-if="passwordFieldErrors.confirmPassword !== undefined" class="text-sm text-destructive">
          {{ passwordFieldErrors.confirmPassword }}
        </p>
      </div>
      <Button
        type="submit"
        variant="outline"
        :disabled="isChangingPassword"
      >
        {{ isChangingPassword ? 'Updating…' : 'Change password' }}
      </Button>
    </form>

    <ApiTokensSection />
  </div>
</template>
