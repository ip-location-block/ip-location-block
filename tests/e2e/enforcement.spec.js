const { test, expect, request: requestFactory } = require("@playwright/test");
const {
  adminApi,
  clearLocationCache,
  cloneSettings,
  getSettings,
  openAdmin,
  readTestToken,
  saveSettings,
} = require("./helpers/admin");

const PRIMARY_IP = "8.8.8.8";
const IPV6_IP = "2001:4860:4860::8888";
const DISTINCT_CANDIDATES = [
  "1.1.1.1",
  "9.9.9.9",
  "208.67.222.222",
  "91.198.174.192",
  "80.80.80.80",
];

async function lookup(page, ip) {
  const payload = await adminApi(page, "/geolocation/search", {
    method: "POST",
    data: { ip, providers: ["IP Location Block"] },
  });
  const row = payload.results?.find(
    (result) => result.provider === "IP Location Block",
  );
  if (!row?.result?.countryCode) {
    throw new Error(`Native lookup did not return a country for ${ip}.`);
  }
  return row.result;
}

function publicRule(baseline, overrides = {}) {
  const settings = cloneSettings(baseline);
  settings.matching_rule = -1;
  settings.validation.public = 1;
  settings.public.target_rule = 0;
  settings.public.response_code = 403;
  settings.public.response_msg = "IP Location Block E2E denial";
  settings.public.redirect_uri = "";
  settings.public.behavior = false;
  settings.public.dnslkup = false;
  settings.simulate = false;
  settings.cache_hold = false;
  settings.validation.reclogs = 5;
  settings.extra_ips.white_list = "";
  settings.extra_ips.black_list = "";
  return Object.assign(settings, overrides);
}

async function anonymousRequest(requestContext, token, ip, options = {}) {
  return requestContext.get(options.path || "/", {
    failOnStatusCode: false,
    maxRedirects: options.maxRedirects === undefined ? 0 : options.maxRedirects,
    headers: {
      "X-ILB-Test-IP": ip,
      "X-ILB-Test-Token": token,
      ...(options.headers || {}),
    },
  });
}

function newAnonymousContext(baseURL) {
  return requestFactory.newContext({
    baseURL,
    storageState: { cookies: [], origins: [] },
  });
}

