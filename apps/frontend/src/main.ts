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

setApiUnauthorizedHandler(async () => {
  const authStore = useAuthStore(pinia)
  authStore.clearAuth()

  if (router.currentRoute.value.path !== '/login') {
    await router.push('/login')
  }
})

app.use(pinia)
app.use(router)
app.mount('#app')
