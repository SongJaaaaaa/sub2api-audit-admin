import { closeSync, existsSync, openSync } from 'node:fs'
import { spawn, spawnSync } from 'node:child_process'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const backend = resolve(frontend, '..', 'backend')
const [mode, portArg] = process.argv.slice(2)
const port = Number(portArg)

if (!['backend', 'frontend'].includes(mode) || !Number.isInteger(port)) {
  console.error('Usage: node scripts/e2e-server.mjs <backend|frontend> <port>')
  process.exit(1)
}

let child
if (mode === 'backend') {
  const windowsPhp = 'D:\\php\\php.exe'
  const php = process.env.PHP_BINARY || (process.platform === 'win32' && existsSync(windowsPhp) ? windowsPhp : 'php')
  const useExternalDb = process.env.E2E_USE_EXTERNAL_DB === 'true'
  const env = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_KEY: process.env.APP_KEY || 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'array',
  }

  if (useExternalDb) {
    const dbName = process.env.DB_DATABASE || ''
    const safeName = /(^|[_-])e2e($|[_-])/i.test(dbName)
    if (process.env.APP_ENV !== 'testing' || process.env.DB_CONNECTION !== 'pgsql' || !safeName) {
      console.error('External E2E database requires APP_ENV=testing, DB_CONNECTION=pgsql, and an E2E-only database name.')
      process.exit(1)
    }
  } else {
    const sqlite = resolve(backend, 'database', 'e2e.sqlite')
    closeSync(openSync(sqlite, 'a'))
    Object.assign(env, {
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: sqlite,
      DB_URL: '',
    })
  }

  const migrated = spawnSync(php, ['artisan', 'migrate:fresh', '--seed', '--force'], {
    cwd: backend,
    env,
    stdio: 'inherit',
    windowsHide: true,
  })
  if (migrated.status !== 0) process.exit(migrated.status ?? 1)

  if (process.platform === 'win32') {
    const router = resolve(backend, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php')
    child = spawn(php, ['-S', `127.0.0.1:${port}`, router], {
      cwd: resolve(backend, 'public'),
      env,
      stdio: 'inherit',
      windowsHide: true,
    })
  } else {
    child = spawn(php, ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`], {
      cwd: backend,
      env: { ...env, PHP_CLI_SERVER_WORKERS: process.env.PHP_CLI_SERVER_WORKERS || '4' },
      stdio: 'inherit',
    })
  }
} else {
  const backendPort = process.env.E2E_BACKEND_PORT || '8010'
  child = spawn(process.execPath, [
    resolve(frontend, 'node_modules', 'vite', 'bin', 'vite.js'),
    '--host', '127.0.0.1',
    '--port', String(port),
    '--strictPort',
  ], {
    cwd: frontend,
    env: { ...process.env, VITE_API_PROXY_TARGET: `http://127.0.0.1:${backendPort}` },
    stdio: 'inherit',
    windowsHide: true,
  })
}

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => child.kill(signal))
}
child.on('exit', (code) => process.exit(code ?? 0))
