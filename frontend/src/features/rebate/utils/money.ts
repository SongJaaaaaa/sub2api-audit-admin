export function toCents(value: string | null | undefined) {
  const raw = String(value || '0').trim()
  const negative = raw.startsWith('-')
  const [whole = '0', decimal = ''] = raw.replace(/^[+-]/, '').split('.')
  const cents = BigInt(whole || '0') * 100n + BigInt(`${decimal}00`.slice(0, 2))
  return negative ? -cents : cents
}

export function formatCents(value: bigint) {
  const negative = value < 0n
  const absolute = negative ? -value : value
  const whole = (absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const decimal = (absolute % 100n).toString().padStart(2, '0')
  return `${negative ? '-' : ''}¥${whole}.${decimal}`
}

export function money(value: string | null | undefined) {
  return formatCents(toCents(value))
}

export function compareMoney(left: string, right: string) {
  const leftCents = toCents(left)
  const rightCents = toCents(right)
  return leftCents === rightCents ? 0 : leftCents > rightCents ? 1 : -1
}

export function multiplyMoney(value: string, rate: string) {
  const [whole = '0', decimal = ''] = String(rate || '0').trim().split('.')
  const units = BigInt(whole || '0') * 10000n + BigInt(`${decimal}0000`.slice(0, 4))
  return formatCents(toCents(value) * units / 10000n)
}

export function sumMoney<T>(rows: T[], read: (row: T) => string) {
  return rows.reduce((sum, row) => sum + toCents(read(row)), 0n)
}
