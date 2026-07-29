import { describe, expect, it } from 'vitest'
import { buildCreateSitePayload } from '@/features/sites/buildCreateSitePayload'

const baseInput = {
  deployMode: 'git' as const,
  runtime: 'php',
  deployBranch: 'main',
  autoCreateDns: false,
  enableSsl: false,
  phpVersion: '8.3',
  appPort: '3000',
  repositoryUrl: '',
  dockerBuildMode: 'build' as const,
  dockerImage: '',
  dockerRegistry: '',
  dockerComposePath: 'docker-compose.yml',
  domain: 'app.example.test',
}

describe('buildCreateSitePayload', () => {
  it('builds git deploy payload with language runtime', () => {
    const payload = buildCreateSitePayload({
      ...baseInput,
      repositoryUrl: 'https://github.com/org/repo.git',
    })

    expect(payload).toEqual({
      domain: 'app.example.test',
      deployBranch: 'main',
      autoCreateDns: false,
      enableSsl: false,
      runtime: 'php',
      deployMode: 'git',
      phpVersion: '8.3',
      repositoryUrl: 'https://github.com/org/repo.git',
    })
  })

  it('builds docker deploy payload with runtime docker', () => {
    const payload = buildCreateSitePayload({
      ...baseInput,
      deployMode: 'docker',
      runtime: 'php',
      dockerBuildMode: 'build',
      repositoryUrl: 'https://github.com/org/repo.git',
      appPort: '8080',
    })

    expect(payload).toEqual({
      domain: 'app.example.test',
      deployBranch: 'main',
      autoCreateDns: false,
      enableSsl: false,
      runtime: 'docker',
      deployMode: 'docker',
      dockerBuildMode: 'build',
      dockerComposePath: 'docker-compose.yml',
      appPort: 8080,
      repositoryUrl: 'https://github.com/org/repo.git',
    })
  })

  it('includes docker image fields for pull mode', () => {
    const payload = buildCreateSitePayload({
      ...baseInput,
      deployMode: 'docker',
      dockerBuildMode: 'pull',
      dockerImage: 'ghcr.io/org/app:latest',
      dockerRegistry: 'ghcr.io',
      appPort: '9000',
    })

    expect(payload).toMatchObject({
      runtime: 'docker',
      deployMode: 'docker',
      dockerBuildMode: 'pull',
      dockerImage: 'ghcr.io/org/app:latest',
      dockerRegistry: 'ghcr.io',
      appPort: 9000,
    })
    expect(payload.repositoryUrl).toBeUndefined()
  })
})
