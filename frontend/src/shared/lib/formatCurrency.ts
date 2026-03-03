const VND = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' })

export function formatVND(amount: number): string {
  return VND.format(amount)
}
