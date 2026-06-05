export type BuildRunnerStatus = 'connecting' | 'online' | 'offline' | 'maintenance'

export type BuildRunnerRuntime = 'php' | 'nodejs' | 'python' | 'go' | 'static' | 'docker'

export interface BuildRunnerProjectSummary {
  id: string
  name: string
}

export interface BuildRunner {
  id: string
  name: string
  ipAddress: string
  sshPort: number
  sshUser: string
  status: BuildRunnerStatus
  maxConcurrentBuilds: number
  activeBuilds: number
  availableSlots: number
  cpuCores: number | null
  ramGb: number | null
  supportedRuntimes: BuildRunnerRuntime[]
  project: BuildRunnerProjectSummary | null
  createdAt: string
  updatedAt: string
}

export interface RegisterBuildRunnerPayload {
  name: string
  ipAddress: string
  sshPort: number
  sshUser: string
  authMethod: 'generate' | 'import'
  privateKey?: string
  maxConcurrentBuilds: number
  cpuCores?: number | null
  ramGb?: number | null
  supportedRuntimes: BuildRunnerRuntime[]
  projectId?: string | null
}

export interface BuildRunnerRegistrationResponse {
  runner: BuildRunner
  publicKey: string | null
}

export type BuildStrategy = 'on_server' | 'runner' | 'external'
