# Privacy review and PIA preparation

**Status:** Preliminary technical review completed on 2 November 2026. This is not an approved Privacy Impact Assessment (PIA), legal opinion, or authorization to import real beneficiary data. The responsible personal information controller and privacy officer must complete and approve the open items below before production records are loaded.

## Data handled

| Data | Current operational use | Review action |
| --- | --- | --- |
| Beneficiary name and beneficiary number | Identify and verify recipients; connect distribution history | Confirm the minimum identifiers needed at each screen and export. |
| Contact number and address | Contact or locate a beneficiary | Confirm whether both fields are needed, who may view them, and when to remove or correct them. |
| Sitio/Purok | Locate a beneficiary within the barangay for service delivery | Collect only the location detail needed for distribution; review staff access and retention with the privacy officer. |
| Household size | Plan household assistance and check-in | Confirm the purpose and restrict access to roles that need it. |
| Priority category (Regular, Senior Citizen, PWD, Solo Parent) | Filter eligibility and target assistance | The privacy officer should assess whether any category reveals sensitive personal information and approve its use and access. |
| QR image, QR token, and beneficiary number encoded in legacy QR files | Identify a beneficiary at check-in | Treat QR values as credentials. Issue replacements if a QR may have been exposed; keep generated files in private storage. |
| Distribution, delivery, stock, and free-text notes | Track assistance and maintain operational history | Set a retention period for records and review whether free-text notes can contain unnecessary personal details. |
| User account, session, access token, audit IP address, and user agent | Authenticate users, record actions, and investigate incidents | Set retention and access rules for account, session, token, and audit records. |

## Access controls reviewed

- Beneficiary administration and CSV export are restricted to administrators in the web routes.
- Staff and volunteer roles have separate distribution permissions. API beneficiary fields are filtered by role; volunteers do not receive address, contact number, household size, or priority category in the beneficiary API resource.
- The web QR lookup endpoint was available to volunteers even though the check-in interface is restricted to admins and staff. It now requires the `distribution-staff` permission.
- Beneficiary API distribution responses included free-text notes and the internal distributing user ID. Those two fields are now returned only to roles that can release packages (admins and staff).
- QR viewing and download use authenticated administrator-only routes. The production deployment checklist calls for private object storage; confirm the Render and storage settings match it.

These are code-level findings. They do not confirm that production roles, database permissions, hosting access, backups, or storage policies are configured correctly.

## QR files in source control

There are 26 tracked files under `public/qr-codes/`. The Docker build excludes that directory, but the files remain tracked in Git and may be served from a local/public webroot or exposed through repository history. Treat any active QR payload in these files as potentially exposed until checked.

`.gitignore` now ignores new files in `public/qr-codes/`; this does not untrack existing files or remove copies from Git history. Before importing real beneficiary records, the repository owner should determine whether the repository or deployed webroot was publicly accessible, rotate any affected active QR credentials, and arrange a reviewed cleanup of tracked files and history. Back up and verify required records before removing legacy files.

## Retention and operational decisions required

The code preserves distribution history and audit logs, but a retention period and deletion schedule were not found in the repository. Before production use, the responsible office should document:

1. The legal basis, specific purpose, notice/consent process, and responsible personal information controller for each collected field.
2. Which staff roles need each field and each export; how accounts are approved, reviewed, and disabled when staff leave.
3. Retention periods for beneficiary profiles, inactive records, distribution history, audit logs, support tickets, sessions, and tokens, including the approved deletion or archival procedure.
4. Backup frequency, encryption, access, retention, restore testing, and incident/breach response contacts and steps.
5. Render, database, and QR storage locations; vendor/subprocessor terms; access controls; and the approved cross-border/data residency arrangements, if applicable.
6. Whether PWD or other priority information is sensitive personal information and what additional safeguards and access limits apply.
7. How beneficiaries can request access or correction, raise a privacy concern, and receive the required privacy notice.

The National Privacy Commission describes a PIA as an assessment of privacy impacts and safeguards for projects processing personal data. The privacy officer should use the current NPC guidance and the organization's own policies to complete and approve the formal PIA: <https://privacy.gov.ph/third-parties/>.

## Release gate

Do not import real beneficiary records until the responsible privacy officer has reviewed this document, completed the formal PIA, approved the notice and retention schedule, and confirmed the production controls above. Record the approving officer, approval date, and any accepted residual risks here before changing this status to approved.

**Approver:** Pending  
**Approval date:** Pending  
**PIA status:** Pending privacy officer review
