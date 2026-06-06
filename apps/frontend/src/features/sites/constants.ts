import type { SiteBuildStrategy } from '@/types'

export const SELECTABLE_SITE_BUILD_STRATEGIES: SiteBuildStrategy[] = ['on_server', 'runner']

export const SELECTABLE_SITE_BUILD_STRATEGY_OPTIONS: Array<{
  value: SiteBuildStrategy
  label: string
  description: string
}> = [
  {
    value: 'on_server',
    label: 'On server',
    description: 'Clone and build directly on the deployment server.',
  },
  {
    value: 'runner',
    label: 'Build runner',
    description: 'Compile on a dedicated runner, then deploy the artifact.',
  },
]

export const EXTERNAL_BUILD_STRATEGY_LABEL = 'External artifact'

export const EXTERNAL_BUILD_STRATEGY_V2_MESSAGE =
  'This site uses the external artifact build strategy, which is not configurable in v1. Pre-built artifact upload is planned for v2.'

export function isSelectableBuildStrategy(strategy: SiteBuildStrategy): boolean {
  return SELECTABLE_SITE_BUILD_STRATEGIES.includes(strategy)
}
