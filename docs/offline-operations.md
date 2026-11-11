# Offline operation and relief claims

## Current behavior

The repository contains the Laravel web portal and API, but no Android, iOS,
or separate mobile-client source. The web distribution screen detects loss of
connectivity and blocks QR verification and release. It does not cache
beneficiary records or queue changes. Super Admin workspace, account, and
platform controls require an online session.

Do not mark a package as released while offline. A release must reach the
server, which rechecks the current beneficiary QR, account status, pending
assignment, release window, duplicate-claim rule, and reserved stock in a
database transaction. A cached page or a scan that has not received server
confirmation is not proof of pickup.

## Safe retry behavior

Online release requests include a random UUID (`offline_claim_id`). The server
stores it uniquely with the distribution and returns the same result if that
same request is retried after a connection interruption. Reusing the UUID for a
different beneficiary or assignment is rejected. This makes an interrupted
online request safe to retry; it does not enable an offline queue.

The older `/api/distributions/record` endpoint is disabled because it could
create a released distribution using only a beneficiary number and package ID,
without proof of a valid QR scan or an existing pending assignment. Clients
should create an assignment and use `/api/distributions/release-pending` after
reconnecting and scanning the current QR.

## If a future mobile app needs offline operation

Build and test that client separately before enabling field use. It must use
device-protected storage, encrypt queued records, minimize cached personal data,
and clearly show queued claims as **unconfirmed**. On reconnect, the server must
validate each claim against current account status, QR revocation, barangay,
assignment window, duplicate claims, and stock. Conflicts must remain visible
for an authorized human to resolve; the client must never silently mark an
offline claim as released. Super Admin administration should remain online-only.
