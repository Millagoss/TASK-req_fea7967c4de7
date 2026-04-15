# questions.md

## 1. Semantic Versioning Trigger Rules

**Question:** The prompt requires semantic versioning (major.minor.patch) for songs, albums, and playlists, but never defines what type of change constitutes a major, minor, or patch increment. There is no domain standard for music catalog versioning, so any implementation without this definition would be arbitrary and likely wrong.
**Assumption:** A patch increment is triggered by metadata-only changes (e.g., tags, cover art), a minor increment by content changes (e.g., audio file replacement, duration update), and a major increment by identity-level changes (e.g., artist change, album reassignment).
**Solution:** Define a versioning policy table mapping field-level change categories to increment types, and enforce it in the update service before persisting any change.

---

## 2. Evaluation Cycle — Entity Definition

**Question:** The Rewards/Penalties module requires linkage to "evaluation cycles," but this entity is never defined anywhere in the prompt. Its attributes (name, date range, status, owner), creation mechanism (admin-created, auto-generated from calendar, event-triggered), and lifecycle are entirely absent. The schema cannot be designed without this definition.
**Assumption:** An evaluation cycle is an admin-created record with a name, start date, end date, and status (draft, active, closed). Reward and penalty records may only be created under an active cycle.
**Solution:** Create an `evaluation_cycles` table with `name`, `start_date`, `end_date`, and `status` columns. Restrict reward/penalty creation to active cycles and provide admin endpoints to manage the cycle lifecycle.

---

## 3. Leader Profile — Entity Definition

**Question:** The Rewards/Penalties module requires linkage to "leader profiles," but this entity is never defined. It is unclear whether a "leader" is a designated role within the existing RBAC system (a `users` FK), a separate standalone entity with its own attributes and table, or a composite of both. The FK target and schema structure cannot be determined without this.
**Assumption:** A "leader profile" refers to a user holding a specific leadership role (e.g., `team_leader`) in the RBAC system. No separate table is needed; the FK points to `users.id`, and the leadership designation is resolved through the role system.
**Solution:** Store the leader's `user_id` as a foreign key on reward/penalty records. Enforce at the service layer that the referenced user holds a leadership role before allowing the linkage.

---

## 4. HL7/FHIR Compliance Scope

**Question:** The result entry subsystem is described as "accepting HL7/FHIR-like field semantics." The word "like" creates a fundamental scope ambiguity: implementing a compliant FHIR REST server (with FHIR resource types, capability statements, SMART-on-FHIR, and bundle support) versus building a custom REST API that merely borrows FHIR field names (`code`, `unit`, `reference_range`, `observed_at`) are completely different efforts — one is a multi-month integration project, the other is a naming convention.
**Assumption:** The system is a custom REST API that uses FHIR Observation field names as its schema vocabulary. No FHIR protocol compliance, resource serialization, capability statements, or SMART auth is required.
**Solution:** Document the API as "FHIR-inspired field naming only." Define the accepted JSON schema using FHIR Observation field names but as a fully custom contract. Do not implement FHIR conformance resources or protocol-level compliance.

---

## 5. Z-Score Outlier — Statistical Population

**Question:** Result entries are flagged as outliers using a z-score threshold, but the population used to compute the mean and standard deviation is never defined. Computing z-scores per-measurement-code system-wide, per-subject, or per-evaluation-period produces materially different statistical results and completely different query designs. Choosing the wrong population would cause the flagging algorithm to produce incorrect outcomes.
**Assumption:** Z-scores are computed against all finalized records system-wide for the same measurement `code`. If fewer than 30 finalized records exist for a given code, z-score flagging is skipped and the entry is accepted without outlier review.
**Solution:** On submission, query `AVG(value)` and `STDDEV(value)` from finalized records with the same `code`. Skip the check when `COUNT(*) < 30`. Flag when `|z| >= threshold` and set the entry to `pending_review`, blocking finalization until a reviewer signs off.

---

## 6. Rewards/Penalties Status Transition Authority

**Question:** Three status transitions are defined — `active`, `appealed`, `cleared` — but the prompt never specifies which roles are authorized to trigger each transition. Without this, the RBAC middleware guards cannot be implemented. Assigning the wrong role to a transition (e.g., allowing a subject to clear their own penalty, or an admin to mark a record as appealed on behalf of a subject) produces an incorrect and potentially unsafe authorization model.
**Assumption:** Only the subject (or their assigned leader) can trigger `active → appealed`. Only an authorized admin can approve `appealed → cleared` or reject an appeal back to `active`. No other transitions are permitted.
**Solution:** Implement a state machine with explicit allowed transitions and role guards: `{ active: [appealed], appealed: [cleared, active], cleared: [] }`. Enforce role checks in middleware before any state mutation and log all transitions to the audit table with `actor_id` and timestamp.
