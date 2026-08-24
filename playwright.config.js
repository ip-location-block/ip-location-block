const path = require("path");
const { defineConfig, devices } = require("@playwright/test");

const authFile = path.join(__dirname, "tests/e2e/.auth/admin.json");

module.exports = defineConfig({
  testDir: "./tests/e2e",
  outputDir: "test-results/e2e",
  globalSetup: require.resolve("./tests/e2e/global-setup"),
  fullyParallel: false,
  workers: 1,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  retries: 0,
  reporter: [
    ["list"],
    ["html", { outputFolder: "playwright-report", open: "never" }],
  ],
  use: {
    baseURL: process.env.ILB_E2E_BASE_URL || "http://wp.iplocationblock.test",
    storageState: authFile,
    // Settings saves contain configured provider credentials. Never persist
    // request bodies in traces or videos.
    trace: "off",
    video: "off",
    screenshot: "only-on-failure",
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "firefox",
      use: { ...devices["Desktop Firefox"] },
    },
    {
      name: "webkit",
      use: { ...devices["Desktop Safari"] },
    },
    {
      name: "mobile-chromium",
      use: {
        ...devices["Pixel 5"],
        viewport: { width: 390, height: 844 },
      },
    },
  ],
});
