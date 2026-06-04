import { onUnmounted } from 'vue'
import {
  DEPLOYMENT_BROADCAST_EVENTS,
  privateDeploymentChannel,
  type DeploymentBroadcastEventName,
  type DeploymentCompletedPayload,
  type DeploymentFailedPayload,
  type DeploymentLogLinePayload,
  type DeploymentStartedPayload,
  type DeploymentStepFinishedPayload,
  type DeploymentStepEventPayload,
} from '@/features/deployments/types'

export interface DeploymentChannelCallbacks {
  onStarted?: (payload: DeploymentStartedPayload) => void
  onStepStarted?: (payload: DeploymentStepEventPayload) => void
  onStepFinished?: (payload: DeploymentStepFinishedPayload) => void
  onLogLine?: (payload: DeploymentLogLinePayload) => void
  onCompleted?: (payload: DeploymentCompletedPayload) => void
  onFailed?: (payload: DeploymentFailedPayload) => void
}

type EchoChannel = {
  listen: (event: DeploymentBroadcastEventName, callback: (payload: unknown) => void) => EchoChannel
  stopListening: (event: DeploymentBroadcastEventName) => void
}

type EchoInstance = {
  private: (channel: string) => EchoChannel
  leave: (channel: string) => void
}

declare global {
  interface Window {
    Echo?: EchoInstance
  }
}

export function useDeploymentChannel(
  deploymentId: string,
  callbacks: DeploymentChannelCallbacks,
): { channelName: string; disconnect: () => void } {
  const channelName = privateDeploymentChannel(deploymentId)
  const echo = window.Echo

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
    channel.listen(DEPLOYMENT_BROADCAST_EVENTS.completed, (payload: unknown) => {
      callbacks.onCompleted?.(payload as DeploymentCompletedPayload)
    })
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
