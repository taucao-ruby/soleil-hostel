// Ensure React is loaded before any component modules resolve —
// prevents intermittent "Cannot read properties of null (reading 'useState')"
// in Vitest/jsdom when React's shared internals haven't initialised yet.
import 'react'
import '@testing-library/jest-dom'
