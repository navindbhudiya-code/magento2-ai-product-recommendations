# Code Audit — NavinDBhudiya_ProductRecommendation (Phase 0)

> Baseline reconnaissance for the productization playbook
> (`.claude/docs/AI-RECO-PRODUCTIZATION-TASKS.md`). Honest classification of what the
> code does **today**, before any Phase 1+ work. Module version: `2.1.0` (`etc/module.xml`).

## Method

Walked `Api/`, `Service/`, `Model/`, `Console/Command/`, `Plugin/`, `Observer/`, `Cron/`, and
`etc/`. "Implemented" = class exists **and** is wired into a runtime path (DI, plugin, controller,
cron, or resolver). "Partial" = code exists but integration into the serving path is incomplete or
unverified. "Missing" = README/marketing claim with no backing code.

## Command namespace — DECISION

**Canonical namespace = `recommendation:*`.** This matches the actual `setName()` values in
`Console/Command/*`, `etc/di.xml` registration, README, and the module `CLAUDE.md`. The
`productrecommendation:*` form referenced elsewhere is **not** used by any command and is
rejected. Registered commands:

| Command | Class |
|---|---|
| `recommendation:index` | `IndexProducts` |
| `recommendation:test` | `TestConnection` |
| `recommendation:similar` | `GetSimilarProducts` |
| `recommendation:clear` | `ClearCollection` |
| `recommendation:personalized` | `GetPersonalizedRecommendations` |
| `recommendation:refresh-profiles` | `RefreshProfiles` |
| `recommendation:trending:refresh` | `RefreshTrending` |

> Note: `recommendation:similar` and `recommendation:personalized` were missing from `di.xml` in
> the working tree on entry; restored in commit `chore: reconcile working tree` (classes existed).

## Feature classification

| Feature (README / playbook) | Status | Evidence / notes |
|---|---|---|
| Vector similarity (ChromaDB) | **Implemented** | `Service/ChromaClient.php` (v0.4/v0.5 API), `Api/ChromaClientInterface.php`. |
| Embedding generation | **Implemented** | `Service/Embedding/ChromaDBEmbeddingProvider.php` (all-MiniLM-L6-v2, 384-dim) via Python embedding-service. Only one provider. |
| Related / Cross-sell / Up-sell | **Implemented** | `Service/RecommendationService.php` + `Plugin/Product/RelatedProducts.php`, `UpsellProducts.php`, `Plugin/Checkout/CrosssellProducts.php`. |
| Fallback to native Magento | **Implemented** | `Plugin/Product/RelatedProducts.php:136` ("Fallback to native if configured"), gated by `Config::isFallbackToNativeEnabled()`. Phase 1 will extend to a full graceful chain (store-down / cold-start) + `served_by` logging. |
| Smart caching | **Implemented** | `Model/Cache/Type/Recommendation.php`, in-memory + cache layers in `RecommendationService`; cleared on product save. |
| LLM re-ranking | **Implemented (off by default)** | `Service/LlmReRanker.php`, providers `Service/Llm/ClaudeProvider.php` + `OpenAiProvider.php`, results cached in `..._llm_ranking` table. `config.xml` ships `llm_reranking/enabled=0`. Not yet test-covered. |
| Circuit breaker | **Partial** | `Service/CircuitBreaker.php` exists and is referenced by `Service/Llm/ClaudeProvider.php` only (not `OpenAiProvider`). No test exercises the open/half-open/closed transitions — playbook Phase 4 requires this. |
| Diversity filter | **Partial** | `Service/DiversityFilter.php` exists and is DI-declared, but is **not injected into `RecommendationService`** (the related/crosssell/upsell serving path). Effectively inert for those slots. |
| Trending boost | **Partial** | `Service/TrendingBooster.php` + `Cron/RefreshTrending.php` + `recommendation:trending:refresh` maintain the `..._trending_products` table, but the booster is **not applied** in the recommendation serving path. Data is collected; ranking impact unconfirmed. |
| Personalized recommendations | **Implemented** | `Service/PersonalizedRecommendationService.php` (browsing/purchase/wishlist/just-for-you), behavior collectors, `..._customer_profile` / `..._personalized_recommendations` / `..._guest_browsing_history` tables, profile-refresh cron. |
| REST API | **Implemented** | `etc/webapi.xml` (7 routes) → `Model/PersonalizedRecommendationManagement.php`. |
| GraphQL | **Implemented** | `etc/schema.graphqls` + `Model/Resolver/PersonalizedRecommendations.php`. |
| Admin config UI + test-connection | **Implemented** | `etc/adminhtml/system.xml`, `Block/Adminhtml/System/Config/TestConnection.php`, `Controller/Adminhtml/System/Config/TestConnection.php`. |
| GDPR / consent / privacy | **Missing (not claimed)** | No `gdpr`/`consent`/`privacy`/`erase` code. README does **not** claim it, so no false claim to correct — but the playbook Phase 3 must add real export/erase/retention. |
| Analytics dashboard / A-B testing | **Missing** | No event table, roll-ups, or dashboard. Entire scope of playbook Phase 2. |
| Pluggable vector store / hosted embeddings | **Missing** | No `VectorStoreInterface`, no `SearchEngineVectorStore`, no `ApiEmbeddingProvider`. Hard ChromaDB + Python dependency. Scope of playbook Phase 1. |
| Health check / index coverage CLI | **Missing** | No `recommendation:health`. Scope of playbook Phase 1. |
| Multi-store / locale isolation | **Partial** | Tables carry `store_id`; no per-locale vector-space separation. Scope of playbook Phase 3. |

