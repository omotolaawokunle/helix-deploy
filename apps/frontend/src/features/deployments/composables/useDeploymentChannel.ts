import { onUnmounted } from 'vue'
import { getEcho } from '@/lib/echo'
import {
  DEPLOYMENT_BROADCAST_EVENTS,
  privateDeploymentChannel,
  type DeploymentCompletedPayload,
  type DeploymentFailedPayload,
  type DeploymentLogLinePayload,
  type DeploymentStartedPayload,
  type DeploymentStepEventPayload,
  type DeploymentStepFinishedPayload,
} from '@/features/deployments/types'

export interface DeploymentChannelCallbacks {
  onStarted?: (payload: DeploymentStartedPayload) => void
  onStepStarted?: (payload: DeploymentStepEventPayload) => void
  onStepFinished?: (payload: DeploymentStepFinishedPayload) => void
  onLogLine?: (payload: DeploymentLogLinePayload) => void
  onCompleted?: (payload: DeploymentCompletedPayload) => void
  onFailed?: (payload: DeploymentFailedPayload) => void
}

export function useDeploymentChannel(
  deploymentId: string,
  callbacks: DeploymentChannelCallbacks,
): { channelName: string; disconnect: () => void } {
  const channelName = privateDeploymentChannel(deploymentId)
  const echo = getEcho()

  if (echo === undefined) {
    return {
      channelName,
      disconnect: (): void => undefined,
    }
  }

  const channel = echo.private(channelName)

  if (callbacks.onStarted !== undefined) {
    channel.listen(DEPLOYMENT_BROADCAST_EVENTS.started, (payload: unknown) => {
      callbacks.onStarted?.(payload as DeploymentStartedPayload)
    })
  }

  if (callbacks.onStepStarted !== undefined) {
    channel.listen(DEPLOYMENT_BROADCAST_EVENTS.stepStarted, (payload: unknown) => {
      callbacks.onStepStarted?.(payload as DeploymentStepEventPayload)
    })
  }

  if (callbacks.onStepFinished !== undefined) {
    channel.listen(DEPLOYMENT_BROADCAST_EVENTS.stepFinished, (payload: unknown) => {
      callbacks.onStepFinished?.(payload as DeploymentStepFinishedPayload)
    })
  }

  if (callbacks.onLogLine !== undefined) {
    channel.listen(DEPLOYMENT_BROADCAST_EVENTS.logLine, (payload: unknown) => {
      callbacks.onLogLine?.(payload as DeploymentLogLinePayload)
    })
  }

  if (callbacks.onCompleted !== undefined) {
    const handleCompleted = (payload: unknown): void => {
      callbacks.onCompleted?.(payload as DeploymentCompletedPayload)
    }

    channel.listen(DEPLOYMENT_BROADCAST_EVENTS.completed, handleCompleted)
    channel.listen(DEPLOYMENT_BROADCAST_EVENTS.rolledBack, handleCompleted)
  }

  if (callbacks.onFailed !== undefined) {
    channel.listen(DEPLOYMENT_BROADCAST_EVENTS.failed, (payload: unknown) => {
      callbacks.onFailed?.(payload as DeploymentFailedPayload)
    })
  }

  const disconnect = (): void => {
    Object.values(DEPLOYMENT_BROADCAST_EVENTS).forEach((event) => {
      channel.stopListening(event)
    })
    echo.leave(channelName)
  }

  onUnmounted(disconnect)

  return { channelName, disconnect }
}
