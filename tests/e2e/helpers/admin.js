const fs = require("fs");

const ADMIN_PATH = "/wp-admin/options-general.php?page=ip-location-block";

function adminUrl(tab = "settings", query = "") {
  return `${ADMIN_PATH}&tab=${tab}${query}`;
}

async function openAdmin(page, tab = "settings", query = "") {
  await page.goto(adminUrl(tab, query), {
    waitUntil: "domcontentloaded",
  });
  await page.locator(".ilb-tab-panel").waitFor({ state: "visible" });
  if (tab === "settings") {
    await page.locator(".ilb-settings").waitFor({ state: "visible" });
  }
}

async function adminApi(page, endpoint, options = {}) {
  return page.evaluate(
    async ({ endpoint: requestedEndpoint, method, data }) => {
      const boot = window.ipLocationBlockAdmin;
      if (!boot?.nonce || !boot?.restNamespace) {
        throw new Error("IP Location Block REST bootstrap is unavailable.");
      }

      const suffix = requestedEndpoint.startsWith("/")
        ? requestedEndpoint
        : `/${requestedEndpoint}`;
      const response = await fetch(
        `${window.location.origin}/wp-json/${boot.restNamespace}${suffix}`,
        {
          method: method || "GET",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": boot.nonce,
          },
          body: data === undefined ? undefined : JSON.stringify(data),
        },
      );

      if (!response.ok) {
        throw new Error(
          `IP Location Block REST request failed with HTTP ${response.status}.`,
        );
      }

      return response.json();
    },
    {
      endpoint,
      method: options.method || "GET",
      data: options.data,
    },
  );
}

async function getSettings(page) {
  return adminApi(page, "/settings?scope=site");
}

async function saveSettings(page, settings) {
  return adminApi(page, "/settings?scope=site", {
    method: "POST",
    data: settings,
  });
}

async function clearLocationCache(page) {
  return adminApi(page, "/cache", { method: "DELETE" });
}

function cloneSettings(settings) {
  return JSON.parse(JSON.stringify(settings));
}

function getPath(object, dottedPath) {
  return dottedPath
    .split(".")
    .reduce(
      (value, part) =>
        value !== undefined && value !== null ? value[part] : undefined,
      object,
    );
}

function setPath(object, dottedPath, value) {
  const parts = dottedPath.split(".");
  const leaf = parts.pop();
  let cursor = object;
  for (const part of parts) {
    if (!cursor[part] || typeof cursor[part] !== "object") {
      cursor[part] = {};
    }
    cursor = cursor[part];
  }
  cursor[leaf] = value;
  return object;
}

function readTestToken() {
  const tokenFile = process.env.ILB_E2E_TOKEN_FILE;
  if (!tokenFile) {
    throw new Error("Set ILB_E2E_TOKEN_FILE for request-enforcement tests.");
  }
  return fs.readFileSync(tokenFile, "utf8").trim();
}

async function expandAllSettingsSections(page) {
  const buttons = page.locator(".components-panel__body-title button");
  for (let index = 0; index < (await buttons.count()); index++) {
    const button = buttons.nth(index);
    if ((await button.getAttribute("aria-expanded")) !== "true") {
      await button.click();
    }
  }
}

function watchRuntime(page) {
  const issues = [];
  page.on("pageerror", (error) => {
    issues.push(`pageerror: ${error.message}`);
  });
  page.on("console", (message) => {
    if (message.type() === "error") {
      issues.push(`console: ${message.text()}`);
    }
  });
  page.on("response", (response) => {
    if (response.status() >= 500) {
      issues.push(
        `http ${response.status()}: ${new URL(response.url()).pathname}`,
      );
    }
  });
  return issues;
}

module.exports = {
  ADMIN_PATH,
  adminApi,
  adminUrl,
  clearLocationCache,
  cloneSettings,
  expandAllSettingsSections,
  getPath,
  getSettings,
  openAdmin,
  readTestToken,
  saveSettings,
  setPath,
  watchRuntime,
};
