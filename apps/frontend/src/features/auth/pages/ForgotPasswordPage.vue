<script setup lang="ts">
import { RouterLink } from 'vue-router'
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
import { useForgotPasswordForm } from '@/features/auth/composables/useForgotPasswordForm'

const { isSubmitting, submitted, apiError, onSubmit } = useForgotPasswordForm()
</script>

<template>
  <AuthLayout>
    <Card class="border shadow-sm">
      <CardHeader>
        <CardTitle>Forgot password</CardTitle>
        <CardDescription>
          Enter your email and we will send a reset link if an account exists.
        </CardDescription>
      </CardHeader>

      <CardContent>
        <div v-if="submitted" class="space-y-4" data-testid="forgot-password-success">
          <p class="text-sm text-muted-foreground">
            If an account exists for that email, a password reset link is on the way.
            Check your inbox and spam folder.
          </p>

          <RouterLink to="/login" class="block text-sm font-medium text-primary hover:underline">
            Back to sign in
          </RouterLink>
        </div>

        <form v-else class="space-y-4" data-testid="forgot-password-form" @submit="onSubmit">
          <FormField v-slot="{ componentField }" name="email">
            <FormItem>
              <FormLabel>Email</FormLabel>
              <FormControl>
                <Input
                  v-bind="componentField"
                  type="email"
                  autocomplete="email"
                  data-testid="forgot-password-email"
                />
              </FormControl>
              <FormMessage data-testid="forgot-password-email-error" />
            </FormItem>
          </FormField>

          <p v-if="apiError" class="text-sm text-destructive" data-testid="forgot-password-api-error">
            {{ apiError }}
          </p>

          <Button
            type="submit"
            class="w-full"
            data-testid="forgot-password-submit"
            :disabled="isSubmitting"
          >
            {{ isSubmitting ? 'Sending…' : 'Send reset link' }}
          </Button>
        </form>

        <p v-if="!submitted" class="mt-4 text-center text-sm text-muted-foreground">
          <RouterLink to="/login" class="font-medium text-primary hover:underline">
            Back to sign in
          </RouterLink>
        </p>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
