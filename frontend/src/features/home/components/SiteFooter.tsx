import React from 'react'
import { Link } from 'react-router-dom'

/**
 * SiteFooter — minimal footer, spec § 07.
 * bg woodDark, white/70 text, links row, copyright.
 * pb-20 to clear the fixed BottomNav.
 */
const SiteFooter: React.FC = () => {
  return (
    <footer className="bg-[#5C3D1E] text-white/70 py-8 px-4 mt-8 pb-20">
      {/* Logo + tagline */}
      <p className="font-serif font-semibold text-white text-base mb-1">Soleil Hostel</p>
      <p className="font-sans text-sm text-white/60 mb-6">Huế, Việt Nam</p>

      {/* Links */}
      <nav aria-label="Footer navigation" className="flex gap-6 mb-6">
        <Link
          to="/"
          className="font-sans text-[13px] text-white/60 hover:text-white transition-colors"
        >
          Giới thiệu
        </Link>
        <Link
          to="/rooms"
          className="font-sans text-[13px] text-white/60 hover:text-white transition-colors"
        >
          Phòng
        </Link>
        <Link
          to="/"
          className="font-sans text-[13px] text-white/60 hover:text-white transition-colors"
        >
          Liên hệ
        </Link>
      </nav>

      {/* Copyright */}
      <p className="font-sans text-xs text-white/40 mt-4">
        © 2026 Soleil Hostel. All rights reserved.
      </p>
    </footer>
  )
}

export default SiteFooter
