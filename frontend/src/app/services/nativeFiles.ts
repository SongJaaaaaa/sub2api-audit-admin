import { Browser } from '@capacitor/browser'
import { Directory, Encoding, Filesystem } from '@capacitor/filesystem'
import { Share } from '@capacitor/share'
import { isNativeApp } from './platform'

function toBase64(buffer: ArrayBuffer) {
  const bytes = new Uint8Array(buffer)
  let binary = ''
  const chunk = 0x8000
  for (let index = 0; index < bytes.length; index += chunk) {
    binary += String.fromCharCode(...bytes.subarray(index, index + chunk))
  }
  return btoa(binary)
}

export async function saveBlob(blob: Blob, filename: string, title = filename) {
  if (!isNativeApp) {
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    anchor.click()
    URL.revokeObjectURL(url)
    return
  }

  const result = await Filesystem.writeFile({
    path: filename,
    directory: Directory.Documents,
    data: toBase64(await blob.arrayBuffer()),
  })
  const share = await Share.canShare()
  if (share.value) {
    await Share.share({ title, files: [result.uri], dialogTitle: '分享文件' })
  }
}

export async function openExternal(url: string) {
  if (isNativeApp) {
    await Browser.open({ url })
    return
  }
  window.open(url, '_blank', 'noopener,noreferrer')
}

export async function saveTextFile(content: string, filename: string) {
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  if (isNativeApp) {
    await Filesystem.writeFile({ path: filename, directory: Directory.Documents, data: content, encoding: Encoding.UTF8 })
    return
  }
  await saveBlob(blob, filename)
}
