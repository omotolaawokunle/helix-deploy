<script setup lang="ts">
import { HexagonIcon } from '@lucide/vue'
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
    <aside
      class="relative hidden flex-col justify-between border-r bg-sidebar p-10 lg:flex"
      aria-hidden="true"
    >
      <div>
        <div class="flex items-center gap-2.5">
          <HexagonIcon class="size-7 text-primary" />
          <span class="text-xl font-semibold tracking-tight">HelixDeploy</span>
        </div>
        <p class="mt-8 max-w-sm text-sm leading-relaxed text-muted-foreground">
          Self-hosted deployment and infrastructure orchestration for teams who need full control.
        </p>
      </div>
      <p class="text-xs text-muted-foreground">
        Open source · Multi-tenant · Queue-first execution
      </p>
    </aside>

    <main class="flex flex-col justify-center px-4 py-10 sm:px-8 lg:px-12">
      <div class="mb-8 flex items-center gap-2.5 lg:hidden">
        <HexagonIcon class="size-7 text-primary" />
        <span class="text-xl font-semibold tracking-tight">HelixDeploy</span>
      </div>

      <div class="mx-auto w-full max-w-md">
        <slot />
      </div>
    </main>
  </div>
</template>
