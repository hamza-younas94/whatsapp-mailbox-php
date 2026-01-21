# Tenant Onboarding Checklist

1) Create or select a user
- Go to users.php and create a user (role: owner/admin) or pick an existing user ID.

2) Configure WhatsApp credentials
- Open user-settings.php, add phone number ID, access token, and verify token.
- Copy the generated webhook URL and configure it in Meta.

3) Seed demo data (optional but recommended for clients)
- Run: php seed_demo_tenant.php --user=<USER_ID>
- Seeds tags, contacts, quick replies, segment, workflow, drip, template, webhook.

4) Run migrations and cron
- php run_feature_migrations.php
- Add cron: * * * * * php process_jobs.php >> logs/cron.log 2>&1

5) Verify multi-tenant isolation
- Log in as the tenant user; ensure contacts/messages show only their data.
- Send/receive a test message; verify webhook maps to the right tenant.

6) Smoke test key features
- Mailbox send/receive, mark read
- CRM edit stage/score, add note/deal
- Broadcast draft/send (small list)
- Drip + workflow toggles
- Message template send
- Webhook test from Webhook Manager

7) Explain plan limits & roles
- Default per-tenant message limit: 500 (adjust via config keys message_limit_user_<id>)
- Roles: owner/admin can see tenant data; agents limited to their tenant.

8) Provide client handoff notes
- Credentials, webhook URL, and token
- How to reach logs (storage/logs)
- How to reseed demo data (rerun step 3)
