import React from 'react'
import { Link } from 'react-router-dom'

/**
 * SiteFooter — fully responsive dark footer for the homepage.
 *
 * Breakpoints (Stitch spec — booking system design):
 *   mobile  (<md)  : stacked — brand → branches → contact → social → nav pills
 *   tablet  (md)   : 2-col — branches left | contact right
 *   desktop (lg)   : 12-col grid — brand(3) | branches(5) | contact card(4)
 *
 * pb-20: clearance for fixed BottomNav (56px + safe area) on mobile.
 */

// ── SVG icons ──────────────────────────────────────────────────────────────
const FacebookIcon = () => (
  <svg viewBox="0 0 24 24" fill="currentColor" className="w-[18px] h-[18px]" aria-hidden="true">
    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
  </svg>
)

const InstagramIcon = () => (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.75"
    strokeLinecap="round"
    strokeLinejoin="round"
    className="w-[18px] h-[18px]"
    aria-hidden="true"
  >
    <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
    <circle cx="12" cy="12" r="4" />
    <circle cx="17.5" cy="6.5" r="0.5" fill="currentColor" stroke="none" />
  </svg>
)

const TikTokIcon = () => (
  <svg viewBox="0 0 24 24" fill="currentColor" className="w-[18px] h-[18px]" aria-hidden="true">
    <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.34 6.34 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.75a4.85 4.85 0 0 1-1.01-.06z" />
  </svg>
)

const LocationIcon = () => (
  <svg
    viewBox="0 0 24 24"
    fill="currentColor"
    className="w-4 h-4 shrink-0 mt-0.5"
    aria-hidden="true"
  >
    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
  </svg>
)

const PhoneIcon = () => (
  <svg viewBox="0 0 24 24" fill="currentColor" className="w-4 h-4 shrink-0" aria-hidden="true">
    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
  </svg>
)

const MailIcon = () => (
  <svg viewBox="0 0 24 24" fill="currentColor" className="w-4 h-4 shrink-0" aria-hidden="true">
    <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
  </svg>
)

const ArrowNEIcon = () => (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
    className="w-4 h-4 shrink-0 opacity-0 group-hover:opacity-100 -translate-x-2 group-hover:translate-x-0 transition-all duration-300"
    aria-hidden="true"
  >
    <path d="M7 17L17 7M17 7H7M17 7v10" />
  </svg>
)

// ── Data ────────────────────────────────────────────────────────────────────
const BRANCHES = [
  'Soleil Hostel',
  'Soleil Boutique Homestay',
  'Soleil Boutique Upstay',
  'Soleil House Hải',
  'Soleil House Huế',
]

const NAV_LINKS = [
  { path: '/', label: 'Trang chủ' },
  { path: '/rooms', label: 'Phòng' },
  { path: '/booking', label: 'Đặt phòng' },
  { path: '/locations', label: 'Chi nhánh' },
  { path: '/contact', label: 'Liên hệ' },
]

const SOCIAL = [
  { href: 'https://facebook.com', label: 'Facebook', Icon: FacebookIcon },
  { href: 'https://instagram.com', label: 'Instagram', Icon: InstagramIcon },
  { href: 'https://tiktok.com', label: 'TikTok', Icon: TikTokIcon },
]

