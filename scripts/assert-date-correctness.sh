#!/usr/bin/env bash
set -euo pipefail

# Use grep (POSIX, present on every CI runner and git-bash) rather than ripgrep,
# which is not installed on GitHub-hosted runners. \s is a GNU extension, so the
# patterns use [[:space:]] to stay portable across grep implementations.

if grep -rE "toISOString\(\).*split\(['\"]T['\"]\)" frontend/src; then
  echo "Do not derive hostel-local booking dates from toISOString(). Use getHostelToday()."
  exit 1
fi

if grep -rE "toISOString\(\)\.slice\(0,[[:space:]]*10\)" frontend/src; then
  echo "Do not derive hostel-local booking dates from toISOString(). Use getHostelToday()."
  exit 1
fi

if grep -rE "after_or_equal:today" backend/app backend/routes; then
  echo "Do not use after_or_equal:today for booking rules. Use HostelClock."
  exit 1
fi

if grep -rE "whereDate\([[:space:]]*['\"]scheduled_(check_in|check_out)_at" backend/app; then
  echo "Do not bucket UTC instant columns (scheduled_*_at) with whereDate(). Use HostelClock::localDateRangeAsUtc() + whereBetween."
  exit 1
fi

if grep -rE "where(Month|Year)\([[:space:]]*['\"](created_at|updated_at)['\"]" backend/app; then
  echo "Do not bucket UTC instant columns (created_at/updated_at) with whereMonth/whereYear + now(). Use a HostelClock UTC range."
  exit 1
fi
