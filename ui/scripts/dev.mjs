/**
 * Spustí vývojové prostredie: vite dev server + Laravel queue worker naraz.
 *
 * Notifikácie (OrderCreated, OrderExpedition, UserInvited, ...) sú ShouldQueue
 * a QUEUE_CONNECTION=database, takže bez bežiaceho workera ostanú maily visieť
 * v tabuľke jobs a navonok to vyzerá, že sa e-maily neposielajú. Na druhé okno
 * s queue:work sa pravidelne zabúda, tak sa worker spúšťa rovno s vite.
 *
 * API sa tu nespúšťa — beží pod Apache, takže `php artisan serve` netreba.
 *
 * Spustenie: npm run dev
 */
import { spawn } from 'node:child_process'
import path from 'node:path'

const uiDirectory = path.resolve(import.meta.dirname, '..')
const apiDirectory = path.resolve(uiDirectory, '..', 'api')

// Vite sa spúšťa cez jeho JS entry, nie cez node_modules/.bin/vite.cmd —
// Node od v20 odmieta spawn .cmd súboru bez `shell: true` a shell by nám
// zase skomplikoval ukončovanie procesu.
const viteEntry = path.join(uiDirectory, 'node_modules', 'vite', 'bin', 'vite.js')

// Fronta tu vozí len notifikácie, takže krátky timeout a pár pokusov navyše
// kvôli výpadkom SMTP.
const workerArguments = ['artisan', 'queue:work', '--queue=default', '--tries=3', '--timeout=120', '--sleep=1']
const workerRestartDelay = 3000

let shuttingDown = false
const running = new Set()

/** Predradí každému riadku výstupu značku procesu, nech sa dva logy nemiešajú. */
function prefixOutput(stream, label, target) {
  let pending = ''

  stream.setEncoding('utf8')
  stream.on('data', chunk => {
    const lines = (pending + chunk).split(/\r?\n/)
    // Posledný kus môže byť nedokončený riadok — počká na ďalší chunk.
    pending = lines.pop()
    for (const line of lines) target.write(`[${label}] ${line}\n`)
  })
  stream.on('end', () => {
    if (pending) target.write(`[${label}] ${pending}\n`)
  })
}

function start(label, command, args, cwd) {
  const child = spawn(command, args, { cwd, stdio: ['ignore', 'pipe', 'pipe'] })

  running.add(child)
  prefixOutput(child.stdout, label, process.stdout)
  prefixOutput(child.stderr, label, process.stderr)
  child.on('exit', () => running.delete(child))

  return child
}

function kill(child) {
  if (child.exitCode !== null || child.signalCode !== null) return

  if (process.platform === 'win32') {
    // child.kill() na Windows nezabije potomkov — php.exe by prežil a ďalej
    // vyberal joby z fronty aj po ukončení dev servera.
    spawn('taskkill', ['/pid', String(child.pid), '/T', '/F'], { stdio: 'ignore' }).unref()

    return
  }

  child.kill('SIGTERM')
}

function shutdown(code) {
  if (shuttingDown) return
  shuttingDown = true
  process.exitCode = code
  for (const child of running) kill(child)
}

// ---- queue worker ----------------------------------------------------------

let phpMissing = false

function startWorker() {
  const worker = start('queue', 'php', workerArguments, apiDirectory)

  // queue:work pri štarte sám nič nevypíše, takže bez tohto riadku sa nedá
  // rozoznať bežiaci worker od nespusteného. Až na 'spawn', inak by sa
  // ohlásil aj worker, ktorý sa vzápätí nepodarí spustiť.
  worker.on('spawn', () => {
    process.stdout.write('[queue] worker beží (php artisan queue:work)\n')
  })

  worker.on('error', error => {
    if (error.code !== 'ENOENT') throw error

    // Chýbajúce PHP nemá zablokovať prácu na UI — len nech je jasné, prečo
    // sa notifikácie neodosielajú.
    phpMissing = true
    process.stderr.write('[queue] php sa nenašiel v PATH — worker nebeží, e-maily ostanú vo fronte.\n')
  })

  worker.on('exit', () => {
    if (shuttingDown || phpMissing) return

    // Worker po páde musí nabehnúť späť sám.
    process.stdout.write('[queue] worker sa zastavil, reštartujem o 3 sekundy...\n')
    setTimeout(startWorker, workerRestartDelay).unref()
  })
}

// ---- vite ------------------------------------------------------------------

const vite = start('vite', process.execPath, [viteEntry], uiDirectory)

// Vite je hlavný proces: keď skončí on, končí celé `npm run dev`.
vite.on('exit', code => shutdown(code ?? 1))

startWorker()

for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP', 'SIGBREAK']) {
  process.on(signal, () => shutdown(0))
}
