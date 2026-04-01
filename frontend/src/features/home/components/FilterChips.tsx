import React, { useState } from 'react'
import type { FilterChip } from '../home.types'

interface FilterChipsProps {
  chips: FilterChip[]
  onFilter?: (chipId: string) => void
}

/**
 * FilterChips — horizontal scrollable single-select amenity filter chips.
 *
 * Active chip: bg-amber-100 text-amber-800 border-amber-300 (PROMPT_0 tokens).
 * Inactive chip: bg-gray-100 text-gray-700 border-transparent.
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
      aria-label="Lọc phòng theo tiện nghi"
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
              'transition-colors duration-150 border',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C9973A] focus-visible:ring-offset-1',
              isActive
                ? 'bg-amber-100 text-amber-800 border-amber-300'
                : 'bg-gray-100 text-gray-700 border-transparent hover:border-[#C9973A]',
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
