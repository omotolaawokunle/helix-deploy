import type { ApiTokenAbility } from '@/features/auth/types'

export const PROFILE_TIMEZONE_OPTIONS = [
  'UTC',
  'America/New_York',
  'America/Los_Angeles',
  'America/Chicago',
  'Europe/London',
  'Europe/Berlin',
  'Africa/Lagos',
  'Asia/Tokyo',
  'Australia/Sydney',
] as const

export const API_TOKEN_ABILITY_OPTIONS: Array<{
  value: ApiTokenAbility
  label: string
  description: string
}> = [
  {
    value: 'read',
    label: 'Read only',
    description: 'List and view resources. Cannot create, update, or delete.',
  },
  {
    value: 'full',
    label: 'Full access',
    description: 'Complete API access for automation and CI/CD pipelines.',
  },
]

export function apiTokenAbilityLabel(ability: ApiTokenAbility): string {
  return API_TOKEN_ABILITY_OPTIONS.find(option => option.value === ability)?.label ?? ability
}
