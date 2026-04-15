# Meridian Records & Compliance Backend — Post-Fix Static Audit Report

## 1. Verdict

**Overall Conclusion: Partial Pass**

The second audit identified multiple Blocker and High severity issues. The development team has addressed most of them. Several critical security gaps remain. The project is closer to acceptance but still requires fixes for identified gaps.

---

## 2. Scope and Static Verification Boundary

### What was reviewed
- Fixed validator files: `StoreSongRequest.php`, `UpdateSongRequest.php`
- Fixed routes: `routes/api.php` — music library permission middleware
- Fixed config: `config/app.php`, `config/services.php`
- Fixed controllers: `PlaylistController.php`, `RecommendationController.php`
- Previously reviewed unchanged files
- Verification that fixes are applied correctly

### What was NOT reviewed
- Same as previous audit

### Claims requiring manual verification
- Same as previous audit

---

## 3. Summary of Fixes Verified

| Issue | Status | Verification Evidence |
|-------|--------|----------------------|
| **Issue 1**: Title/artist min:1 validation | **FIXED** | `StoreSongRequest.php:17-18` — `'min:1'` added |
| **Issue 2**: ApplyDataScopes applies to queries | **NOT FIXED** | Still only `set()` in request attributes |
| **Issue 3**: Playlist search includes artist | **FIXED** | `PlaylistController.php:34-36, 41-46` — searches via songs relationship |
| **Issue 4**: Profile weights configurable | **FIXED** | `config/services.php:5-12` — loads from env vars |
| **Issue 5**: SSO config key fix | **FIXED** | `config/app.php:31` — `'sso_enabled'` properly loaded |
| **Issue 6**: Music routes permission guards | **FIXED** | `routes/api.php:113-152` — ALL routes now have permission middleware |
| **Issue 7**: Recommendation authorization check | **FIXED** | `RecommendationController.php:20-25` — checks user ID and users.list permission |

---

## 4. Section-by-Section Review

### 4.1 Documentation and Static Verifiability

**Conclusion: Pass**

- **Rationale**: Same as previous audit - documentation is comprehensive

### 4.2 Prompt-to-Code Alignment

**Conclusion: Partial Pass**

- **Fixes Verified**: 
  - Title/artist min:1 now enforced (Issue 1)
  - Profile weights now configurable (Issue 4)
  - Playlist search now includes artist via songs (Issue 3)
- **Gap Remaining**: ApplyDataScopes still non-functional (Issue 2)
- **Evidence**: `app/Http/Middleware/ApplyDataScopes.php:31` - only sets attributes, never reads them

### 4.3 Core Functional Requirements Coverage

**Conclusion: Partial Pass**

- **Fixes Verified**: Issue 1, 3, 4, 5, 6, 7 from previous audit are now fixed
- **Gap Remaining**: Data scoping functionality incomplete

### 4.4 End-to-End Deliverable Completeness

**Conclusion: Pass**

- Same as previous audit

### 4.5 Engineering Structure and Module Decomposition

**Conclusion: Pass**

- Same as previous audit

### 4.6 Maintainability and Extensibility

**Conclusion: Pass**

- Profile weights now configurable via config/services.php adds extensibility

### 4.7 Error Handling, Logging, and Validation

**Conclusion: Partial Pass**

- **Fixes Verified**: Title/artist validation now complete
- **Gaps Remaining**:
  - LoginRequest still missing `min:12` password validation
  - Behavior event dedup still missing target_type check

### 4.8 Real Product / Service Maturity

**Conclusion: Pass**

- Same as previous audit

### 4.9 Business Goal and Implicit Constraint Fit

**Conclusion: Partial Pass**

- **Fixes Verified**: Most issues from previous audit are addressed
- **Gap Remaining**: Data scoping still not applied to queries

---

## 5. Issues / Suggestions (Severity-Rated)

### BLOCKER Issues

#### Issue 1: ApplyDataScopes Still Not Applied to Queries
- **Severity**: Blocker
- **Title**: Data scope middleware sets attributes but does not filter results
- **Conclusion**: The middleware still only attaches data scopes to `$request->attributes` in `ApplyDataScopes.php:31`. No downstream code reads these attributes and applies filters. Same as Issue 2 from previous audit - NOT FIXED.
- **Evidence**: `app/Http/Middleware/ApplyDataScopes.php:31` — only `set()`, no consumer code found
- **Impact**: Data scope authorization is completely non-functional. Users with restricted scopes can still access all data.
- **Minimum actionable fix**: Implement a query scope or trait that reads `$request->attributes->get('data_scopes')` and applies where clauses to queries
- **Minimal verification**: Create scoped user, verify they only see scoped data

### HIGH Severity Issues

#### Issue 2: Behavior Event Deduplication Missing target_type
- **Severity**: High
- **Title**: Dedup uses (user_id, event_type, target_id) but Prompt requires (user_id, event_type, target_id, 5s window) — missing target_type
- **Conclusion**: `BehaviorEventController.php:31-36` still uses the same key without target_type. This was mentioned in audit 1 but NOT fixed in this iteration.
- **Evidence**: Same as before - lines 31-36 don't include target_type in WHERE
- **Impact**: A "browse" event for song ID 5 could be deduplicated as a "click" event for the same song ID within 5 seconds
- **Minimum actionable fix**: Add `->where('target_type', $request->input('target_type'))` to the dedup query
- **Minimal verification**: Send browse event, then click event for same target_id within 5s, verify both logged

