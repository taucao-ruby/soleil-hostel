import React from 'react'
import { Link } from 'react-router-dom'

/**
 * Footer — responsive dark footer for all non-/ non-/admin/* routes (PROMPT_SH1).
 *
 * Breakpoints (Stitch spec — booking system design):
 *   mobile  (<md)  : stacked single column
 *   tablet  (md)   : 2-col — brand+nav | contact
 *   desktop (lg)   : 3-col — brand(4) | nav(4) | contact(4)
 */

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

const NAV_LINKS = [
  { path: '/', label: 'Trang chủ' },
  { path: '/rooms', label: 'Phòng' },
  { path: '/locations', label: 'Chi nhánh' },
  { path: '/booking', label: 'Đặt phòng' },
]

const SOCIAL = [
  { href: 'https://facebook.com', label: 'Facebook', Icon: FacebookIcon },
  { href: 'https://instagram.com', label: 'Instagram', Icon: InstagramIcon },
  { href: 'https://tiktok.com', label: 'TikTok', Icon: TikTokIcon },
]

const Footer: React.FC = () => {
  return (
    <footer className="bg-[#151311] border-t border-white/10 mt-auto">
      <div
        className="max-w-6xl mx-auto px-5 md:px-8 py-10 md:py-14
                      grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10"
      >
        {/* Col 1 — Brand */}
        <div className="flex flex-col gap-4">
          <div className="flex items-center gap-2">
            <span className="text-[#C9973A] font-medium text-[18px] leading-none">Soleil</span>
            <span className="text-[10px] text-white/40 tracking-widest uppercase mt-0.5">
              HOSTEL
            </span>
          </div>
          <p className="text-[13px] text-white/50 leading-relaxed max-w-[220px]">
            Trải nghiệm lưu trú tuyệt vời tại Huế, Việt Nam.
          </p>
          <div className="flex gap-3 mt-1">
            {SOCIAL.map(({ href, label, Icon }) => (
              <a
                key={label}
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={label}
                className="w-9 h-9 flex items-center justify-center rounded-full
                           border border-white/15 text-white/55
                           hover:border-[#C9973A] hover:text-[#C9973A]
                           transition-colors duration-300"
              >
                <Icon />
              </a>
            ))}
          </div>
        </div>

        {/* Col 2 — Khám phá */}
        <div>
          <h4 className="text-[10px] text-[#C9973A]/60 uppercase tracking-[0.25em] font-medium mb-5">
            Khám phá
          </h4>
          <ul className="space-y-3">
            {NAV_LINKS.map(({ path, label }) => (
              <li key={path}>
                <Link
                  to={path}
                  className="text-[13px] text-white/55 hover:text-[#C9973A] transition-colors duration-300"
                >
                  {label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        {/* Col 3 — Liên hệ */}
        <div>
          <h4 className="text-[10px] text-[#C9973A]/60 uppercase tracking-[0.25em] font-medium mb-5">
            Liên hệ
          </h4>
          <ul className="space-y-3">
            <li>
              <a
                href="tel:0867535593"
                className="flex items-start gap-2.5 text-[13px] text-white/55 hover:text-[#C9973A] transition-colors"
              >
                <span aria-hidden="true" className="mt-0.5 shrink-0">
                  📞
                </span>
                <span>0867 535 593</span>
              </a>
            </li>
            <li>
              <a
                href="mailto:info@soleil.vn"
                className="flex items-start gap-2.5 text-[13px] text-white/55 hover:text-[#C9973A] transition-colors"
              >
                <span aria-hidden="true" className="mt-0.5 shrink-0">
                  ✉️
                </span>
                <span>info@soleil.vn</span>
              </a>
            </li>
            <li className="flex items-start gap-2.5 text-[13px] text-white/55">
              <span aria-hidden="true" className="mt-0.5 shrink-0">
                📍
              </span>
              <span>12 Nguyễn Huệ, Phường Phú Hội, Huế</span>
            </li>
          </ul>
        </div>
      </div>

      {/* Bottom bar */}
      <div
        className="max-w-6xl mx-auto px-5 md:px-8 py-5 border-t border-white/8
                      flex flex-col md:flex-row items-center justify-between gap-3"
      >
        <p className="text-[11px] text-white/30">© 2026 Soleil Hostel. Bảo lưu mọi quyền.</p>
        <div className="flex gap-6">
          {NAV_LINKS.map(({ path, label }) => (
            <Link
              key={path}
              to={path}
              className="text-[11px] text-white/30 hover:text-[#C9973A] transition-colors"
            >
              {label}
            </Link>
          ))}
        </div>
      </div>
    </footer>
  )
}

export default Footer
