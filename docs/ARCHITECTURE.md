# System Architecture Overview

The TDT Optimization System is built on a strict **Canonical Data Authority** model. This ensures that every calculation, visualization, and export is driven by a single, validated source of truth.

## 1. Data Hierarchy & Lifecycle

The system follows a linear progression of data:

1.  **Ingestion (Template/Excel):** Raw data is gathered via the UI Generator or uploaded Excel files.
2.  **Normalization:** The `CanonicalNormalizer` transforms raw inputs into a structured, lexicographically sorted JSON object.
3.  **Optimization Snapshot:** When an optimization is triggered, the current state of the dataset is "snapshotted" into the `optimizations` and `results` tables.
4.  **Authoritative Results:** All subsequent views (Viewer, Tree, Exports) consume the **Result Snapshot**, never the raw dataset. This prevents "semantic drift" if the dataset is modified after an optimization run.

## 2. Core Components

### ResultsController & ResultParser
The `ResultsController` is the primary gatekeeper for result data. It utilizes `ResultParser` to:
- Validate the integrity of the stored JSON.
- Rehydrate topology relations.
- Normalize numeric values to prevent floating-point drift.
- Produce a `ResultViewModel` which is the sole input for the UI layer.

### Visualization Engine (vis-network)
The network tree visualization is **deterministic**. Given the same result snapshot, the layout algorithm produces an identical graph structure every time. This is achieved by:
- Sorting nodes and edges by their canonical IDs (Bloque, Piso, Apto, TU) before rendering.
- Removing any dependencies on database insertion order.

## 3. Data Integrity & Validation

- **FOR UPDATE Locking:** During optimization, the system applies row-level locks on the dataset to prevent race conditions from concurrent users.
- **SHA256 Hashing:** Datasets are hashed upon storage. The system verifies this hash before running optimizations to detect and prevent data corruption.
- **Irreversible Doctrine:** Once a result is generated, it is immutable. Changes to building geometry require a new optimization run, which automatically expires the old snapshot.

## 4. Export Layer
Exports (XLSX, CSV, DOCX) are driven by the same `ResultViewModel` used by the web UI. This guarantees 100% parity between what the engineer sees on the screen and what is delivered in the final documentation.
