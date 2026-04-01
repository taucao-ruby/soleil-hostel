import React from 'react'
import { Link } from 'react-router-dom'

/**
 * Footer — dark footer for all non-/ non-/admin/* routes (PROMPT_SH1).
 *
 * 3-col desktop, single-col mobile.
 * Col 1: Brand + tagline + address
 * Col 2: Khám phá — nav links
 * Col 3: Liên hệ — phone, email, address
 */

const Footer: React.FC = () => {
  return (
    <footer className="bg-[#1C1A17] border-t border-white/10 mt-auto">
      <div className="max-w-6xl mx-auto px-4 py-12">
        {/* ── 3-col grid ──────────────────────────────────────────────────── */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {/* Col 1 — Brand */}
          <div>
            {/* Wordmark */}
            <div className="flex items-center gap-1.5 mb-4">
              <span className="text-[#C9973A] font-sans font-medium text-[18px] leading-none">
                Soleil
              </span>
              <span className="text-[10px] text-white/40 tracking-widest uppercase mt-0.5">
                HOSTEL
              </span>
            </div>

            <p className="text-[14px] text-white/55 leading-relaxed mb-3">
              Trải nghiệm lưu trú tuyệt vời tại Huế
            </p>

            <p className="text-[13px] text-white/40 leading-relaxed">
              12 Nguyễn Huệ, Phường Phú Hội
              <br />
              Thành phố Huế, Việt Nam
            </p>
          </div>

          {/* Col 2 — Khám phá */}
          <div>
            <h4 className="text-white text-[13px] font-medium uppercase tracking-wider mb-4">
              Khám phá
            </h4>
            <ul className="space-y-2.5">
              {[
                { path: '/', label: 'Trang chủ' },
                { path: '/rooms', label: 'Phòng' },
                { path: '/locations', label: 'Chi nhánh' },
                { path: '/booking', label: 'Đặt phòng' },
              ].map(({ path, label }) => (
                <li key={path}>
                  <Link
                    to={path}
                    className="text-[13px] text-white/55 hover:text-white transition-colors"
                  >
                    {label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Col 3 — Liên hệ */}
          <div>
            <h4 className="text-white text-[13px] font-medium uppercase tracking-wider mb-4">
              Liên hệ
            </h4>
            <ul className="space-y-3">
              <li>
                <a
                  href="tel:02346789012"
                  className="flex items-start gap-2 text-[13px] text-white/55 hover:text-white transition-colors"
                >
                  <span aria-hidden="true" className="mt-0.5">
                    📞
                  </span>
                  <span>0234 xxx xxxx</span>
                </a>
              </li>
              <li>
                <a
                  href="mailto:info@soleil.vn"
                  className="flex items-start gap-2 text-[13px] text-white/55 hover:text-white transition-colors"
                >
                  <span aria-hidden="true" className="mt-0.5">
                    ✉️
                  </span>
                  <span>info@soleil.vn</span>
                </a>
              </li>
              <li className="flex items-start gap-2 text-[13px] text-white/55">
                <span aria-hidden="true" className="mt-0.5">
                  📍
                </span>
                <span>12 Nguyễn Huệ, Huế</span>
              </li>
            </ul>
          </div>
        </div>

        {/* ── Bottom bar ──────────────────────────────────────────────────── */}
        <div className="border-t border-white/10 pt-6 mt-8">
          <p className="text-[12px] text-white/35">© 2026 Soleil Hostel. Bảo lưu mọi quyền.</p>
        </div>
      </div>
    </footer>
  )
}

export default Footer
