import { useRouter } from 'vue-router'

const prefetchedPaths = new Set<string>()

export function useRoutePrefetch(): { prefetchRoute: (path: string) => void } {
  const router = useRouter()

  function prefetchRoute(path: string): void {
    if (prefetchedPaths.has(path)) {
      return
    }

    prefetchedPaths.add(path)

    const resolved = router.resolve(path)

    for (const record of resolved.matched) {
      const component = record.components?.default

      if (typeof component === 'function' && component.length === 0) {
        void (component as () => Promise<unknown>)()
      }
    }
  }

  return { prefetchRoute }
}
