<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { FileQuestionIcon } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'

const authStore = useAuthStore()

const homeRoute = computed(() =>
  authStore.isAuthenticated && authStore.isEmailVerified ? '/dashboard' : '/login',
)

const homeLabel = computed(() =>
  authStore.isAuthenticated && authStore.isEmailVerified ? 'Go to dashboard' : 'Go to sign in',
)
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-background px-4">
    <div class="w-full max-w-md space-y-6 text-center">
      <div class="mx-auto flex size-12 items-center justify-center rounded-lg bg-muted text-muted-foreground">
        <FileQuestionIcon class="size-6" />
      </div>

      <div class="space-y-2">
        <h1 class="page-title">
          Page not found
        </h1>
        <p class="text-sm text-muted-foreground">
          The page you requested does not exist or may have been moved.
        </p>
      </div>

      <Button as-child>
        <RouterLink :to="homeRoute">
          {{ homeLabel }}
        </RouterLink>
      </Button>
    </div>
  </div>
</template>
