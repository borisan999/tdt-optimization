# Operational Constraints & Performance

To ensure a responsive user experience and system stability, the TDT Optimization System operates within defined performance boundaries.

## 1. Solver Constraints (MILP)

The system uses the COIN-OR Branch-and-Cut (CBC) solver via the PuLP library. The following constraints are enforced in `app/python/10/optimizer_canonical.py`:

| Parameter | Value | Rationale |
| :--- | :--- | :--- |
| **Time Limit** | 20 Seconds | Prevents PHP process hanging and ensures timely HTTP responses. |
| **Optimality Gap** | 5.0% | Allows the solver to stop once a solution is found that is within 5% of the theoretical optimum. |
| **Parallelism** | 4 Threads | Optimized for multi-core server environments. |

### Impact on Solution Quality
Validation tests confirm that the 5% gap tolerance typically results in an objective value deviation of less than 2% compared to high-precision runs (0% gap), while reducing computation time by up to 80%.

## 2. Concurrency Protection

The system is hardened against simultaneous optimization triggers for the same dataset:

- **Row-Level Locking:** `SELECT ... FOR UPDATE` is used during the initialization of any optimization.
- **Process Deduplication:** If an optimization is already in the `running` or `queued` status for a specific dataset, subsequent requests will "attach" to the existing process rather than spawning a new solver instance.
- **Session Locking:** PHP session writes are closed (`session_write_close()`) before spawning the Python solver to prevent blocking the UI for other tabs/requests while calculations are in progress.

## 3. Computational Limits

- **Max Floors:** Theoretically unlimited, but UI responsiveness is optimized for buildings up to 100 floors.
- **Max TUs:** Systems with >1000 TUs may approach the 20-second time limit, potentially resulting in "Sub-optimal" but feasible results.

## 4. Input Requirements

- **No Merged Cells:** Excel imports must contain explicit values for every row. Merged cells are treated as missing data and will trigger a validation error.
- **Valid Catalogs:** All equipment used in manual entry or Excel must exist in the system catalogs (managed via the Configurations dashboard).
