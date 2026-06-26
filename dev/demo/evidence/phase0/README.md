# Phase 0 evidence

Capture the baseline **on a live environment** (Magento + ChromaDB + indexed Luma catalog).
The agent cannot run Warden/Magento, so Navin runs these and the JSON lands here.

## Steps (Navin)

```bash
# 1. Load the Luma catalog (prints the exact sequence; run it):
bash app/code/NavinDBhudiya/ProductRecommendation/dev/demo/install-sample-data.sh

# 2. Enable + index the module (inside the PHP container):
bin/magento module:enable NavinDBhudiya_ProductRecommendation
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento recommendation:test          # confirm ChromaDB + embedding service reachable
bin/magento recommendation:index         # index the catalog into the vector store

# 3. Capture the baseline:
bin/magento recommendation:demo:baseline
#   -> writes dev/demo/evidence/phase0/baseline.json
```

## Expected shape of `baseline.json`

```json
{
  "catalog": "Magento Luma sample data",
  "metric": "hit-rate@10",
  "slot": "related",
  "store_id": null,
  "k": 10,
  "pairs_total": 18,
  "pairs_evaluated": 16,
  "pairs_unresolved": 2,
  "hits": 7,
  "misses": 9,
  "hit_rate_at_10": 0.4375,
  "p50_latency_ms": 142.5,
  "p95_latency_ms": 310.8,
  "unresolved_skus": ["..."],
  "per_pair": [ { "anchor_sku": "...", "expected_sku": "...", "resolved": true, "hit": true, "latency_ms": 138.2, "top_skus": ["..."] } ]
}
```

> The numbers above are an **illustrative shape only** — real values come from the live run.
> The metric math (`Service/Metrics/BaselineCalculator.php`) is unit-tested, so the same method
> yields comparable, reproducible numbers on demo and production traffic.
