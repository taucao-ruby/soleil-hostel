import { useState } from 'react'
import api from '@/shared/lib/api'
import type { BookingActionProposal, ProposalDecisionResponse } from './proposal.types'

interface ProposalConfirmationModalProps {
  proposal: BookingActionProposal
  onClose: () => void
  onResult: (result: ProposalDecisionResponse) => void
}

/**
 * Modal for explicit user confirmation/decline of a BookingActionProposal.
 *
 * - Shows: human_readable_summary, policy_refs, risk_assessment
 * - "Xác nhận" button → POST confirm to harness → existing service validates
 * - "Từ chối" button → POST decline to harness → logged, nothing executed
 * - No auto-confirm path. No timer. No default action.
 */
export default function ProposalConfirmationModal({
  proposal,
  onClose,
  onResult,
}: ProposalConfirmationModalProps) {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  const handleDecision = async (decision: 'confirmed' | 'declined') => {
    setLoading(true)
    setError('')

    try {
      const res = await api.post<{ data: ProposalDecisionResponse }>(
        `/v1/ai/proposals/${proposal.proposal_hash}/decide`,
        { decision }
      )

      const result = res.data?.data
      if (result) {
        onResult(result)
      }
      onClose()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Đã xảy ra lỗi. Vui lòng thử lại.'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  const riskColor =
    proposal.risk_assessment.level === 'high'
      ? 'text-red-600'
      : proposal.risk_assessment.level === 'medium'
        ? 'text-yellow-600'
        : 'text-green-600'

  const actionLabel =
    proposal.action_type === 'suggest_booking' ? 'Đề xuất đặt phòng' : 'Đề xuất hủy booking'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="mx-4 w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
        {/* Header */}
        <h2 className="mb-4 text-lg font-semibold text-gray-900">{actionLabel}</h2>

        {/* Summary */}
        <div className="mb-4 rounded-md bg-blue-50 p-4">
          <p className="text-sm text-gray-800">{proposal.human_readable_summary}</p>
        </div>

        {/* Risk Assessment */}
        <div className="mb-4">
          <h3 className="mb-1 text-sm font-medium text-gray-700">Đánh giá rủi ro</h3>
          <p className={`text-sm font-semibold ${riskColor}`}>
            {proposal.risk_assessment.level.toUpperCase()}
          </p>
          {proposal.risk_assessment.factors.length > 0 && (
            <ul className="mt-1 list-inside list-disc text-sm text-gray-600">
              {proposal.risk_assessment.factors.map(factor => (
                <li key={factor}>{factor}</li>
              ))}
            </ul>
          )}
        </div>

        {/* Policy References */}
        {proposal.policy_refs.length > 0 && (
          <div className="mb-4">
            <h3 className="mb-1 text-sm font-medium text-gray-700">Chính sách tham khảo</h3>
            <ul className="list-inside list-disc text-sm text-gray-600">
              {proposal.policy_refs.map(ref => (
                <li key={ref}>{ref}</li>
              ))}
            </ul>
          </div>
        )}

        {/* Error */}
        {error && <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</div>}

        {/* Actions — explicit confirm/decline, no auto-confirm */}
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={() => handleDecision('declined')}
            disabled={loading}
            className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          >
            Từ chối
          </button>
          <button
            type="button"
            onClick={() => handleDecision('confirmed')}
            disabled={loading}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? 'Đang xử lý...' : 'Xác nhận'}
          </button>
        </div>
      </div>
    </div>
  )
}
