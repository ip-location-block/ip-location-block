const { test, expect } = require("@playwright/test");
const { adminApi, openAdmin, watchRuntime } = require("./helpers/admin");

test.describe.serial("administration", () => {
  test("all primary tabs load without runtime or server errors", async ({
    page,
  }) => {
    const issues = watchRuntime(page);
    for (const tab of [
      "settings",
      "statistics",
      "logs",
      "search",
      "diagnostics",
    ]) {
      await openAdmin(page, tab);
      await expect(page.locator("h1")).toContainText("IP Location Block");
      await expect(page.locator(".ilb-tab-panel")).not.toBeEmpty();
    }
    expect(issues).toEqual([]);
  });

  test("the live Native provider verifies and returns precise search data", async ({
    page,
  }) => {
    await openAdmin(page, "settings");
    await page.getByRole("button", { name: "Simple", exact: true }).click();
    const providerResponse = page.waitForResponse(
      (response) =>
        response.url().includes("/providers/test") &&
        response.request().method() === "POST",
    );
    await page
      .getByRole("button", { name: "Test connection", exact: true })
      .click();
    expect((await providerResponse).status()).toBe(200);
    await expect(page.getByText("Ready", { exact: true })).toBeVisible();

    await openAdmin(page, "search");
    await page
      .getByRole("textbox", { name: "IP address", exact: true })
      .fill("8.8.8.8");
    const searchResponse = page.waitForResponse(
      (response) =>
        response.url().includes("/geolocation/search") &&
        response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "Search", exact: true }).click();
    expect((await searchResponse).status()).toBe(200);
    await expect(page.locator(".ilb-tab-panel")).toContainText("US");
    await expect(page.locator(".ilb-tab-panel")).toContainText("California");
    await expect(page.locator(".ilb-tab-panel")).toContainText("Mountain View");
    await expect(page.locator(".ilb-tab-panel")).toContainText("AS15169");
  });

  test("statistics, logs, and diagnostics actions respond", async ({
    page,
  }) => {
    await openAdmin(page, "statistics");
    await expect(
      page.getByRole("button", { name: "Statistics of validation" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Expand all" }).click();
    await expect(page.getByText("Provider performance")).toBeVisible();

    await openAdmin(page, "logs");
    await expect(
      page.getByRole("heading", { name: "Validation logs" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Refresh" }).click();
    const details = page.getByRole("button", { name: "Show details" });
    if (await details.count()) {
      await details.first().click();
      await expect(page.getByText("Request details")).toBeVisible();
    }

    await openAdmin(page, "diagnostics");
    const diagnosticsResponse = page.waitForResponse((response) =>
      response.url().includes("/diagnostics"),
    );
    await page.getByRole("button", { name: "Run checks" }).click();
    expect((await diagnosticsResponse).status()).toBe(200);
    await expect(page.getByText("Passed checks")).toBeVisible();

    const environment = await adminApi(page, "/diagnostics/environment");
    expect(environment).toBeTruthy();
  });

  test("classic and new interfaces can be switched without losing the tab", async ({
    page,
  }) => {
    await openAdmin(page, "settings");
    await Promise.all([
      page.waitForURL(/view=classic/),
      page.getByRole("link", { name: "Classic view" }).click(),
    ]);
    const newInterfaceLink = page
      .getByRole("link", { name: "Switch to the new interface" })
      .first();
    await expect(newInterfaceLink).toBeVisible();
    await expect(page.locator(".nav-tab")).toHaveCount(5);

    await Promise.all([page.waitForURL(/view=new/), newInterfaceLink.click()]);
    await expect(page.locator(".ilb-tab-panel")).toBeVisible();
    await expect(
      page.getByRole("link", { name: "Classic view" }),
    ).toBeVisible();
  });
});
