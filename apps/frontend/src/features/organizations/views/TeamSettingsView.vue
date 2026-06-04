<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/layout/PageHeader.vue'
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
import { useAuthStore } from '@/features/auth/stores/useAuthStore'

const authStore = useAuthStore()

const name = ref('')
const email = ref('')
const timezone = ref('UTC')
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')

onMounted(() => {
  if (authStore.user !== null) {
    name.value = authStore.user.name
    email.value = authStore.user.email
    timezone.value = authStore.user.timezone ?? 'UTC'
  }
})

function saveProfile(): void {
  toast.message('Profile updates will be available in a future release.')
}

function changePassword(): void {
  if (newPassword.value !== confirmPassword.value) {
    toast.error('Passwords do not match.')

    return
  }

  toast.message('Password change will be available in a future release.')
}
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Team settings"
      description="Your personal profile and preferences."
    />

    <form class="panel max-w-lg space-y-4 p-6" @submit.prevent="saveProfile">
      <h2 class="section-label">
        Profile
      </h2>
      <div class="space-y-2">
        <Label for="profile-name">Name</Label>
        <Input id="profile-name" v-model="name" />
      </div>
      <div class="space-y-2">
        <Label for="profile-email">Email</Label>
        <Input id="profile-email" v-model="email" type="email" />
      </div>
      <div class="space-y-2">
        <Label>Timezone</Label>
        <Select v-model="timezone">
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="UTC">
              UTC
            </SelectItem>
            <SelectItem value="America/New_York">
              America/New_York
            </SelectItem>
            <SelectItem value="Europe/London">
              Europe/London
            </SelectItem>
            <SelectItem value="Africa/Lagos">
              Africa/Lagos
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <Button type="submit">
        Save profile
      </Button>
    </form>

    <form class="panel max-w-lg space-y-4 p-6" @submit.prevent="changePassword">
      <h2 class="section-label">
        Password
      </h2>
      <div class="space-y-2">
        <Label for="current-password">Current password</Label>
        <Input id="current-password" v-model="currentPassword" type="password" autocomplete="current-password" />
      </div>
      <div class="space-y-2">
        <Label for="new-password">New password</Label>
        <Input id="new-password" v-model="newPassword" type="password" autocomplete="new-password" />
      </div>
      <div class="space-y-2">
        <Label for="confirm-password">Confirm new password</Label>
        <Input id="confirm-password" v-model="confirmPassword" type="password" autocomplete="new-password" />
      </div>
      <Button type="submit" variant="outline">
        Change password
      </Button>
    </form>
  </div>
</template>
