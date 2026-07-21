import { chromium } from '@playwright/test'
import fs from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))
const assetDir = path.join(root, 'assets')
const svg = await fs.readFile(path.join(assetDir, 'logo.svg'), 'utf8')
const data = `data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}`
const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const page = await browser.newPage({ viewport: { width: 1024, height: 1024 }, deviceScaleFactor: 1 })

async function render(out, width, height, { background = '#f7f9fc', scale = 0.24, transparent = false, round = false } = {}) {
  await page.setViewportSize({ width, height })
  const size = Math.round(Math.min(width, height) * scale)
  const surface = round
    ? `width:100%;height:100%;border-radius:50%;background:${background};display:grid;place-items:center`
    : `width:100%;height:100%;background:${background};display:grid;place-items:center`
  await page.setContent(`<style>html,body{margin:0;width:100%;height:100%;${transparent || round ? 'background:transparent' : `background:${background}`};display:grid;place-items:center}.surface{${surface}}img{width:${size}px;height:${size}px;object-fit:contain}</style><div class="surface"><img src="${data}" alt=""></div>`)
  await page.screenshot({ path: out, omitBackground: transparent || round })
}

await render(path.join(assetDir, 'icon.png'), 1024, 1024, { background: '#111827', scale: 0.72 })
await render(path.join(assetDir, 'splash.png'), 2732, 2732)

const androidRes = path.join(root, 'android/app/src/main/res')
const iconSizes = { mdpi: 48, hdpi: 72, xhdpi: 96, xxhdpi: 144, xxxhdpi: 192 }
for (const [density, size] of Object.entries(iconSizes)) {
  const dir = path.join(androidRes, `mipmap-${density}`)
  await render(path.join(dir, 'ic_launcher.png'), size, size, { background: '#111827', scale: 0.72 })
  await render(path.join(dir, 'ic_launcher_round.png'), size, size, { background: '#111827', scale: 0.66, round: true })
  await render(path.join(dir, 'ic_launcher_foreground.png'), Math.round(size * 2.25), Math.round(size * 2.25), { background: 'transparent', scale: 0.54, transparent: true })
}

const splashes = [
  ['drawable/splash.png', 480, 320],
  ['drawable-land-mdpi/splash.png', 480, 320],
  ['drawable-land-hdpi/splash.png', 800, 480],
  ['drawable-land-xhdpi/splash.png', 1280, 720],
  ['drawable-land-xxhdpi/splash.png', 1600, 960],
  ['drawable-land-xxxhdpi/splash.png', 1920, 1280],
  ['drawable-port-mdpi/splash.png', 320, 480],
  ['drawable-port-hdpi/splash.png', 480, 800],
  ['drawable-port-xhdpi/splash.png', 720, 1280],
  ['drawable-port-xxhdpi/splash.png', 960, 1600],
  ['drawable-port-xxxhdpi/splash.png', 1280, 1920],
]
for (const [file, width, height] of splashes) {
  await render(path.join(androidRes, file), width, height)
}

const iosAssets = path.join(root, 'ios/App/App/Assets.xcassets')
await render(path.join(iosAssets, 'AppIcon.appiconset/AppIcon-512@2x.png'), 1024, 1024, { background: '#111827', scale: 0.72 })
for (const file of ['splash-2732x2732.png', 'splash-2732x2732-1.png', 'splash-2732x2732-2.png']) {
  await render(path.join(iosAssets, 'Splash.imageset', file), 2732, 2732)
}
await browser.close()
