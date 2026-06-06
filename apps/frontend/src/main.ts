import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from '@/router'
import { setApiUnauthorizedHandler } from '@/lib/axios'
import { initThemePreference } from '@/lib/theme'
import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'

initThemePreference()

const app = createApp(App)

const guestPaths = new Set(['/login', '/register', '/verify-email', '/accept-invitation'])

setApiUnauthorizedHandler(async () => {
  const authStore = useAuthStore(pinia)
  authStore.clearAuth()

  const currentPath = router.currentRoute.value.path

  if (!guestPaths.has(currentPath)) {
    await router.push('/login')
  }
})

app.use(pinia)
app.use(router)
app.mount('#app')
