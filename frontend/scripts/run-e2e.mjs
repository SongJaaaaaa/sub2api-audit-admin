import { spawn } from 'node:child_process'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(fileURLToPath(new URL('..', import.meta.url)))
const cli = resolve(root, 'node_modules', '@playwright', 'test', 'cli.js')
const mode = process.argv[2] || 'web'
const args = [cli, 'test', ...process.argv.slice(3)]
const env = { ...process.env, E2E_APP_ONLY: mode === 'app' ? 'true' : 'false' }
const child = spawn(process.execPath, args, { cwd: root, env, stdio: 'inherit', windowsHide: true })
child.on('exit', code => process.exit(code ?? 1))
