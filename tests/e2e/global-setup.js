const fs = require("fs");
const path = require("path");
const { chromium, request } = require("@playwright/test");

module.exports = async function globalSetup(config) {
  const baseURL = config.projects[0].use.baseURL;
  const parsed = new URL(baseURL);
  const isLocal =
    parsed.hostname === "localhost" ||
    parsed.hostname === "127.0.0.1" ||
    parsed.hostname.endsWith(".test");
  const username = process.env.ILB_E2E_ADMIN_USER || (isLocal ? "admin" : "");
  const password =
    process.env.ILB_E2E_ADMIN_PASSWORD || (isLocal ? "admin" : "");

  if (!username || !password) {
    throw new Error(
      "Set ILB_E2E_ADMIN_USER and ILB_E2E_ADMIN_PASSWORD for non-local targets.",
    );
  }

  const tokenFile = process.env.ILB_E2E_TOKEN_FILE;
  if (!tokenFile) {
    throw new Error("Set ILB_E2E_TOKEN_FILE for the isolated QA site.");
  }
  const token = fs.readFileSync(tokenFile, "utf8").trim();
  if (!token) {
    throw new Error("The ILB_E2E_TOKEN_FILE token must not be empty.");
  }

  const healthContext = await request.newContext({
    baseURL,
    storageState: { cookies: [], origins: [] },
  });
  try {
    const qaResponse = await healthContext.get("/?ilb-e2e-health=1", {
      headers: { "X-ILB-Test-Token": token },
    });
    if (
      qaResponse.status() !== 200 ||
      qaResponse.headers()["x-ilb-e2e-qa"] !== "enabled"
    ) {
      throw new Error(
        "Refusing to run: the token-gated IP Location Block E2E MU helper is not enabled.",
      );
    }
  } finally {
    await healthContext.dispose();
  }

  const browser = await chromium.launch();
  const context = await browser.newContext({
    extraHTTPHeaders: {
      "X-ILB-Test-Admin": "1",
      "X-ILB-Test-IP": "198.51.100.1",
      "X-ILB-Test-Token": token,
    },
  });
  const page = await context.newPage();

  try {
    await page.goto(`${baseURL}/wp-login.php`, {
      waitUntil: "domcontentloaded",
    });
    await page.locator("#user_login").fill(username);
    await page.locator("#user_pass").fill(password);
    await Promise.all([
      page.waitForURL(/\/wp-admin\//),
      page.locator("#wp-submit").click(),
    ]);

    const authDirectory = path.join(__dirname, ".auth");
    fs.mkdirSync(authDirectory, { recursive: true });
    await page.context().storageState({
      path: path.join(authDirectory, "admin.json"),
    });
  } finally {
    await browser.close();
  }
};
