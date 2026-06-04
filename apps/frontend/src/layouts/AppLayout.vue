<script setup lang="ts">
import { ref } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { MenuIcon } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet'
import AppSidebar from '@/components/layout/AppSidebar.vue'

const isMobileNavOpen = ref(false)
const route = useRoute()
</script>

<template>
  <div class="flex min-h-screen bg-background">
    <aside class="hidden w-64 shrink-0 border-r border-sidebar-border bg-sidebar lg:block">
      <AppSidebar />
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-4 lg:px-6">
        <Sheet v-model:open="isMobileNavOpen">
          <SheetTrigger as-child>
            <Button variant="outline" size="icon" class="lg:hidden">
              <MenuIcon class="size-4" />
              <span class="sr-only">Open navigation</span>
            </Button>
          </SheetTrigger>
          <SheetContent side="left" class="w-64 border-sidebar-border bg-sidebar p-0">
            <AppSidebar />
          </SheetContent>
        </Sheet>

        <p class="truncate text-sm text-muted-foreground lg:hidden">
          {{ route.meta.title ?? 'HelixDeploy' }}
        </p>

        <div class="flex-1" />
      </header>

      <main class="flex-1 overflow-y-auto px-4 py-6 lg:px-8 lg:py-8">
        <div class="mx-auto w-full max-w-6xl">
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>
