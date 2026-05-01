<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use Normalizer;

/**
 * Sanitizes untrusted text before it is injected into the model context.
 *
 * Defends against AI-001 (policy-document prompt injection) and is shared
 * with AI-002 (input-side prompt injection). The contract is intentionally
 * conservative: anything that could be parsed as a model control token,
 * an HTML element, or a Unicode steering character is removed before the
 * content is wrapped in a structurally quoted block.
 *
 * Wrapping alone is not sufficient — an attacker could break out of the
 * wrapper by smuggling a closing tag or a model-specific control token —
 * so sanitization must run before wrapping, and the system prompt must
 * additionally instruct the model to treat the wrapped block as data.
 */
class PromptSanitizerService
{
    /**
     * Sequences that look like model control tokens or system frames.
     * These are neutralized to avoid an attacker steering the model.
     *
     * @var list<string>
     */
    private const CONTROL_TOKENS = [
        '<|',
        '|>',
        '[INST]',
        '[/INST]',
        '<<SYS>>',
        '<</SYS>>',
        '###',
        '---SYSTEM---',
    ];

    /**
     * Replacement marker used when neutralizing control-token sequences.
     */
    private const CTRL_REDACTION = '[REDACTED-CTRL]';

    /**
     * Maximum length of a tag-like sequence we consider for stripping.
     * Matches the AI-001 spec regex `<[^>]{0,200}>`.
     */
    private const MAX_TAG_LENGTH = 200;

    /**
     * Sanitize raw policy content for safe injection into the model context.
     *
     * Order matters:
     *  1. NFC normalize so visually-equivalent codepoints collapse before
     *     the strip pass — otherwise an attacker can hide control chars
     *     behind decomposed forms.
     *  2. Strip Unicode control / formatting / bidi characters.
     *  3. Neutralize literal model control-token sequences. This must run
     *     BEFORE the tag-stripping pass: `<|...|>` looks like a tag to the
     *     tag stripper and would be eaten silently, hiding the attack from
     *     the redaction marker.
     *  4. Strip non-allowlisted HTML-like tags, keeping inner text.
     */
    public function sanitizePolicyContent(string $content): string
    {
        $content = $this->normalize($content);
        $content = $this->stripControlCharacters($content);
        $content = $this->neutralizeControlTokens($content);

        return $this->stripUnsafeTags($content);
    }

    /**
     * Wrap sanitized policy content in a structurally typed block.
     *
     * The model is instructed (via the system prompt) to treat the
     * contents of <policy_document> as guest-facing reference data
     * and never as instructions.
     */
    public function wrapAsPolicyDocument(
        string $sanitizedContent,
        string $slug,
        string $version,
        string $verifiedAt,
    ): string {
        $safeSlug = $this->sanitizeAttribute($slug);
        $safeVersion = $this->sanitizeAttribute($version);
        $safeVerifiedAt = $this->sanitizeAttribute($verifiedAt);

        return sprintf(
            "<policy_document slug=\"%s\" version=\"%s\" verified=\"%s\">\n%s\n</policy_document>",
            $safeSlug,
            $safeVersion,
            $safeVerifiedAt,
            $sanitizedContent,
        );
    }

    /**
     * Ratio of characters changed by sanitization, in [0.0, 1.0].
     *
     * Used by PolicyDocument::validateContent() to surface authoring-time
     * injection attempts: a benign Markdown document should sanitize to
     * essentially itself; an adversarial document will diff sharply.
     */
    public function diffRatio(string $raw, string $sanitized): float
    {
        $rawLength = mb_strlen($raw);

        if ($rawLength === 0) {
            return 0.0;
        }

        $sanitizedLength = mb_strlen($sanitized);
        $delta = abs($rawLength - $sanitizedLength);

        return min(1.0, $delta / $rawLength);
    }

    private function normalize(string $content): string
    {
        if (! class_exists(Normalizer::class)) {
            return $content;
        }

        $normalized = Normalizer::normalize($content, Normalizer::FORM_C);

        return $normalized === false ? $content : $normalized;
    }

    /**
     * Strip Unicode control / formatting / bidi codepoints.
     *
     * Ranges (per AI-001 spec):
     *  - U+0000–U+001F except U+0009 (TAB) and U+000A (LF)
     *  - U+200B–U+200F (zero-width + LRM/RLM)
     *  - U+202A–U+202E (bidi override)
     *  - U+2060–U+206F (word joiner, invisible operators)
     *  - U+FFF9–U+FFFF (interlinear / non-character)
     */
    private function stripControlCharacters(string $content): string
    {
        $pattern = '/[\x{0000}-\x{0008}\x{000B}-\x{001F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FFF9}-\x{FFFF}]/u';

        $stripped = preg_replace($pattern, '', $content);

        return $stripped ?? $content;
    }

    /**
     * Strip every tag-like sequence, keeping inner text.
     * Matches `<[^>]{0,200}>` (per AI-001 spec). Policy content is fed to
     * a model, never rendered in a browser, so no HTML element is "safe"
     * here — the inner text is what we want to preserve.
     */
    private function stripUnsafeTags(string $content): string
    {
        $pattern = '/<[^>]{0,'.self::MAX_TAG_LENGTH.'}>/u';

        $stripped = preg_replace($pattern, '', $content);

        return $stripped ?? $content;
    }

    private function neutralizeControlTokens(string $content): string
    {
        return str_replace(self::CONTROL_TOKENS, self::CTRL_REDACTION, $content);
    }

    /**
     * Strip characters that would break out of an XML-style attribute value.
     */
    private function sanitizeAttribute(string $value): string
    {
        $value = $this->normalize($value);
        $value = preg_replace('/[\x{0000}-\x{001F}<>"\']/u', '', $value) ?? '';

        return mb_substr($value, 0, 100);
    }
}
