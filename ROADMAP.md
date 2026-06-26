# Roadmap

Features that are **planned or partially built** — kept out of the README so the README only
describes what the code does today. Tracked against the productization playbook
(`.claude/docs/AI-RECO-PRODUCTIZATION-TASKS.md`).

## Partially implemented (present but not fully wired)

- **Diversity filter** (`Service/DiversityFilter.php`) — exists, not injected into the
  related/cross-sell/up-sell serving path.
- **Trending boost** (`Service/TrendingBooster.php`) — maintains the trending table via cron,
  but the boost is not applied when serving recommendations.
- **Circuit breaker** (`Service/CircuitBreaker.php`) — used by the Claude LLM provider only;
  not yet exercised by a test.
- **Graceful fallback chain** — `Service/Fallback/FallbackSelector` decides the tier
  (primary → same-category → native); full integration into the serving path is in progress.

## Phase 2 — Proof: analytics + A/B testing

- Event tracking table + JS beacon + server-side cart/purchase attribution.
- Daily roll-ups (CTR, add-to-cart rate, attributed revenue) + admin dashboard.
- Deterministic A/B bucketing (AI vs Magento-native) with lift %.
- Reproducible demo data generator (`recommendation:demo:seed-events --seed=42`).

## Phase 3 — Trust: packaging, CI, multi-store/locale, GDPR

- Packagist release + GitHub Actions CI (phpcs + phpstan + tests, status badge).
- Per-store-view indexes and locale-aware embeddings (no cross-language vector bleed).
- Real GDPR: consent-gated tracking, retention cron, customer data export + erase.

## Phase 4 — Differentiation

- LLM re-ranking with cached human-readable reasons (behind a flag; breaker exercised).
- Natural-language merchandising rules compiled to deterministic boosts/filters.
- "Complete the look" bundles from co-purchase + semantic proximity.
- Cold-start via attribute-only embeddings for brand-new products.

See `dev/demo/AUDIT.md` for the current Implemented / Partial / Missing breakdown.
