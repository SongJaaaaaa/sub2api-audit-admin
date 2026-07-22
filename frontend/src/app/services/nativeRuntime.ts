import { App as NativeApp } from '@capacitor/app'
import { Browser } from '@capacitor/browser'
import { Keyboard, KeyboardResize } from '@capacitor/keyboard'
import { Network } from '@capacitor/network'
import { StatusBar, Style } from '@capacitor/status-bar'
import type { Router } from 'vue-router'
import { ref } from 'vue'
import { appParentRoute, hasAppBackEntry } from './appNavigation'
import { isAppMode, isNativeApp } from './platform'

export const networkOnline = ref(true)
let initialized = false
let lastBackAt = 0

export async function syncNativeTheme(dark: boolean) {
  if (!isNativeApp) return
  try {
    await StatusBar.setStyle({ style: dark ? Style.Light : Style.Dark })
    await StatusBar.setBackgroundColor({ color: dark ? '#111827' : '#f7f9fc' })
  } catch {
    // Status bar support differs between Android versions and iOS overlays.
  }
}

function closeOpenLayer() {
  const appLayer = document.querySelector('.appDetailOverlay')
  if (appLayer) {
    const close = appLayer.querySelector<HTMLElement>('[data-app-close]')
    close?.click()
    return true
  }
  const filterSheet = document.querySelector<HTMLElement>('.appSheetBackdrop')
  if (filterSheet) {
    const close = filterSheet.querySelector<HTMLElement>('[aria-label="关闭筛选"]')
    if (close) close.click()
    else filterSheet.click()
    return true
  }
  const inlineFilter = document.querySelector<HTMLElement>('.app-filter-sheet')
  if (inlineFilter) {
    inlineFilter.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', bubbles: true }))
    return true
  }
  const layer = document.querySelector<HTMLElement>('.ant-modal-wrap:not([style*="display: none"]), .ant-drawer-open, .n-modal-container, .n-drawer-container')
  if (layer) {
    const close = layer.querySelector<HTMLElement>('.ant-modal-close, .ant-drawer-close, [aria-label="关闭"], [aria-label="Close"]')
    if (close) {
      close.click()
      return true
    }
    layer.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', bubbles: true }))
    return true
  }
  const details = document.querySelector<HTMLElement>('.appFilterPanel[open]')
  if (details) {
    details.removeAttribute('open')
    return true
  }
  return false
}

export async function initNativeRuntime(router: Router, dark = false) {
  if (!isAppMode || initialized) return
  initialized = true

  window.addEventListener('online', () => { networkOnline.value = true })
  window.addEventListener('offline', () => { networkOnline.value = false })
  networkOnline.value = navigator.onLine

  if (!isNativeApp) return

  try {
    const status = await Network.getStatus()
    networkOnline.value = status.connected
    await Network.addListener('networkStatusChange', ({ connected }) => { networkOnline.value = connected })
    await Keyboard.setResizeMode({ mode: KeyboardResize.Native })
    await StatusBar.setOverlaysWebView({ overlay: false })
    await syncNativeTheme(dark)
  } catch {
    // The browser-mode App shell remains usable when a native plugin is absent.
  }

  await NativeApp.addListener('backButton', ({ canGoBack }) => {
    if (closeOpenLayer()) return
    const path = router.currentRoute.value.path
    if (path === '/app') {
      const now = Date.now()
      if (now - lastBackAt < 1600) {
        void NativeApp.exitApp()
      } else {
        lastBackAt = now
        window.dispatchEvent(new CustomEvent('app-back-hint'))
      }
      return
    }
    if (canGoBack && hasAppBackEntry()) {
      void router.back()
    } else {
      void router.replace(appParentRoute(router.currentRoute.value))
    }
  })

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target.closest('a[href]') : null
    const href = target?.getAttribute('href') || ''
    if (!target || !/^https?:\/\//i.test(href) || new URL(href, window.location.href).origin === window.location.origin) return
    event.preventDefault()
    void Browser.open({ url: href })
  }, true)
}
