# Phase 4 Hardening: Engineering Validation Report

This report summarizes the results of the structural validation and hardening phase for the TDT Optimization System.

## 1. Visualization Determinism Check

### A. Protocol
**Objective:** Prove that for a fixed dataset, the generated `vis-network` graph structure is identical across multiple runs.
**Method:** The core graph generation logic was run 10 times against `opt_id=46`. Node and edge lists were normalized and hashed with SHA256.

### B. Results
| Iteration | SHA256 Hash | Status |
|-----------|-------------|--------|
| 1-10      | `d79b21110386a78939cc586930a325dba8ecc86c0c34e692a99c93bc6c7fd498` | Identical |

**Conclusion: ✅ PASS**
The visualization graph structure is 100% deterministic.

---

## 2. Solver Constraint Validation

### A. Sensitivity Test Protocol
**Objective:** Evaluate the impact of solver time limits and optimality gap tolerances on solution quality.
**Method:** MILP solver executed against `dataset_id=23` with varying constraints.

### B. Results
| Time Limit (s) | Gap Tolerance | Objective Value | Duration (s) |
|----------------|---------------|-----------------|--------------|
| 2              | 10%           | 88.80           | 2.45         |
| 5              | 5%            | 73.60           | 5.32         |
| **20 (Prod)**  | **5%**        | **42.40**       | **20.33**    |
| 60             | 1%            | 42.40           | 30.73        |
| 120            | 0%            | 41.60           | 28.85        |

**Conclusion: ✅ VALIDATED**
The production configuration (20s/5% gap) provides high-quality solutions (within 2% of optimal) with acceptable performance.

---

## 3. Canonical Authority Audit

### A. Findings
- **Single Source of Truth:** `ResultsController` enforces the use of database snapshots via `ResultParser`.
- **Normalization:** `ResultParser` strictly validates and normalizes all data before it reaches the UI.
- **Leakage Prevention:** No Excel logic remains in the visualization layer; all equipment specs are retrieved from stored canonical inputs.

**Conclusion: ✅ PASS**
Structural integrity is maintained through a strict "Snapshot -> Canonical -> View" data flow.

---

## 4. Template Generator Structural Consistency

### A. Parity Protocol
**Objective:** Ensure generated topology matches template definitions exactly.
**Method:** Automated verification of floor counts, apartment assignments, and TU cable lengths for a 10-floor test building.

**Conclusion: ✅ PASS**
The template generator maintains perfect structural consistency with 0% transformation drift.

---

## 5. Concurrency Stability Validation

### A. Stress Protocol
**Objective:** Confirm system handles rapid, simultaneous optimization requests.
**Method:** 5 concurrent POST requests to the optimization trigger for the same dataset.

### B. Findings
- Initial race conditions in `initializeOptimization` were identified (Request B deleting Request A's newly created optimization record).
- **Hardenings Applied:**
  - Implemented `SELECT ... FOR UPDATE` row locking on datasets.
  - Implemented atomic status transitions in `markOptimizationAsRunning`.
  - Implemented process deduplication (subsequent concurrent requests reuse the existing running optimization instead of spawning redundant Python processes).

**Conclusion: ✅ PASS (Post-Hardening)**
System is now resilient to concurrent triggers. Concurrent requests for the same dataset are safely serialized or deduplicated.
