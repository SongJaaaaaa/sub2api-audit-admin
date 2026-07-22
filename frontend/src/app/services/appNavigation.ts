import type { RouteLocationNormalizedLoaded, RouteLocationRaw } from 'vue-router'

export function hasAppBackEntry() {
  return typeof window.history.state?.back === 'string' && window.history.state.back !== ''
}

export function appParentRoute(route: RouteLocationNormalizedLoaded): RouteLocationRaw {
  const name = route.meta.backTo
  if (typeof name !== 'string') return { name: 'app-home' }

  const keys = Array.isArray(route.meta.backParams) ? route.meta.backParams : []
  const params = Object.fromEntries(
    keys.map(key => [String(key), route.params[String(key)]]),
  )

  return { name, params }
}
