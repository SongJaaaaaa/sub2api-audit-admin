import { SecureStoragePlugin } from 'capacitor-secure-storage-plugin'
import { isNativeApp } from './platform'

const tokenKey = 'adminToken'
const adminKey = 'adminInfo'
let memoryToken = ''
let storageQueue: Promise<void> = Promise.resolve()

function enqueue(task: () => Promise<void>) {
  const run = storageQueue.then(task, task)
  storageQueue = run.catch(() => undefined)
  return run
}

function readWeb(key: string) {
  try {
    return window.localStorage.getItem(key)
  } catch {
    return null
  }
}

function removeWeb(key: string) {
  try {
    window.localStorage.removeItem(key)
  } catch {
    // Storage may be unavailable in a private browser context.
  }
}

async function readNative(key: string) {
  try {
    const result = await SecureStoragePlugin.get({ key })
    return result.value || null
  } catch {
    return null
  }
}

async function writeNative(key: string, value: string) {
  await SecureStoragePlugin.set({ key, value })
}

async function removeNative(key: string) {
  try {
    await SecureStoragePlugin.remove({ key })
  } catch {
    // A missing key is already the desired state.
  }
}

export async function hydrateTokenStorage() {
  await storageQueue
  if (!isNativeApp) {
    memoryToken = readWeb(tokenKey) || ''
    return {
      token: memoryToken,
      adminInfo: readWeb(adminKey),
    }
  }

  let token = await readNative(tokenKey)
  let adminInfo = await readNative(adminKey)

  // Migrate the old WebView values once, then remove the less protected copy.
  if (!token) {
    const oldToken = readWeb(tokenKey)
    const oldAdmin = readWeb(adminKey)
    if (oldToken) {
      await writeNative(tokenKey, oldToken)
      token = oldToken
    }
    if (oldAdmin) {
      await writeNative(adminKey, oldAdmin)
      adminInfo = oldAdmin
    }
    removeWeb(tokenKey)
    removeWeb(adminKey)
  }

  memoryToken = token || ''
  return { token: memoryToken, adminInfo }
}

export function getMemoryToken() {
  return memoryToken
}

export function saveToken(token: string, adminInfo: string) {
  memoryToken = token
  return enqueue(async () => {
    if (isNativeApp) {
      await writeNative(tokenKey, token)
      await writeNative(adminKey, adminInfo)
      removeWeb(tokenKey)
      removeWeb(adminKey)
      return
    }

    window.localStorage.setItem(tokenKey, token)
    window.localStorage.setItem(adminKey, adminInfo)
  })
}

export function saveAdminInfo(adminInfo: string) {
  return enqueue(async () => {
    if (isNativeApp) {
      await writeNative(adminKey, adminInfo)
      removeWeb(adminKey)
      return
    }
    window.localStorage.setItem(adminKey, adminInfo)
  })
}

export function clearTokenStorage() {
  memoryToken = ''
  return enqueue(async () => {
    if (isNativeApp) {
      await Promise.all([removeNative(tokenKey), removeNative(adminKey)])
    }
    removeWeb(tokenKey)
    removeWeb(adminKey)
  })
}
