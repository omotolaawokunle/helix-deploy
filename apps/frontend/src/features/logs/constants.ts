export const LOG_LINE_COUNT_OPTIONS = [50, 100, 200, 500] as const

export type LogLineCount = (typeof LOG_LINE_COUNT_OPTIONS)[number]
