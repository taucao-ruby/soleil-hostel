import { onCLS, onINP, onFCP, onLCP, onTTFB, type Metric } from 'web-vitals'

/**
 * Web Vitals Monitoring
 *
 * Tracks Core Web Vitals metrics and reports them to console
 * (or analytics service in production)
 *
 * Metrics tracked:
 * - CLS: Cumulative Layout Shift
 * - FID: First Input Delay
 * - FCP: First Contentful Paint
 * - LCP: Largest Contentful Paint
 * - TTFB: Time to First Byte
 */

function sendToAnalytics(metric: Metric): void {
  // In production, send to your analytics service
  // Example: Google Analytics, Vercel Analytics, or custom endpoint

  if (process.env.NODE_ENV === 'development') {
    console.log(`[Web Vitals] ${metric.name}:`, metric.value, metric)
  }

  // TODO: Send to analytics service
  // Example for Google Analytics:
  // gtag('event', metric.name, {
  //   value: Math.round(metric.name === 'CLS' ? metric.value * 1000 : metric.value),
  //   event_category: 'Web Vitals',
  //   event_label: metric.id,
  //   non_interaction: true,
  // });

  // Example for custom endpoint:
  // fetch('/api/analytics/web-vitals', {
  //   method: 'POST',
  //   body: JSON.stringify(metric),
  //   headers: { 'Content-Type': 'application/json' },
  // });
}

/**
 * Initialize Web Vitals monitoring
 * Call this once in your application entry point (main.tsx)
 *
 * Note: FID has been replaced by INP (Interaction to Next Paint) in web-vitals v3+
 */
export function initWebVitals(): void {
  onCLS(sendToAnalytics)
  onINP(sendToAnalytics) // Replaces FID in web-vitals v3+
  onFCP(sendToAnalytics)
  onLCP(sendToAnalytics)
  onTTFB(sendToAnalytics)
}

/**
 * Web Vitals thresholds for scoring
 * Good: <= good threshold
 * Needs Improvement: between good and poor
 * Poor: >= poor threshold
 */
export const WEB_VITALS_THRESHOLDS = {
  CLS: { good: 0.1, poor: 0.25 },
  INP: { good: 200, poor: 500 }, // Replaces FID in web-vitals v3+
  FCP: { good: 1800, poor: 3000 },
  LCP: { good: 2500, poor: 4000 },
  TTFB: { good: 800, poor: 1800 },
}

/**
 * Get performance rating based on metric value
 */
export function getPerformanceRating(
  metricName: string,
  value: number
): 'good' | 'needs-improvement' | 'poor' {
  const thresholds = WEB_VITALS_THRESHOLDS[metricName as keyof typeof WEB_VITALS_THRESHOLDS]

  if (!thresholds) return 'good'

  if (value <= thresholds.good) return 'good'
  if (value >= thresholds.poor) return 'poor'
  return 'needs-improvement'
}