// ── Component ───────────────────────────────────────────────────────────────
const SiteFooter: React.FC = () => {
  return (
    <footer className="bg-[#151311] border-t border-white/10 mt-8">
      {/* ══ MAIN GRID ══════════════════════════════════════════════════════ */}
      <div
        className="max-w-7xl mx-auto px-5 md:px-8 lg:px-12 pt-10 lg:pt-16 pb-8
                      grid grid-cols-1 md:grid-cols-3 lg:grid-cols-12 gap-10 lg:gap-16"
      >
        {/* ── Col 1: Brand block (lg: 3/12) ──────────────────────────── */}
        <div className="lg:col-span-3 flex flex-col gap-4">
          {/* Wordmark */}
          <div className="flex items-center gap-2">
            <span className="text-[#C9973A] font-medium text-[20px] leading-none">Soleil</span>
            <span className="text-[10px] text-white/40 tracking-widest uppercase mt-0.5">
              HOSTEL
            </span>
          </div>
          <p className="text-[13px] text-white/50 leading-relaxed max-w-[240px]">
            Trải nghiệm lưu trú tuyệt vời tại Huế — nơi mỗi khoảnh khắc đều đáng nhớ.
          </p>
          {/* Social icons — shown here on tablet+, at bottom of contact on mobile */}
          <div className="hidden md:flex gap-3 mt-2">
            {SOCIAL.map(({ href, label, Icon }) => (
              <a
                key={label}
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={label}
                className="w-10 h-10 flex items-center justify-center rounded-full
                           border border-white/15 text-white/60
                           hover:border-[#C9973A] hover:text-[#C9973A]
                           transition-colors duration-300"
              >
                <Icon />
              </a>
            ))}
          </div>
        </div>

        {/* ── Col 2: Branches (lg: 5/12) ─────────────────────────────── */}
        <div className="lg:col-span-5">
          <p className="text-[10px] text-[#C9973A]/60 uppercase tracking-[0.25em] font-medium mb-5">
            Hệ thống cơ sở
          </p>
          <nav className="flex flex-col gap-1">
            {BRANCHES.map((name, i) => (
              <Link
                key={name}
                to="/locations"
                className="group flex items-center justify-between
                           py-3 border-b border-white/8 last:border-0
                           text-white/55 hover:text-[#C9973A]
                           transition-colors duration-300"
              >
                <span
                  className={`text-[14px] lg:text-[16px] tracking-wide ${i === 0 ? 'text-[#C9973A]' : ''}`}
                >
                  {name}
                </span>
                <ArrowNEIcon />
              </Link>
            ))}
          </nav>
        </div>

        {/* ── Col 3: Contact card (lg: 4/12) ─────────────────────────── */}
        <div className="lg:col-span-4">
          <p className="text-[10px] text-[#C9973A]/60 uppercase tracking-[0.25em] font-medium mb-5">
            Liên hệ
          </p>
          <div className="lg:bg-white/[0.04] lg:border lg:border-white/10 lg:rounded-xl lg:p-6 space-y-4">
            <div className="flex gap-3 text-[13px] text-white/55 leading-relaxed">
              <LocationIcon />
              <span>12 Nguyễn Huệ, Phường Phú Hội, Thành phố Huế, Việt Nam</span>
            </div>
            <div>
              <a
                href="tel:0867535593"
                className="flex gap-3 text-[13px] text-white/55 hover:text-[#C9973A] transition-colors"
              >
                <PhoneIcon />
                <span>0867 535 593</span>
              </a>
            </div>
            <div>
              <a
                href="mailto:info@soleil.vn"
                className="flex gap-3 text-[13px] text-white/55 hover:text-[#C9973A] transition-colors"
              >
                <MailIcon />
                <span>info@soleil.vn</span>
              </a>
            </div>

            {/* Social icons — only on mobile (tablet+ uses brand block) */}
            <div className="flex gap-3 pt-3 md:hidden">
              {SOCIAL.map(({ href, label, Icon }) => (
                <a
                  key={label}
                  href={href}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={label}
                  className="w-10 h-10 flex items-center justify-center rounded-full
                             border border-white/15 text-white/60
                             hover:border-[#C9973A] hover:text-[#C9973A]
                             transition-colors duration-300"
                >
                  <Icon />
                </a>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* ══ UTILITY NAV PILLS (mobile / tablet horizontal scroll) ══════════ */}
      <div className="overflow-x-auto no-scrollbar px-5 md:px-8 py-4 border-t border-white/8 lg:hidden">
        <div className="flex gap-2 whitespace-nowrap w-max">
          {NAV_LINKS.map(({ path, label }) => (
            <Link
              key={path}
              to={path}
              className="px-4 py-2 rounded-full bg-white/6 text-[12px] text-white/50
                         hover:text-[#C9973A] hover:bg-white/10 transition-colors"
            >
              {label}
            </Link>
          ))}
        </div>
      </div>

      {/* ══ BOTTOM BAR ═════════════════════════════════════════════════════ */}
      <div
        className="max-w-7xl mx-auto px-5 md:px-8 lg:px-12
                      py-5 border-t border-white/8
                      flex flex-col md:flex-row items-center justify-between gap-4
                      pb-20 md:pb-5"
      >
        <p className="text-[11px] text-white/30 tracking-wide">
          © 2026 Soleil Hostel · Huế, Việt Nam
        </p>
        {/* Desktop nav links */}
        <div className="hidden lg:flex gap-8">
          {NAV_LINKS.map(({ path, label }) => (
            <Link
              key={path}
              to={path}
              className="text-[11px] uppercase tracking-[0.15em] text-white/40
                         hover:text-[#C9973A] transition-colors duration-300"
            >
              {label}
            </Link>
          ))}
        </div>
      </div>
    </footer>
  )
}

export default SiteFooter
