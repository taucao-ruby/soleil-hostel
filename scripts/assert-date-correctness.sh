#!/usr/bin/env bash
set -euo pipefail

if ! command -v rg >/dev/null 2>&1; then
  echo "ripgrep (rg) is required for date correctness guardrails."
  exit 1
fi

if rg "toISOString\(\).*split\(['\"]T['\"]\)" frontend/src; then
  echo "Do not derive hostel-local booking dates from toISOString(). Use getHostelToday()."
  exit 1
fi

if rg "toISOString\(\)\.slice\(0,\s*10\)" frontend/src; then
  echo "Do not derive hostel-local booking dates from toISOString(). Use getHostelToday()."
  exit 1
fi

if rg "after_or_equal:today" backend/app backend/routes; then
  echo "Do not use after_or_equal:today for booking rules. Use HostelClock."
  exit 1
fi

if rg "whereDate\(\s*['\"]scheduled_(check_in|check_out)_at" backend/app; then
  echo "Do not bucket UTC instant columns (scheduled_*_at) with whereDate(). Use HostelClock::localDateRangeAsUtc() + whereBetween."
  exit 1
fi

if rg "where(Month|Year)\(\s*['\"](created_at|updated_at)['\"]" backend/app; then
  echo "Do not bucket UTC instant columns (created_at/updated_at) with whereMonth/whereYear + now(). Use a HostelClock UTC range."
  exit 1
fi
