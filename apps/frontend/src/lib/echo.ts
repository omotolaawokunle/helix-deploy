export type EchoChannel = {
  listen: (event: string, callback: (payload: unknown) => void) => EchoChannel
  stopListening: (event: string) => void
}

export type EchoInstance = {
  private: (channel: string) => EchoChannel
  leave: (channel: string) => void
}

declare global {
  interface Window {
    Echo?: EchoInstance
  }
}

export function getEcho(): EchoInstance | undefined {
  return window.Echo
}
