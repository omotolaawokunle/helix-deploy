<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
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
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { useLoginForm } from '@/features/auth/composables/useLoginForm'

const route = useRoute()
const { authStore, apiError, onSubmit } = useLoginForm()

const showResetSuccess = computed((): boolean => route.query.reset === '1')
</script>

<template>
  <AuthLayout>
    <Card class="border shadow-sm">
      <CardHeader>
        <CardTitle>Sign in</CardTitle>
        <CardDescription>Enter your credentials to access your account.</CardDescription>
      </CardHeader>

      <CardContent>
        <p
          v-if="showResetSuccess"
          class="mb-4 text-sm text-muted-foreground"
          data-testid="password-reset-success"
        >
          Password updated. Sign in with your new password.
        </p>

        <form class="space-y-4" data-testid="login-form" @submit="onSubmit">
          <FormField v-slot="{ componentField }" name="email">
            <FormItem>
              <FormLabel>Email</FormLabel>
              <FormControl>
                <Input
                  v-bind="componentField"
                  type="email"
                  autocomplete="email"
                  data-testid="login-email"
                />
              </FormControl>
              <FormMessage data-testid="login-email-error" />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="password">
            <FormItem>
              <div class="flex items-center justify-between gap-2">
                <FormLabel>Password</FormLabel>
                <RouterLink
                  to="/forgot-password"
                  class="text-xs font-medium text-primary hover:underline"
                  data-testid="forgot-password-link"
                >
                  Forgot password?
                </RouterLink>
              </div>
              <FormControl>
                <Input
                  v-bind="componentField"
                  type="password"
                  autocomplete="current-password"
                  data-testid="login-password"
                />
              </FormControl>
              <FormMessage data-testid="login-password-error" />
            </FormItem>
          </FormField>

          <p v-if="apiError" class="text-sm text-destructive" data-testid="login-api-error">
            {{ apiError }}
          </p>

          <Button
            type="submit"
            class="w-full"
            data-testid="login-submit"
            :disabled="authStore.isLoading"
          >
            {{ authStore.isLoading ? 'Signing in…' : 'Sign in' }}
          </Button>
        </form>

        <p class="mt-4 text-center text-sm text-muted-foreground">
          Don't have an account?
          <RouterLink to="/register" class="font-medium text-primary hover:underline">
            Register
          </RouterLink>
        </p>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
