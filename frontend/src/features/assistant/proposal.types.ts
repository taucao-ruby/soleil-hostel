/**
 * BookingActionProposal — surfaced by the AI harness.
 *
 * The model proposes. The harness validates. The human decides.
 * The existing service executes. The database constrains.
 */

export type ProposalActionType = 'suggest_booking' | 'suggest_cancellation'

export interface RiskAssessment {
  level: string
  factors: string[]
}

export interface BookingActionProposal {
  action_type: ProposalActionType
  proposed_params: Record<string, unknown>
  human_readable_summary: string
  policy_refs: string[]
  risk_assessment: RiskAssessment
  requires_confirmation: true
  proposal_hash: string
  generated_at: string
}

export interface ProposalDecisionResponse {
  proposal_hash: string
  decision: 'confirmed' | 'declined'
  downstream_result?: string
  message: string
}
