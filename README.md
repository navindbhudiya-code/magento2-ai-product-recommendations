# NavinDBhudiya_ProductRecommendation

AI-powered product recommendations for Magento 2 — semantic *related*, *cross-sell*, and
*up-sell* suggestions driven by vector embeddings, with optional LLM re-ranking and
behaviour-based personalization.

> This README describes **only what the code does today**. Planned and partially-built
> features live in [ROADMAP.md](ROADMAP.md); an honest feature-by-feature breakdown is in
> [dev/demo/AUDIT.md](dev/demo/AUDIT.md).

## What it does

- **Semantic related / cross-sell / up-sell** via nearest-neighbour search over product
  embeddings, injected into the native Magento blocks through plugins.
- **Pluggable vector store** behind `Api/VectorStoreInterface`:
  - **ChromaDB** (existing).
  - **Search Engine** — your store's OpenSearch/Elasticsearch via k-NN (**no extra infra**).
- **Pluggable embeddings** behind `Api/EmbeddingProviderInterface`:
  - **Hosted API** (OpenAI-compatible, e.g. `text-embedding-3-small`) — recommended default.
  - **ChromaDB embedding-service** (self-hosted Python, `all-MiniLM-L6-v2`).
- **Never-empty block**: a fallback chain (primary → same-category → Magento native) keeps the
  slot populated even when the AI backend is down (`Service/Fallback/FallbackSelector`).
- **Personalized recommendations** (browsing / purchase / wishlist / "Just for you") with
  REST + GraphQL APIs.
- **Optional LLM re-ranking** (Claude / OpenAI) — **off by default**.

## Requirements

- Magento **2.4.6–2.4.8**, PHP **8.1–8.3**.
- A vector store: either OpenSearch/Elasticsearch (already required by Magento) **or** ChromaDB.
- For hosted embeddings: an API key (OpenAI/Voyage/compatible).

## Install in 3 minutes (no extra infra)

Uses your existing OpenSearch + a hosted embeddings API — no ChromaDB, no Python container.

```bash
# 1. Add the module (or copy into app/code/NavinDBhudiya/ProductRecommendation)
bin/magento module:enable NavinDBhudiya_ProductRecommendation
bin/magento setup:upgrade
bin/magento setup:di:compile

# 2. Configure (Stores > Configuration > NavinDBhudiya > AI Product Recommendation):
#    - Embedding > Embedding Provider = "Hosted API"; set API key + model (text-embedding-3-small)
#    - Vector Store > Backend = "Search Engine (OpenSearch/Elasticsearch k-NN)"
#      (set host/port if not the defaults opensearch:9200)

# 3. Index + verify
bin/magento recommendation:index
bin/magento recommendation:health     # expect green + X/Y coverage
```

Prefer ChromaDB? Set Vector Store = ChromaDB and Embedding Provider = ChromaDB, then run the
embedding-service container (see `docker/`) — see [CLAUDE.md](CLAUDE.md) for the ChromaDB path.

## CLI commands

All commands use the `recommendation:*` namespace.

| Command | Purpose |
|---|---|
| `recommendation:health` | Ping embedding provider + vector store; show index coverage. |
| `recommendation:index` | Embed and store the catalog. |
| `recommendation:test` | Test the ChromaDB / embedding-service connection. |
| `recommendation:similar <id>` | Show similar products for a product (or `--query`). |
| `recommendation:clear` | Clear the vector collection. |
| `recommendation:personalized` | Personalized recommendations for a customer. |
| `recommendation:trending:refresh` | Refresh the trending table. |
| `recommendation:refresh-profiles` | Refresh customer behaviour profiles. |
| `recommendation:demo:baseline` | Measure hit-rate@10 + latency over the ground-truth pairs. |

## Architecture

```
Product save / recommendation:index
        │
        ▼
EmbeddingProviderInterface  ──►  VectorStoreInterface  (ChromaDB | Search Engine k-NN)
 (Hosted API | ChromaDB)
        ▲                                 │
        │                                 ▼
PDP / cart blocks ──► RecommendationService ──► nearest neighbours
                              │
                              ├─ optional LLM re-rank (off by default)
                              └─ FallbackSelector: primary → same-category → native
```

- `Api/VectorStoreInterface` — `upsert / query / delete / count / ping`. Implementations:
  `Service/VectorStore/ChromaVectorStore`, `Service/VectorStore/SearchEngineVectorStore`
  (selected by config via `VectorStoreFactory`).
- `Api/EmbeddingProviderInterface` — implementations: `ApiEmbeddingProvider`,
  `ChromaDBEmbeddingProvider` (selected via `EmbeddingProviderFactory`).
- REST: `etc/webapi.xml`. GraphQL: `etc/schema.graphqls`.

## APIs

- **REST** — e.g. `GET /V1/recommendation/personalized/justforyou`.
- **GraphQL** — `personalizedRecommendations(type: JUST_FOR_YOU, limit: 8) { items { sku name } }`.

## Testing

No Warden required:

```bash
bash dev/demo/run-tests.sh            # unit + integration + phpcs
bash dev/demo/run-tests.sh --unit
composer test                          # if composer is available
```

See [CLAUDE.md](CLAUDE.md) for the PHPUnit-10 / Magento classmap caveat and the test launcher.

## License

MIT. Author: Navin Bhudiya.
