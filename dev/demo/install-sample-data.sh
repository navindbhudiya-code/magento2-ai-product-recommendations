#!/usr/bin/env bash
#
# NavinDBhudiya ProductRecommendation — Luma sample-data install helper.
#
# This script PRINTS the commands needed to install the Magento Luma sample data
# (the canonical ~2000-product dataset the recommendation demo runs against).
# It does NOT execute them: sampledata:deploy + setup:upgrade are heavy, mutate the
# database, and must be run by Navin inside the PHP container. Run the printed lines
# yourself (e.g. via `warden shell`) — this script never touches Warden or the DB.
#
# Usage:
#   bash dev/demo/install-sample-data.sh
#
set -euo pipefail

cat <<'INSTRUCTIONS'
============================================================================
 Install Magento Luma sample data (run these inside the PHP-FPM container)
============================================================================

# 1. Deploy the sample-data composer packages (needs Magento Marketplace auth keys
#    in auth.json; this downloads ~2000 Luma products, categories, and attributes):
bin/magento sampledata:deploy

# 2. Apply the schema/data the sample-data packages add:
bin/magento setup:upgrade

# 3. Recompile DI and reindex so the catalog is queryable:
bin/magento setup:di:compile
bin/magento indexer:reindex
bin/magento cache:flush

# 4. Verify the catalog loaded (Luma adds a few thousand products):
#    Admin > Catalog > Products, or:
bin/magento catalog:images:resize >/dev/null 2>&1 || true  # optional, warms product images

----------------------------------------------------------------------------
 Next: index into the vector store and capture the Phase 0 baseline
----------------------------------------------------------------------------
bin/magento recommendation:test          # ChromaDB + embedding service reachable?
bin/magento recommendation:index         # embed + store the catalog
bin/magento recommendation:demo:baseline # -> dev/demo/evidence/phase0/baseline.json

NOTE: These are printed for you to run. This agent does not run Warden, the
Magento installer, or any destructive command.
============================================================================
INSTRUCTIONS