test.describe.serial("request enforcement", () => {
  test("country blacklist and whitelist both block and allow correctly", async ({
    page,
    baseURL,
  }) => {
    await openAdmin(page, "search");
    const baseline = await getSettings(page);
    const token = readTestToken();
    const primary = await lookup(page, PRIMARY_IP);
    let distinct;

    for (const ip of DISTINCT_CANDIDATES) {
      const result = await lookup(page, ip);
      if (result.countryCode !== primary.countryCode) {
        distinct = { ip, result };
        break;
      }
    }
    expect(
      distinct,
      "A live fixture in a second country is required.",
    ).toBeTruthy();
    const anonymous = await newAnonymousContext(baseURL);
    expect(
      (await anonymous.storageState()).cookies.map((cookie) => cookie.name),
    ).toEqual([]);

    try {
      const blacklist = publicRule(baseline);
      blacklist.public.matching_rule = 1;
      blacklist.public.black_list = primary.countryCode;
      const savedBlacklist = await saveSettings(page, blacklist);
      expect(Number(savedBlacklist.validation.public)).toBe(1);
      expect(Number(savedBlacklist.public.matching_rule)).toBe(1);
      expect(savedBlacklist.public.black_list).toBe(primary.countryCode);
      await clearLocationCache(page);

      const blocked = await anonymousRequest(anonymous, token, PRIMARY_IP);
      expect(blocked.status()).toBe(403);
      expect(await blocked.text()).toContain("IP Location Block E2E denial");
      expect(blocked.headers()["x-robots-tag"]).toContain("noindex");
      expect(blocked.headers()["cache-control"]).toContain("no-store");

      const allowed = await anonymousRequest(anonymous, token, distinct.ip);
      expect(allowed.status()).toBe(200);

      const whitelist = publicRule(baseline);
      whitelist.public.matching_rule = 0;
      whitelist.public.white_list = primary.countryCode;
      await saveSettings(page, whitelist);
      await clearLocationCache(page);
      expect(
        (await anonymousRequest(anonymous, token, PRIMARY_IP)).status(),
      ).toBe(200);
      expect(
        (await anonymousRequest(anonymous, token, distinct.ip)).status(),
      ).toBe(403);
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
      await anonymous.dispose();
    }
  });

  test("state, city, Region alias, and OR precision rules are enforced", async ({
    page,
    baseURL,
  }) => {
    await openAdmin(page, "search");
    const baseline = await getSettings(page);
    const token = readTestToken();
    const location = await lookup(page, PRIMARY_IP);
    expect(location.stateName).toBeTruthy();
    expect(location.cityName).toBeTruthy();
    const anonymous = await newAnonymousContext(baseURL);

    try {
      for (const rule of [
        `${location.countryCode}:State:${location.stateName}`,
        `${location.countryCode}:Region:${location.stateName}`,
        `${location.countryCode}:City:Not the fixture city~${location.cityName}`,
      ]) {
        const settings = publicRule(baseline);
        settings.public.matching_rule = 1;
        settings.public.black_list = rule;
        await saveSettings(page, settings);
        await clearLocationCache(page);
        expect(
          (await anonymousRequest(anonymous, token, PRIMARY_IP)).status(),
          rule,
        ).toBe(403);
      }

      const nonmatch = publicRule(baseline);
      nonmatch.public.matching_rule = 1;
      nonmatch.public.black_list = `${location.countryCode}:City:Not the fixture city`;
      await saveSettings(page, nonmatch);
      await clearLocationCache(page);
      expect(
        (await anonymousRequest(anonymous, token, PRIMARY_IP)).status(),
      ).toBe(200);
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
      await anonymous.dispose();
    }
  });

  test("extra IP, CIDR, IPv6, and ASN rules have the expected precedence", async ({
    page,
    baseURL,
  }) => {
    await openAdmin(page, "search");
    const baseline = await getSettings(page);
    const token = readTestToken();
    const location = await lookup(page, PRIMARY_IP);
    const anonymous = await newAnonymousContext(baseURL);

    try {
      const whiteOverride = publicRule(baseline);
      whiteOverride.public.matching_rule = 1;
      whiteOverride.public.black_list = location.countryCode;
      whiteOverride.extra_ips.white_list = PRIMARY_IP;
      await saveSettings(page, whiteOverride);
      await clearLocationCache(page);
      expect(
        (await anonymousRequest(anonymous, token, PRIMARY_IP)).status(),
      ).toBe(200);

      for (const extraRule of ["8.8.8.0/24", IPV6_IP]) {
        const extraBlock = publicRule(baseline);
        extraBlock.public.matching_rule = 1;
        extraBlock.public.black_list = "ZZ";
        extraBlock.extra_ips.black_list = extraRule;
        await saveSettings(page, extraBlock);
        await clearLocationCache(page);
        const ip = extraRule.includes(":") ? IPV6_IP : PRIMARY_IP;
        expect(
          (await anonymousRequest(anonymous, token, ip)).status(),
          extraRule,
        ).toBe(403);
      }

      if (location.asn) {
        const asnBlock = publicRule(baseline);
        asnBlock.use_asn = 1;
        asnBlock.public.matching_rule = 1;
        asnBlock.public.black_list = "ZZ";
        asnBlock.extra_ips.black_list = location.asn;
        await saveSettings(page, asnBlock);
        await clearLocationCache(page);
        expect(
          (await anonymousRequest(anonymous, token, PRIMARY_IP)).status(),
        ).toBe(403);
      }
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
      await anonymous.dispose();
    }
  });

  test("simulation records a would-block request without denying it", async ({
    page,
    baseURL,
  }) => {
    await openAdmin(page, "search");
    const baseline = await getSettings(page);
    const token = readTestToken();
    const location = await lookup(page, PRIMARY_IP);
    const anonymous = await newAnonymousContext(baseURL);

    try {
      const before = await adminApi(page, "/logs?hook=public");
      const existingIds = new Set(before.rows.map((row) => row.id));
      const settings = publicRule(baseline);
      settings.public.matching_rule = 1;
      settings.public.black_list = location.countryCode;
      settings.simulate = true;
      await saveSettings(page, settings);
      await clearLocationCache(page);
      const response = await anonymousRequest(anonymous, token, PRIMARY_IP);
      expect(response.status()).toBe(200);

      const logs = await adminApi(page, "/logs?hook=public");
      expect(
        logs.rows.some(
          (row) =>
            !existingIds.has(row.id) &&
            row.code === location.countryCode &&
            row.verdict === "blocked",
        ),
      ).toBeTruthy();
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
      await anonymous.dispose();
    }
  });
});
