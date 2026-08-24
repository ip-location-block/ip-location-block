const { test, expect } = require("@playwright/test");
const crypto = require("crypto");
const { getSettings, openAdmin } = require("./helpers/admin");

test.describe("1.3.9 upgrade assertions", () => {
  test.skip(
    !process.env.ILB_E2E_UPGRADE_PROFILE,
    "Set ILB_E2E_UPGRADE_PROFILE after installing 1.4.0 over a seeded 1.3.9 site.",
  );

  test("the migration is complete and preserves the selected fixture profile", async ({
    page,
  }) => {
    await openAdmin(page, "settings", "&view=advanced");
    const settings = await getSettings(page);

    expect(settings.version).toBe("1.4.0");
    expect(settings.restrict_api).toBeUndefined();
    expect(
      Object.fromEntries(
        ["admin", "ajax", "plugins", "themes"].map((target) => [
          target,
          Number(settings.validation[target]),
        ]),
      ),
    ).toEqual({ admin: 1, ajax: 1, plugins: 0, themes: 1 });
    expect(settings.extra_ips.white_list).toContain("198.51.100.0/24");
    expect(settings.providers["Removed E2E Provider"]).toBeUndefined();
    expect(settings.providers["IP Location Block"]).toBeTruthy();

    if (process.env.ILB_E2E_NATIVE_KEY_SHA256) {
      expect(
        crypto
          .createHash("sha256")
          .update(String(settings.providers["IP Location Block"]))
          .digest("hex"),
      ).toBe(process.env.ILB_E2E_NATIVE_KEY_SHA256);
    }

    if (process.env.ILB_E2E_UPGRADE_PROFILE === "legacy-default") {
      expect(settings.ua_legacy_offer).toBeTruthy();
      expect(settings.public.ua_list).toContain("Twitterbot:US");
    } else {
      expect(settings.ua_legacy_offer).toBeFalsy();
      expect(settings.public.ua_list).toContain("E2ECustomBot#*");
    }

    await page.reload({ waitUntil: "domcontentloaded" });
    await page.locator(".ilb-settings").waitFor({ state: "visible" });
    expect(await getSettings(page)).toEqual(settings);

    await page.goto("/wp-admin/plugins.php", {
      waitUntil: "domcontentloaded",
    });
    await expect(page.locator('tr[data-slug="ip-location-block"]')).toHaveClass(
      /active/,
    );
  });
});
