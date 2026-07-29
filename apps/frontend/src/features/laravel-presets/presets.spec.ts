import { describe, expect, it } from 'vitest'
import {
  buildCronPreset,
  buildDaemonPreset,
  buildHorizonDaemonPreset,
  buildSchedulerCronPreset,
  currentPathFromWebroot,
  slugFromDomain,
} from '@/features/laravel-presets/presets'

describe('laravel presets', () => {
  it('derives current path from capistrano webroot', () => {
    expect(currentPathFromWebroot('/var/www/example.test/current/public')).toBe('/var/www/example.test/current')
  })

  it('builds horizon daemon preset', () => {
    const preset = buildHorizonDaemonPreset(
      '/var/www/app.example.com/current/public',
      'app.example.com',
    )

    expect(preset.name).toBe('app-example-com-horizon')
    expect(preset.command).toBe('php artisan horizon')
    expect(preset.directory).toBe('/var/www/app.example.com/current')
  })

  it('builds scheduler cron preset', () => {
    const preset = buildSchedulerCronPreset('/var/www/app.example.com/current/public')

    expect(preset.expression).toBe('* * * * *')
    expect(preset.command).toContain('schedule:run')
    expect(preset.command).toContain('/var/www/app.example.com/current')
  })

  it('builds daemon preset via switch helper', () => {
    const preset = buildDaemonPreset(
      'queue',
      '/home/deploy/worker.example.test/current',
      'worker.example.test',
    )

    expect(preset?.name).toBe('worker-example-test-queue')
    expect(preset?.command).toContain('queue:work')
  })

  it('returns null for custom cron preset', () => {
    expect(buildCronPreset('custom', '/var/www/current/public')).toBeNull()
  })

  it('sanitizes domain slug', () => {
    expect(slugFromDomain('My_App.Example.COM')).toBe('my-app-example-com')
  })
})
