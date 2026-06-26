# Integration tests

This directory holds the module's integration suite.

## Current state (Phase 0)

`SmokeTest.php` is a **dependency-free** smoke test: it asserts the module's structural
contracts (registration, module declaration, console-command registration) **without**
bootstrapping Magento, a database, ChromaDB, or the search engine. This keeps
`bash dev/demo/run-tests.sh` green on any host from Phase 0 onward.

Run it standalone:

```bash
bash dev/demo/run-tests.sh --integration
```

## Roadmap (Phase 1+)

Real, DB-backed integration tests will run under **Magento's integration test framework**
(`dev/tests/integration/`), which boots the application and a test database. Planned coverage:

- **Phase 1** — index Luma into the search-engine vector store; query `known_pairs.json`;
  assert hit-rate@10 ≥ Phase 0 baseline. Resilience: vector store down → block still renders
  via fallback and logs `served_by=fallback`.
- **Phase 2** — seed events (`--seed=42`), run roll-up cron, assert dashboard numbers match
  `dev/demo/evidence/phase2/expected_stats.json` exactly; consent-off writes nothing.
- **Phase 3** — two store views return locale-correct recommendations (no cross-locale bleed);
  GDPR export/erase/retention.

Those tests extend `Magento\TestFramework\TestCase\AbstractController` /
`PHPUnit\Framework\TestCase` with the integration bootstrap and are invoked via the Magento
integration `phpunit.xml`, not this skeleton config. They require a running environment, so
they are executed by Navin (commands printed per phase), never via Warden from this agent.