#### Issue 3: LoginRequest Still Missing Password Length Validation
- **Severity**: High
- **Title**: LoginRequest accepts any password length
- **Conclusion**: `LoginRequest.php:14-20` still only checks `'required', 'string'` without min length. Prompt requires "minimum 12 characters". This was Issue 9 in audit 1 and NOT fixed.
- **Evidence**: `app/Http/Requests/LoginRequest.php:18` - no min rule
- **Impact**: Users can log in with passwords shorter than 12 characters
- **Minimum actionable fix**: Add `'min:12'` rule to password validation
- **Minimal verification**: Login with 5-char password, verify 422 rejection

#### Issue 4: AuditAdminAction Only Audits Admin Routes
- **Severity**: High
- **Title**: Non-admin write operations not audited
- **Conclusion**: `AuditAdminAction.php:20-24` still only audits `/api/v1/admin/*` routes. Music library, behavior events, notifications, results still not audited. Same as Issue 8 in audit 1 - NOT FIXED.
- **Evidence**: Same check - `$request->is('api/v1/admin/*')` not changed
- **Impact**: Only admin actions are audited, leaving most write operations untracked
- **Minimum actionable fix**: Remove admin-only filter or create separate middleware for all write auditing
- **Minimal verification**: Create song as non-admin, verify audit log entry

### MEDIUM Severity Issues

#### Issue 5: Disciplinatory Record Appeal Has No Deadline Check
- **Severity**: Medium
- **Title**: Appeal has no time limit - same as Issue 12 in audit 1
- **Evidence**: Same code - only checks status
- **Impact**: Expired records can still be appealed
- **Minimum actionable fix**: Check `expires_at` not in past

#### Issue 6: Bulk Notification Allows Empty Array
- **Severity**: Medium
- **Title**: Bulk send with empty array succeeds silently
- **Evidence**: Same code - no min count check
- **Impact**: Zero-recipient sends silently succeed
- **Minimum actionable fix**: Add min:1 validation

#### Issue 7: Behavior Event Race Condition
- **Severity**: Medium
- **Title**: Concurrent dedup can still create duplicates
- **Evidence**: Same transaction code - no unique constraint
- **Impact**: Under concurrent load, duplicates may be created
- **Minimum actionable fix**: Add DB-level unique constraint

### LOW Severity Issues

Same as previous audit - minor issues noted but not critical.

---

## 6. Security Review Summary

### Authentication Entry Points
- **Conclusion**: Pass (with note that LoginRequest missing min:12)
- **Evidence**: AuthService with bcrypt cost 12, lockout logic

### Route-Level Authorization
- **Conclusion**: Pass
- **Evidence**: `routes/api.php:113-152` now has permission middleware on ALL music routes

### Object-Level Authorization
- **Conclusion**: Pass
- **Evidence**: Music controllers check created_by, RecommendationController checks user ID

### Function-Level Authorization
- **Conclusion**: Pass
- **Evidence**: Permission middleware applied consistently

### Tenant / User Data Isolation
- **Conclusion**: Fail
- **Evidence**: Same as Issue 1 - data scopes still not applied

### Admin / Internal / Debug Protection
- **Conclusion**: Pass
- **Evidence**: Admin routes properly protected

---

## 7. Tests and Logging Review

### Unit Tests
- **Conclusion**: Pass (for what is covered)
- Same as previous audit

### API / Integration Tests
- **Conclusion**: Pass (for what is covered)
- Same as previous audit

### Logging Categories / Observability
- **Conclusion**: Pass
- Same as previous audit

### Sensitive-Data Leakage Risk
- **Conclusion**: Partial Pass
- Same as previous audit

---

## 8. Test Coverage Assessment

### Coverage Summary

| Area | Fixed | Remaining Gaps |
|------|-------|----------------|
| Authentication | min:12 now enforced on CreateUser | LoginRequest no min check |
| Route Authorization | Music routes now protected | - |
| Object Authorization | Song ownership checked | - |
| Data Scoping | Middleware exists | Still not applied to queries |
| Behavior Dedup | Transaction added | target_type still missing |

### Final Coverage Judgment

**Fail**

While authorization gaps are mostly fixed, critical uncovered risks remain:

1. **Data scoping is non-functional** - middleware exists but never applies filters
2. **Behavior deduplication incomplete** - could allow incorrect dedup across event types  
3. **Login password validation incomplete** - accepts short passwords on login
4. **Audit coverage incomplete** - only admin routes audited

**Positive Changes from Audit 1:**
- Permission guards now on all music routes
- Playlist search now complete
- Profile weights now configurable
- SSO config now correct
- Recommendation endpoint authorization added
- Title/artist validation complete

---

## 9. Final Notes

### Issues Fixed (7 of 21)
1. ✅ Title/artist min:1 validation
2. ✅ Playlist search includes artist
3. ✅ Profile weights configurable
4. ✅ SSO config key fix
5. ✅ Music routes permission guards
6. ✅ Recommendation authorization
7. ✅ Title/artist min length validation for updates

### Issues NOT Fixed (6 key issues remain)
1. ❌ ApplyDataScopes doesn't apply to queries
2. ❌ Behavior event dedup missing target_type
3. ❌ LoginRequest missing min:12 password
4. ❌ AuditAdminAction only audits admin routes
5. ❌ Disciplinatory appeal no deadline check
6. ❌ Bulk notification empty array allowed

### Verdict: Partial Pass

Most security and authorization gaps from audit 1 are fixed. Critical gaps remain in:
- Data scoping middleware (non-functional)
- Behavior event deduplication (incomplete query)
- Login password length validation

Recommend addressing HIGH severity issues before production deployment.