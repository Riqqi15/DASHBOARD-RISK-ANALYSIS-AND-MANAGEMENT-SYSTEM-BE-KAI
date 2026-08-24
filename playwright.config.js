import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { defineConfig } from '@playwright/test'

function findChromium() {
  const root = process.env.LOCALAPPDATA
    ? path.join(process.env.LOCALAPPDATA, 'ms-playwright')
    : path.join(os.homedir(), '.cache', 'ms-playwright')

  if (!fs.existsSync(root)) return undefined

  const folder = fs.readdirSync(root).find((name) => /^chromium-\d+$/.test(name))
  if (!folder) return undefined

  const candidates = [
    path.join(root, folder, 'chrome-win64', 'chrome.exe'),
    path.join(root, folder, 'chrome-linux', 'chrome'),
    path.join(root, folder, 'chrome-mac', 'Chromium.app', 'Contents', 'MacOS', 'Chromium'),
  ]

  return candidates.find((candidate) => fs.existsSync(candidate))
}

const executablePath = findChromium()

export default defineConfig({
  testDir: './tests/e2e',
  reporter: 'html',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000',
    headless: false,
    launchOptions: executablePath ? { executablePath } : undefined,
    trace: 'on-first-retry',
  },
  webServer: [
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8000',
      url: 'http://127.0.0.1:8000/login',
      reuseExistingServer: true,
    },
  ],
})
