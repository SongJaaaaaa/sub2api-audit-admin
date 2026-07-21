import sharp from 'sharp'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const pub = join(__dirname, '..', 'public')
const src = join(pub, 'jarvis-source.jpg')

const meta = await sharp(src).metadata()
console.log('[v0] source size', meta.width, meta.height)

// 裁出圆形徽标区域（去掉底部 MY JARVIS 文字，小尺寸下文字不可读）
const W = meta.width
const H = meta.height
const cx = Math.round(W * 0.505)
const cy = Math.round(H * 0.383)
const half = Math.round(W * 0.30)
const crop = {
  left: Math.max(0, cx - half),
  top: Math.max(0, cy - half),
  width: Math.min(W, half * 2),
  height: Math.min(H, half * 2),
}
console.log('[v0] crop', crop)

const emblem = await sharp(src).extract(crop).png().toBuffer()

// 纯图标（贴边，用于 favicon / 常规 PWA 图标 / apple-touch）
async function icon(size, out) {
  await sharp(emblem).resize(size, size, { fit: 'cover' }).png().toFile(join(pub, out))
  console.log('[v0] wrote', out)
}

// 可遮罩图标：徽标缩小到安全区（约 80%），四周留白背景
async function maskable(size, out) {
  const inner = Math.round(size * 0.72)
  const resized = await sharp(emblem).resize(inner, inner, { fit: 'cover' }).png().toBuffer()
  await sharp({
    create: { width: size, height: size, channels: 4, background: { r: 255, g: 255, b: 255, alpha: 1 } },
  })
    .composite([{ input: resized, gravity: 'center' }])
    .png()
    .toFile(join(pub, out))
  console.log('[v0] wrote', out)
}

await icon(192, 'pwa-192.png')
await icon(512, 'pwa-512.png')
await icon(180, 'apple-touch-icon.png')
await icon(64, 'favicon.png')
await maskable(512, 'pwa-maskable-512.png')

console.log('[v0] all icons generated')
