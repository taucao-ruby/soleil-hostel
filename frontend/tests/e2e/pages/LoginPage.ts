import { Page } from '@playwright/test'

/**
 * Login page (/login) — drives the httpOnly-cookie auth flow.
 *
 * On success the SPA stores the csrf_token in sessionStorage, the backend
 * sets the SameSite=Strict soleil_token cookie, and the app redirects to
 * /dashboard.
 */
export class LoginPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/login')
  }

  async login(email: string, password: string): Promise<void> {
    await this.page.getByLabel('Địa chỉ email', { exact: true }).fill(email)
    await this.page.getByLabel('Mật khẩu', { exact: true }).fill(password)
    await this.page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
    // Successful login redirects to the dashboard; waiting here guarantees the
    // cookie + sessionStorage csrf_token are in place before we navigate on.
    await this.page.waitForURL('**/dashboard', { timeout: 15_000 })
  }
}
