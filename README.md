# TDT Optimization System

An engineering-grade web platform for the design, optimization, and validation of Television Digital Terrestre (TDT) distribution networks in residential and commercial buildings.

## 🚀 Core Features

- **Advanced Building Template Generator:** Rapidly model complex building topologies with floors, apartments, and user outlets (TUs).
- **MILP Optimization Engine:** Uses Mixed-Integer Linear Programming to select the most efficient equipment (derivadores/repartidores) while ensuring RITEL compliance.
- **Interactive Network Visualization:** Real-time, deterministic tree visualization of the signal distribution hierarchy.
- **Canonical Data Authority:** Strict data modeling ensures that optimization snapshots are immutable and authoritative for all views and exports.
- **Multilingual Support:** Full English and Spanish localization across all interfaces.
- **Professional Exports:** Generate engineering-ready reports in XLSX, CSV, and DOCX formats.

## 🛠 Tech Stack

- **Backend:** PHP 8.x (Vanilla/MVC)
- **Database:** MySQL 8.0+
- **Optimization:** Python 3.x with PuLP (CBC Solver)
- **Frontend:** Bootstrap 5, vis-network, Chart.js, Animate.css
- **Reporting:** PhpSpreadsheet, PHPWord

## 📂 Documentation

Detailed technical documentation is available in the `/docs` directory:

1.  **[Architecture Overview](docs/ARCHITECTURE.md):** Canonical data flow and system design.
2.  **[Operational Constraints](docs/OPERATIONAL_CONSTRAINTS.md):** Solver limits, gap tolerances, and concurrency protection.
3.  **[Deployment Guide](docs/DEPLOYMENT.md):** Server requirements and installation steps.
4.  **[Validation Report](docs/validation/phase4_validation_report.md):** Results of Phase 4 hardening and determinism tests.

## ⚖️ Usage Scope

This system is intended for professional use by telecommunications engineers. It focuses on the **Internal Distribution Network** (RITEL standard compliance) and assumes a valid signal source at the building headend.

---
© 2026 TDT Optimization Team.
