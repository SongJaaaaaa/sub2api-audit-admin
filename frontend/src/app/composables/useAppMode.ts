import { computed } from 'vue'
import { isAppMode, isNativeApp } from '../services/platform'

const appMode = computed(() => isAppMode)

export function useAppMode() {
  return {
    isAppMode: appMode,
    isNativeApp,
  }
}
