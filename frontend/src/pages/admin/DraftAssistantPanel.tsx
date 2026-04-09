import { useState, useCallback } from 'react'
import api from '@/shared/lib/api'

interface ToolDraftResult {
  tool_name: string
  draft_text: string
  suggested_tone: string
  context_used: string[]
  policy_refs: string[]
  key_facts: Record<string, string>
  draft_hash: string
  generated_at: string
}

interface DraftResponse {
  canary?: boolean
  support_contact?: string
  message?: string
  response_class?: string
  content?: string
  tool_results?: ToolDraftResult
}

interface AuditEntry {
  action: string
  draftHash: string
  bookingId: string
  timestamp: string
}

export default function DraftAssistantPanel() {
  const [context, setContext] = useState('')
  const [bookingId, setBookingId] = useState('')
  const [draftType, setDraftType] = useState<'message' | 'cancellation'>('message')
  const [loading, setLoading] = useState(false)
  const [draft, setDraft] = useState<ToolDraftResult | null>(null)
  const [editedText, setEditedText] = useState('')
  const [error, setError] = useState('')
  const [auditLog, setAuditLog] = useState<AuditEntry[]>([])
  const [sent, setSent] = useState(false)

  const generateDraft = useCallback(async () => {
    setLoading(true)
    setError('')
    setDraft(null)
    setSent(false)

    try {
      const message =
        draftType === 'cancellation'
          ? `Soạn tóm tắt hủy cho booking #${bookingId}`
          : `${context}${bookingId ? ` (booking #${bookingId})` : ''}`

      const res = await api.post<{ data: DraftResponse }>('/v1/ai/admin_draft', {
        message,
      })

      const data = res.data?.data

      if (data?.canary === false) {
        setError(data.message ?? 'Chức năng này chưa được kích hoạt.')
        return
      }

      if (data?.tool_results) {
        setDraft(data.tool_results)
        setEditedText(data.tool_results.draft_text)
      } else if (data?.content) {
        setDraft({
          tool_name:
            draftType === 'cancellation' ? 'draft_cancellation_summary' : 'draft_admin_message',
          draft_text: data.content,
          suggested_tone: 'professional',
          context_used: [],
          policy_refs: [],
          key_facts: {},
          draft_hash: '',
          generated_at: new Date().toISOString(),
        })
        setEditedText(data.content)
      }
    } catch {
      setError('Không thể tạo bản nháp. Vui lòng thử lại.')
    } finally {
      setLoading(false)
    }
  }, [context, bookingId, draftType])

  const handleSend = useCallback(
    (edited: boolean) => {
      if (!draft) return

      const entry: AuditEntry = {
        action: edited ? 'edit_then_send' : 'send_as_is',
        draftHash: draft.draft_hash,
        bookingId,
        timestamp: new Date().toISOString(),
      }

      setAuditLog(prev => [...prev, entry])
      setSent(true)

      // Log audit entry for observability (in production, send to backend)
      console.warn('[AI Draft Audit]', entry)
    },
    [draft, bookingId]
  )

  const handleDiscard = useCallback(() => {
    if (!draft) return

    const entry: AuditEntry = {
      action: 'discard',
      draftHash: draft.draft_hash,
      bookingId,
      timestamp: new Date().toISOString(),
    }

    setAuditLog(prev => [...prev, entry])
    setDraft(null)
    setEditedText('')
    setSent(false)

    console.warn('[AI Draft Audit]', entry)
  }, [draft, bookingId])

  return (
    <div className="w-full max-w-3xl p-6 mx-auto bg-white border border-gray-200 rounded-lg shadow-sm">
      <h2 className="mb-4 text-lg font-semibold text-gray-800">Trợ lý soạn thảo</h2>

      {/* Draft type selector */}
      <div className="flex gap-2 mb-4">
        <button
          onClick={() => setDraftType('message')}
          className={`px-4 py-2 rounded text-sm font-medium ${
            draftType === 'message'
              ? 'bg-indigo-600 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          }`}
        >
          Phản hồi khách
        </button>
        <button
          onClick={() => setDraftType('cancellation')}
          className={`px-4 py-2 rounded text-sm font-medium ${
            draftType === 'cancellation'
              ? 'bg-indigo-600 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          }`}
        >
          Tóm tắt hủy
        </button>
      </div>

      {/* Input fields */}
      <div className="mb-4 space-y-3">
        <div>
          <label className="block mb-1 text-sm font-medium text-gray-700">
            Mã đặt phòng (nếu có)
          </label>
          <input
            type="text"
            value={bookingId}
            onChange={e => setBookingId(e.target.value)}
            placeholder="VD: 123"
            className="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
          />
        </div>
        {draftType === 'message' && (
          <div>
            <label className="block mb-1 text-sm font-medium text-gray-700">Nội dung yêu cầu</label>
            <textarea
              value={context}
              onChange={e => setContext(e.target.value)}
              rows={3}
              placeholder="Mô tả yêu cầu của khách hoặc nội dung cần phản hồi..."
              className="w-full px-3 py-2 text-sm border border-gray-300 rounded resize-none focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
        )}
      </div>

      <button
        onClick={generateDraft}
        disabled={
          loading ||
          (draftType === 'message' && !context) ||
          (draftType === 'cancellation' && !bookingId)
        }
        className="w-full px-4 py-2 mb-4 text-sm font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {loading ? 'Đang tạo bản nháp...' : 'Tạo bản nháp AI'}
      </button>

      {error && <div className="p-3 mb-4 text-sm text-red-800 rounded bg-red-50">{error}</div>}

      {/* Draft display */}
      {draft && !sent && (
        <div className="p-4 mb-4 border border-indigo-200 rounded bg-indigo-50">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-indigo-700">
              Bản nháp AI · Giọng văn: {draft.suggested_tone}
            </span>
            <span className="px-2 py-0.5 text-xs bg-yellow-100 text-yellow-800 rounded">
              Cần duyệt
            </span>
          </div>

          <textarea
            value={editedText}
            onChange={e => setEditedText(e.target.value)}
            rows={6}
            className="w-full p-3 mb-3 text-sm border border-gray-300 rounded resize-y focus:ring-indigo-500 focus:border-indigo-500"
          />

          {/* Context used */}
          {draft.context_used.length > 0 && (
            <div className="mb-2 text-xs text-gray-500">Nguồn: {draft.context_used.join(', ')}</div>
          )}
          {draft.policy_refs.length > 0 && (
            <div className="mb-3 text-xs text-gray-500">
              Chính sách: {draft.policy_refs.join(', ')}
            </div>
          )}

          {/* Action buttons */}
          <div className="flex gap-2">
            <button
              onClick={() => handleSend(editedText !== draft.draft_text)}
              className="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded hover:bg-green-700"
            >
              {editedText !== draft.draft_text ? 'Gửi sau chỉnh sửa' : 'Gửi nguyên bản'}
            </button>
            <button
              onClick={handleDiscard}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
            >
              Hủy bỏ
            </button>
          </div>
        </div>
      )}

      {sent && (
        <div className="p-3 mb-4 text-sm text-green-800 rounded bg-green-50">
          Bản nháp đã được gửi thành công.
        </div>
      )}

      {/* Audit log (visible to admin) */}
      {auditLog.length > 0 && (
        <div className="mt-4">
          <h3 className="mb-2 text-sm font-semibold text-gray-600">Nhật ký sử dụng AI</h3>
          <div className="space-y-1">
            {auditLog.map((entry, i) => (
              <div
                key={i}
                className="flex justify-between p-2 text-xs text-gray-600 rounded bg-gray-50"
              >
                <span>
                  {entry.action === 'send_as_is' && '✅ Gửi nguyên bản'}
                  {entry.action === 'edit_then_send' && '✏️ Gửi sau chỉnh sửa'}
                  {entry.action === 'discard' && '🗑️ Hủy bỏ'}
                  {entry.bookingId && ` · Booking #${entry.bookingId}`}
                </span>
                <span>{new Date(entry.timestamp).toLocaleString('vi-VN')}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
