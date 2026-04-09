import { useState, useRef, useEffect } from 'react'
import api from '@/shared/lib/api'

interface Citation {
  source_slug: string
  verified_at: string
}

interface AiResponse {
  response_class: string
  content: string
  citations: Citation[]
  failure_reason: string | null
}

interface Message {
  role: 'user' | 'assistant'
  content: string
  citations?: Citation[]
  responseClass?: string
}

const SUPPORT_CONTACT = 'support@soleilhostel.vn | Hotline: 0909-123-456'

export default function FaqAssistantWidget() {
  const [isOpen, setIsOpen] = useState(false)
  const [messages, setMessages] = useState<Message[]>([])
  const [input, setInput] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [hasError, setHasError] = useState(false)
  const messagesEndRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  const handleSend = async () => {
    const trimmed = input.trim()
    if (!trimmed || isLoading) return

    const userMessage: Message = { role: 'user', content: trimmed }
    setMessages(prev => [...prev, userMessage])
    setInput('')
    setIsLoading(true)

    try {
      const res = await api.post<{ success: boolean; data: AiResponse }>('/v1/ai/faq_lookup', {
        message: trimmed,
      })

      const data = res.data.data

      // Non-canary bypass response
      if ('canary' in data && !(data as Record<string, unknown>).canary) {
        const bypassMsg: Message = {
          role: 'assistant',
          content:
            ((data as Record<string, unknown>).message as string) ||
            'Vui lòng liên hệ bộ phận hỗ trợ.',
          responseClass: 'bypass',
        }
        setMessages(prev => [...prev, bypassMsg])
        setIsLoading(false)
        return
      }

      const assistantMsg: Message = {
        role: 'assistant',
        content: data.content,
        citations: data.citations,
        responseClass: data.response_class,
      }
      setMessages(prev => [...prev, assistantMsg])
    } catch {
      setHasError(true)
    } finally {
      setIsLoading(false)
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  // Error state: show support link only
  if (hasError) {
    return (
      <div className="fixed bottom-4 right-4 z-50">
        <div className="rounded-lg bg-white p-4 shadow-lg border border-gray-200 max-w-xs">
          <p className="text-sm text-gray-700 mb-2">Trợ lý AI tạm thời không khả dụng.</p>
          <p className="text-sm text-gray-500">Liên hệ hỗ trợ: {SUPPORT_CONTACT}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="fixed bottom-4 right-4 z-50">
      {/* Toggle button */}
      {!isOpen && (
        <button
          onClick={() => setIsOpen(true)}
          className="rounded-full bg-amber-500 p-3 text-white shadow-lg hover:bg-amber-600 transition-colors"
          aria-label="Mở trợ lý FAQ"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            className="h-6 w-6"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
            />
          </svg>
        </button>
      )}

      {/* Chat panel */}
      {isOpen && (
        <div
          className="w-80 sm:w-96 rounded-lg bg-white shadow-xl border border-gray-200 flex flex-col"
          style={{ maxHeight: '32rem' }}
        >
          {/* Header */}
          <div className="flex items-center justify-between bg-amber-500 text-white px-4 py-3 rounded-t-lg">
            <h3 className="font-semibold text-sm">Trợ lý chính sách Soleil Hostel</h3>
            <button
              onClick={() => setIsOpen(false)}
              className="text-white hover:text-gray-200"
              aria-label="Đóng"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                className="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>

          {/* Messages */}
          <div className="flex-1 overflow-y-auto p-3 space-y-3">
            {messages.length === 0 && (
              <p className="text-gray-400 text-sm text-center mt-4">
                Hỏi về chính sách hủy phòng, nhận/trả phòng, nội quy, tiện ích...
              </p>
            )}

            {messages.map((msg, i) => (
              <div
                key={i}
                className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
              >
                <div
                  className={`max-w-[85%] rounded-lg px-3 py-2 text-sm ${
                    msg.role === 'user' ? 'bg-amber-100 text-gray-800' : 'bg-gray-100 text-gray-800'
                  }`}
                >
                  <p className="whitespace-pre-wrap">{msg.content}</p>

                  {/* Citations */}
                  {msg.citations && msg.citations.length > 0 && (
                    <div className="mt-2 pt-2 border-t border-gray-200">
                      <p className="text-xs text-gray-500 font-medium">Nguồn:</p>
                      {msg.citations.map((c, ci) => (
                        <p key={ci} className="text-xs text-gray-500">
                          {c.source_slug} (xác minh: {c.verified_at})
                        </p>
                      ))}
                    </div>
                  )}

                  {/* Abstain: show support contact */}
                  {msg.responseClass === 'abstain' && (
                    <div className="mt-2 pt-2 border-t border-gray-200">
                      <p className="text-xs text-gray-500">Liên hệ hỗ trợ: {SUPPORT_CONTACT}</p>
                    </div>
                  )}
                </div>
              </div>
            ))}

            {isLoading && (
              <div className="flex justify-start">
                <div className="bg-gray-100 rounded-lg px-3 py-2 text-sm text-gray-500">
                  Đang xử lý...
                </div>
              </div>
            )}

            <div ref={messagesEndRef} />
          </div>

          {/* Input */}
          <div className="border-t border-gray-200 p-3">
            <div className="flex gap-2">
              <input
                type="text"
                value={input}
                onChange={e => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Nhập câu hỏi..."
                className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                disabled={isLoading}
              />
              <button
                onClick={handleSend}
                disabled={isLoading || !input.trim()}
                className="rounded-lg bg-amber-500 px-3 py-2 text-white text-sm hover:bg-amber-600 disabled:opacity-50 transition-colors"
              >
                Gửi
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
