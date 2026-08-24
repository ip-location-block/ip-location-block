# IP Location Block browser tests

These tests run only against an isolated WordPress QA site with the E2E MU
fixture enabled. The global setup refuses to run when the fixture response
header is absent.

```bash
npm run test:e2e:install
ILB_E2E_BASE_URL=http://wp.iplocationblock.test \
ILB_E2E_ADMIN_USER=admin \
ILB_E2E_ADMIN_PASSWORD=admin \
ILB_E2E_TOKEN_FILE=/path/to/qa-token \
npm run test:e2e
```

Use `npm run test:e2e:all` for Chromium, Firefox, WebKit, and the mobile
Chromium project. Provider credentials are never committed, printed, or
recorded in Playwright traces. Tests execute serially because they temporarily
change one shared WordPress option and restore it afterward.

The suite covers every path declared by the React settings schema, conditional
Simple/Advanced controls, representative save and sanitization behavior, every
primary admin tab, Classic/New switching, the live Native provider and precise
lookup data, and request enforcement for country, state, city, Region aliases,
OR rules, IP/CIDR, IPv6, ASN, and simulation mode.

The MU fixture is deliberately inert unless `ILB_E2E_ENABLED` is the boolean
`true`. Even then, an IP override or administrator bypass is accepted only when
the request also carries the random token read from `ILB_E2E_TOKEN_FILE`. Never
enable or install the fixture on a public site.

After upgrading a seeded 1.3.9 fixture, run the migration assertions with:

```bash
ILB_E2E_UPGRADE_PROFILE=custom \
ILB_E2E_BASE_URL=http://wp.iplocationblock.test \
ILB_E2E_ADMIN_USER=admin \
ILB_E2E_ADMIN_PASSWORD=admin \
ILB_E2E_TOKEN_FILE=/path/to/qa-token \
npx playwright test tests/e2e/upgrade.spec.js --project=chromium
```

Use `legacy-default` instead of `custom` when the 1.3.9 fixture retains the old
default User-Agent list. The normal enforcement suite performs live provider
lookups and therefore consumes a small amount of Native API quota.
