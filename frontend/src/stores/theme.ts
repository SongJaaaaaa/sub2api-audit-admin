import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export type ThemeMode = 'system' | 'light' | 'dark'
export type ThemeName = 'light' | 'dark'

const key = 'themeMode'
const modes: ThemeMode[] = ['system', 'light', 'dark']

function savedMode(): ThemeMode {
  const val = localStorage.getItem(key) as ThemeMode | null
  return val && modes.includes(val) ? val : 'system'
}

export const useThemeStore = defineStore('theme', () => {
  const mode = ref<ThemeMode>(savedMode())
  const systemDark = ref(window.matchMedia('(prefers-color-scheme: dark)').matches)

  const themeName = computed<ThemeName>(() => {
    if (mode.value === 'system') return systemDark.value ? 'dark' : 'light'
    return mode.value
  })

  function apply() {
    document.documentElement.dataset.theme = themeName.value
  }

  function setMode(val: ThemeMode) {
    mode.value = val
    localStorage.setItem(key, val)
    apply()
  }

  function initTheme() {
    apply()
    const media = window.matchMedia('(prefers-color-scheme: dark)')
    media.addEventListener('change', (event) => {
      systemDark.value = event.matches
      apply()
    })
  }

  return {
    mode,
    themeName,
    setMode,
    initTheme,
  }
})
