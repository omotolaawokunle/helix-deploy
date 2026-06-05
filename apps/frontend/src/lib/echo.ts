import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { api } from '@/lib/axios'

export type EchoChannel = {
  listen: (event: string, callback: (payload: unknown) => void) => EchoChannel
  stopListening: (event: string) => void
}

export type EchoInstance = {
  private: (channel: string) => EchoChannel
  leave: (channel: string) => void
  disconnect: () => void
}

declare global {
  interface Window {
    Echo?: EchoInstance
    Pusher?: typeof Pusher
  }
}

let echoInstance: EchoInstance | undefined
let connectionListenerAttached = false

export type EchoConnectionState =
  | 'connecting'
  | 'connected'
  | 'disconnected'
  | 'unavailable'

type ConnectionStateListener = (state: EchoConnectionState) => void

const connectionStateListeners = new Set<ConnectionStateListener>()

function notifyConnectionState(state: EchoConnectionState): void {
  connectionStateListeners.forEach((listener) => {
    listener(state)
  })
}

export function subscribeEchoConnectionState(listener: ConnectionStateListener): () => void {
  connectionStateListeners.add(listener)

  return (): void => {
    connectionStateListeners.delete(listener)
  }
}

function attachConnectionStateListener(echo: InstanceType<typeof Echo>): void {
  if (connectionListenerAttached) {
    return
  }

  const connector = echo as unknown as {
    connector?: { pusher?: { connection: { bind: (event: string, callback: (payload: { current: string }) => void) => void } } }
  }

  const pusher = connector.connector?.pusher

  if (pusher === undefined) {
    return
  }

  connectionListenerAttached = true

  pusher.connection.bind('state_change', (states) => {
    const current = states.current

    if (
      current === 'connecting'
      || current === 'connected'
      || current === 'disconnected'
      || current === 'unavailable'
    ) {
      notifyConnectionState(current)
    }
  })
}

function reverbUsesTls(): boolean {
  return (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https'
}

export function initEcho(): EchoInstance | undefined {
  const appKey = import.meta.env.VITE_REVERB_APP_KEY

  if (appKey === undefined || appKey === '') {
    return undefined
  }

  if (echoInstance !== undefined) {
    return echoInstance
  }

  window.Pusher = Pusher

  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080)

  const echo = new Echo({
    broadcaster: 'reverb',
    key: appKey,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: reverbUsesTls(),
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
        void api
          .post('/broadcasting/auth', {
            socket_id: socketId,
            channel_name: channel.name,
          })
          .then((response) => {
            callback(null, response.data as { auth: string })
          })
          .catch((error: unknown) => {
            callback(error instanceof Error ? error : new Error('Broadcast auth failed'), null)
          })
      },
    }),
  })

  echoInstance = echo as unknown as EchoInstance
  window.Echo = echoInstance

  attachConnectionStateListener(echo)
  notifyConnectionState('connecting')

  return echoInstance
}

export function getEcho(): EchoInstance | undefined {
  return echoInstance ?? window.Echo
}

export function disconnectEcho(): void {
  if (echoInstance !== undefined) {
    echoInstance.disconnect()
    echoInstance = undefined
    window.Echo = undefined
    connectionListenerAttached = false
    notifyConnectionState('disconnected')
  }
}
