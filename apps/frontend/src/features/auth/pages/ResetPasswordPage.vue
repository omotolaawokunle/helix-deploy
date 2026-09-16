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
import { useResetPasswordForm } from '@/features/auth/composables/useResetPasswordForm'

const { isSubmitting, apiError, hasValidLink, onSubmit } = useResetPasswordForm()
</script>

<template>
  <AuthLayout>
    <Card class="border shadow-sm">
      <CardHeader>
        <CardTitle>Reset password</CardTitle>
        <CardDescription>
          Choose a new password for your account.
        </CardDescription>
      </CardHeader>

      <CardContent>
        <div v-if="!hasValidLink" class="space-y-4" data-testid="reset-password-invalid">
          <p class="text-sm text-destructive">
            This reset link is invalid or incomplete. Request a new one from the forgot password page.
          </p>

          <RouterLink
            to="/forgot-password"
            class="block text-sm font-medium text-primary hover:underline"
          >
            Request a new link
          </RouterLink>
        </div>

        <form
          v-else
          class="space-y-4"
          data-testid="reset-password-form"
          @submit="onSubmit"
        >
          <FormField v-slot="{ componentField }" name="password">
            <FormItem>
              <FormLabel>New password</FormLabel>
              <FormControl>
                <Input
                  v-bind="componentField"
                  type="password"
                  autocomplete="new-password"
                  data-testid="reset-password-password"
                />
              </FormControl>
              <FormMessage data-testid="reset-password-password-error" />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="passwordConfirmation">
            <FormItem>
              <FormLabel>Confirm password</FormLabel>
              <FormControl>
                <Input
                  v-bind="componentField"
                  type="password"
                  autocomplete="new-password"
                  data-testid="reset-password-confirmation"
                />
              </FormControl>
              <FormMessage data-testid="reset-password-confirmation-error" />
            </FormItem>
          </FormField>

          <p v-if="apiError" class="text-sm text-destructive" data-testid="reset-password-api-error">
            {{ apiError }}
          </p>

          <Button
            type="submit"
            class="w-full"
            data-testid="reset-password-submit"
            :disabled="isSubmitting"
          >
            {{ isSubmitting ? 'Saving…' : 'Reset password' }}
          </Button>
        </form>

        <p class="mt-4 text-center text-sm text-muted-foreground">
          <RouterLink to="/login" class="font-medium text-primary hover:underline">
            Back to sign in
          </RouterLink>
        </p>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