## Test harness state

- **Unit tests:** 1 file, `Test/Unit/Helper/ConfigTest.php` (13 tests, 27 assertions). Passes.
- **Integration tests:** none.
- **CI / phpcs / phpstan:** none configured.

### Host caveat — PHPUnit 10 vs Magento `exclude-from-classmap` (IMPORTANT)
The Magento root `composer.json` ships the standard
`"exclude-from-classmap": ["**/dev/**", "**/update/**", "**/Test/**"]`. Magento 2.4.x targets
PHPUnit **9.5**, but this checkout has PHPUnit **10.5.52** installed, whose event classes live
under `vendor/phpunit/phpunit/src/Event/Events/Test/Lifecycle/*Subscriber.php`. The `**/Test/**`
glob strips them from the optimized classmap, so `vendor/bin/phpunit` cannot boot
(`Subscriber "...DataProviderMethodCalledSubscriber" does not exist or is not an interface`).
This breaks **all** Magento unit tests on this host, not just this module.

**Workaround shipped in this module (commit `test: harness`):** `dev/demo/phpunit-launcher.php`
registers a fallback autoloader recovering the omitted PHPUnit classes (and maps the module's
PSR-4 namespace for isolated runs), then runs PHPUnit normally — touching nothing on the host.
Use `dev/demo/run-tests.sh`. With it, the suite runs green.

## phpcs debt

`magento/magento-coding-standard` (Magento2 ruleset) — see `phpcs.xml` and `composer phpcs`.

**Recorded debt (baseline, do NOT fix in Phase 0):**
Under the project ruleset `phpcs.xml` (excludes `view/`, `dev/demo/`, `docker/`):
`A TOTAL OF 12 ERRORS AND 111 WARNINGS WERE FOUND IN 36 FILES`.
(A raw `--standard=Magento2` scan of the whole tree reports 17 errors / 122 warnings / 37 files.)
Heaviest files: `Service/LlmReRanker.php`, `Service/DiversityFilter.php`,
`Service/RecommendationExplainer.php`, `Service/TrendingBooster.php`. Captured with
`bash dev/demo/run-tests.sh --phpcs` on PHP 8.4.10. New Phase 0 code is phpcs-clean.
Cleanup of the legacy debt is deferred to Phase 3 (CI gate).

## Baseline metrics (Phase 0.5)

`recommendation:demo:baseline` (added in commit `feat: baseline command`) computes
**hit-rate@10** and **p50 latency** over `dev/demo/fixtures/known_pairs.json` and writes
`dev/demo/evidence/phase0/baseline.json`. The metric math is pure and unit-tested
(`Service/Metrics/BaselineCalculator.php`); real numbers require a live Magento + ChromaDB +
indexed Luma catalog and must be captured by Navin (commands printed by the command + run-tests).
