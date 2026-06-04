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
import { useRegisterForm } from '@/features/auth/composables/useRegisterForm'

const {
  authStore,
  showVerificationNotice,
  isResending,
  resendMessage,
  registeredEmail,
  onSubmit,
  handleResend,
} = useRegisterForm()
</script>

<template>
  <AuthLayout>
    <Card class="border shadow-sm">
      <CardHeader>
        <CardTitle>Create account</CardTitle>
        <CardDescription>Register to start deploying with HelixDeploy.</CardDescription>
      </CardHeader>

      <CardContent>
        <div
          v-if="showVerificationNotice"
          class="space-y-4"
          data-testid="verification-notice"
        >
          <p class="text-sm text-muted-foreground">
            Check your inbox — click the link to verify your email
            <span v-if="registeredEmail" class="font-medium text-foreground">
              ({{ registeredEmail }})
            </span>.
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

          <RouterLink to="/login" class="block text-sm font-medium text-primary hover:underline">
            Back to sign in
          </RouterLink>
        </div>

        <form v-else class="space-y-4" data-testid="register-form" @submit="onSubmit">
          <FormField v-slot="{ componentField }" name="name">
            <FormItem>
              <FormLabel>Name</FormLabel>
              <FormControl>
                <Input v-bind="componentField" autocomplete="name" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="email">
            <FormItem>
              <FormLabel>Email</FormLabel>
              <FormControl>
                <Input v-bind="componentField" type="email" autocomplete="email" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="password">
            <FormItem>
              <FormLabel>Password</FormLabel>
              <FormControl>
                <Input v-bind="componentField" type="password" autocomplete="new-password" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="password_confirmation">
            <FormItem>
              <FormLabel>Confirm password</FormLabel>
              <FormControl>
                <Input v-bind="componentField" type="password" autocomplete="new-password" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <Button type="submit" class="w-full" :disabled="authStore.isLoading">
            {{ authStore.isLoading ? 'Creating account…' : 'Create account' }}
          </Button>
        </form>

        <p v-if="!showVerificationNotice" class="mt-4 text-center text-sm text-muted-foreground">
          Already have an account?
          <RouterLink to="/login" class="font-medium text-primary hover:underline">
            Sign in
          </RouterLink>
        </p>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
