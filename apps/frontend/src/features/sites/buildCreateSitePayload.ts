import type { CreateSitePayload } from '@/features/sites/api'
import type { DockerBuildMode, SiteDeployMode } from '@/types'

export interface BuildCreateSitePayloadInput {
  deployMode: SiteDeployMode
  runtime: string
  deployBranch: string
  autoCreateDns: boolean
  enableSsl: boolean
  phpVersion: string
  appPort: string
  repositoryUrl: string
  dockerBuildMode: DockerBuildMode
  dockerImage: string
  dockerRegistry: string
  dockerComposePath: string
  domain?: string
  subdomainPrefix?: string
  projectId?: string
  projectDnsZoneId?: string
  includeWwwAlias?: boolean
  sslChallenge?: 'http-01' | 'dns-01'
}

export function buildCreateSitePayload(input: BuildCreateSitePayloadInput): CreateSitePayload {
  const isDockerDeployMode = input.deployMode === 'docker'

  const payload: CreateSitePayload = {
    deployBranch: input.deployBranch.trim() || 'main',
    autoCreateDns: input.autoCreateDns,
    enableSsl: input.enableSsl,
    runtime: isDockerDeployMode ? 'docker' : input.runtime,
  }

  if (isDockerDeployMode) {
    payload.deployMode = 'docker'
    payload.dockerBuildMode = input.dockerBuildMode

    if (input.dockerComposePath.trim() !== '') {
      payload.dockerComposePath = input.dockerComposePath.trim()
    }

    if (input.dockerBuildMode === 'pull') {
      if (input.dockerImage.trim() !== '') {
        payload.dockerImage = input.dockerImage.trim()
      }

      if (input.dockerRegistry.trim() !== '') {
        payload.dockerRegistry = input.dockerRegistry.trim()
      }
    }
  } else {
    payload.deployMode = 'git'
  }

  if (input.projectId !== undefined && input.projectId !== '') {
    payload.projectId = input.projectId
  }

  if (input.subdomainPrefix !== undefined) {
    payload.subdomainPrefix = input.subdomainPrefix
    payload.projectDnsZoneId = input.projectDnsZoneId

    if (input.includeWwwAlias === true) {
      payload.includeWwwAlias = true
    }
  } else if (input.domain !== undefined) {
    payload.domain = input.domain
  }

  if (input.enableSsl && input.sslChallenge === 'dns-01') {
    payload.sslChallenge = 'dns-01'
  }

  if (!isDockerDeployMode && input.runtime === 'php') {
    payload.phpVersion = input.phpVersion
  }

  const requiresAppPort = isDockerDeployMode || ['nodejs', 'python', 'go'].includes(input.runtime)

  if (requiresAppPort) {
    payload.appPort = Number(input.appPort)
  }

  if (input.repositoryUrl.trim() !== '') {
    payload.repositoryUrl = input.repositoryUrl.trim()
  }

  return payload
}
