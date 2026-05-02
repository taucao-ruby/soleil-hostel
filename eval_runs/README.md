# Eval Runs

Per-batch audit trail for prompt-driven engineering work.

## Layout

```
eval_runs/
  schema/
    manifest.schema.json   # JSON Schema enforced by CI
  batch-N/
    manifest.json          # one per batch — fields documented in schema
```

## Manifest contract

Every PR labelled `prompt-batch-generated` must include exactly one new
`eval_runs/batch-<N>/manifest.json` file. The manifest is the audit
record that lets a future auditor reconstruct:

- which model produced the work
- which finding IDs the batch addressed
- the SHA-256 fingerprint of the engineering brief for each finding
  (or a `commit:<sha>` pointer for findings whose brief is not retained)
- the git SHA at which the work landed
- how many new tests were added and how many of those pass

CI enforcement lives in `.github/workflows/batch-manifest-check.yml`.
The job fails if:

- no `eval_runs/batch-*/manifest.json` is added on a PR with the
  `prompt-batch-generated` label
- the manifest is not well-formed JSON
- the manifest fails schema validation
- `tests_passing` is less than `tests_added` (un-signed regression)

## Adding a new batch

1. Pick the next sequential `batch-N` number.
2. Create `eval_runs/batch-N/manifest.json`. Copy from a recent batch
   and edit fields. Required fields are listed in the schema.
3. Compute prompt hashes:
   - For prompts you have in hand: `printf '%s' "<prompt-text>" | sha256sum`
   - For findings whose brief is no longer reachable: use
     `commit:<short-sha>` of the originating commit.
4. Run the local tests, count new ones, fill in `tests_added` /
   `tests_passing`.
5. Have a human reviewer fill in `sign_off`.
6. Open the PR, label it `prompt-batch-generated`, push.

## Schema evolution

Schema changes go in `eval_runs/schema/manifest.schema.json`. Bump
`$id` if the change is breaking. CI uses the file at the schema path
in the same commit, so backward-incompatible changes do not retroactively
invalidate old manifests.
