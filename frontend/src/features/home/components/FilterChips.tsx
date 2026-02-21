import React, { useState } from 'react'
import type { FilterChip } from '../home.types'

interface FilterChipsProps {
  chips: FilterChip[]
  onFilter?: (chipId: string) => void
}

/**
 * FilterChips — horizontal scrollable single-select filter chips.
 * Active chip uses literal bg-[#D4622A] so regression tests can assert className.
 */
const FilterChips: React.FC<FilterChipsProps> = ({ chips, onFilter }) => {
  const [activeId, setActiveId] = useState<string>(chips[0]?.id ?? '')

  const handleClick = (id: string) => {
    setActiveId(id)
    onFilter?.(id)
  }

  return (
    <div
      className="flex gap-2 overflow-x-auto px-4 py-3"
      style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
      role="group"
      aria-label="Lọc phòng"
    >
      {chips.map(chip => {
        const isActive = chip.id === activeId
        return (
          <button
            key={chip.id}
            onClick={() => handleClick(chip.id)}
            aria-pressed={isActive}
            className={[
              'flex-shrink-0 px-4 py-2 rounded-full text-sm font-sans font-medium whitespace-nowrap',
              'transition-colors duration-150',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D4622A] focus-visible:ring-offset-1',
              isActive
                ? 'bg-[#D4622A] text-white'
                : 'bg-[#F5EFE0] text-[#5C3D1E] border border-[#E2D5C3] hover:border-[#D4622A]',
            ].join(' ')}
          >
            {chip.label}
          </button>
        )
      })}
    </div>
  )
}

export default FilterChips
