export const HOSTEL_TIME_ZONE = 'Asia/Ho_Chi_Minh'

export function getHostelToday(now: Date = new Date()): string {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: HOSTEL_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(now)

  const getPart = (type: string): string => {
    const value = parts.find(part => part.type === type)?.value

    if (!value) {
      throw new Error(`Missing date part: ${type}`)
    }

    return value
  }

  return `${getPart('year')}-${getPart('month')}-${getPart('day')}`
}

export function addDaysToDateOnly(date: string, amount: number): string {
  const [year, month, day] = date.split('-').map(Number)

  if (!year || !month || !day) {
    return date
  }

  const nextDate = new Date(Date.UTC(year, month - 1, day + amount))
  const nextYear = nextDate.getUTCFullYear()
  const nextMonth = String(nextDate.getUTCMonth() + 1).padStart(2, '0')
  const nextDay = String(nextDate.getUTCDate()).padStart(2, '0')

  return `${nextYear}-${nextMonth}-${nextDay}`
}

export function getHostelTomorrow(now: Date = new Date()): string {
  return addDaysToDateOnly(getHostelToday(now), 1)
}

export function isDateBeforeHostelToday(date: string, now: Date = new Date()): boolean {
  return date < getHostelToday(now)
}

export function isDateOnOrBeforeHostelToday(date: string, now: Date = new Date()): boolean {
  return date <= getHostelToday(now)
}
