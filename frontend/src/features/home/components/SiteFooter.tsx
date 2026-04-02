import React from 'react'
import { Link } from 'react-router-dom'

/**
 * SiteFooter — 3-col dark footer (PROMPT_1A spec).
 *
 * Cols: Chi nhánh | Liên hệ | Kết nối
 * pb-20: clearance for fixed BottomNav (56px + safe area)
 */
const SiteFooter: React.FC = () => {
  return (
    <footer className="bg-[#1C1A17] mt-8 pb-20">
      <div className="px-5 pt-8 pb-4 grid grid-cols-3 gap-4">
        {/* Col 1 — Chi nhánh */}
        <div>
          <h4 className="text-white text-xs font-medium uppercase tracking-wider mb-3">
            Chi nhánh
          </h4>
          <ul className="space-y-1.5">
            {[
              'Soleil Hostel',
              'Soleil Boutique Homestay',
              'Soleil Boutique Upstay',
              'Soleil House Hải',
              'Soleil House Huế',
            ].map(name => (
              <li key={name}>
                <Link
                  to="/locations"
                  className="text-[11px] text-white/55 hover:text-white transition-colors"
                >
                  {name}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        {/* Col 2 — Liên hệ */}
        <div>
          <h4 className="text-white text-xs font-medium uppercase tracking-wider mb-3">Liên hệ</h4>
          <ul className="space-y-2">
            <li className="flex gap-1.5 text-[11px] text-white/55">
              <span aria-hidden="true">📍</span>
              <span>341 Ađ, 20 Phố lông Tỉnh, Huế, Việt Nam</span>
            </li>
            <li className="text-[11px] text-white/55">
              <a href="tel:0867535593" className="flex gap-1.5 hover:text-white transition-colors">
                <span aria-hidden="true">📞</span>
                <span>0867 535 593</span>
              </a>
            </li>
          </ul>
        </div>

        {/* Col 3 — Kết nối */}
        <div>
          <h4 className="text-white text-xs font-medium uppercase tracking-wider mb-3">Kết nối</h4>
          <div className="flex flex-wrap gap-2">
            <a
              href="#"
              aria-label="Facebook"
              className="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white text-xs transition-colors"
            >
              f
            </a>
            <a
              href="#"
              aria-label="Instagram"
              className="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white text-xs transition-colors"
            >
              ◻
            </a>
            <a
              href="#"
              aria-label="TikTok"
              className="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white text-xs transition-colors"
            >
              ♪
            </a>
          </div>
        </div>
      </div>

      {/* Bottom bar */}
      <div className="px-5 pt-3 border-t border-white/10">
        <p className="text-[11px] text-white/35">© 2026 Soleil Hostel · Huế, Việt Nam</p>
      </div>
    </footer>
  )
}

export default SiteFooter
