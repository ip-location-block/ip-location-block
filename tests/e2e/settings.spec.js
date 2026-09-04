const fs = require("fs");
const path = require("path");
const { test, expect } = require("@playwright/test");
const {
  clearLocationCache,
  cloneSettings,
  expandAllSettingsSections,
  getPath,
  getSettings,
  openAdmin,
  saveSettings,
  setPath,
  watchRuntime,
} = require("./helpers/admin");
const { SETTINGS_PATHS, VIRTUAL_PATHS } = require("./settings-manifest");

async function setToggle(container, accessibleName, checked) {
  const control = container.getByLabel(accessibleName);
  if ((await control.isChecked()) !== checked) {
    const controlId = await control.getAttribute("id");
    const label = container.locator(`label[for="${controlId}"]`).first();
    await label.evaluate((element) =>
      element.scrollIntoView({ block: "center" }),
    );
    // WordPress renders the real checkbox under an animated, styled label.
    // Trigger that associated label to avoid transient mobile hit-test races.
    await label.click({ force: true });
  }
  if (checked) {
    await expect(control).toBeChecked();
  } else {
    await expect(control).not.toBeChecked();
  }
}

test.describe.serial("settings", () => {
  test("the E2E manifest covers every declared settings field", async ({
    page,
  }) => {
    const schema = fs.readFileSync(
      path.join(__dirname, "../../admin/app/src/tabs/settingsSchema.js"),
      "utf8",
    );
    const schemaPaths = [...schema.matchAll(/path:\s*'([^']+)'/g)].map(
      (match) => match[1],
    );

    expect([...new Set(schemaPaths)].sort()).toEqual(
      [...new Set(SETTINGS_PATHS)].sort(),
    );

    await openAdmin(page, "settings", "&view=advanced");
    const settings = await getSettings(page);
    for (const settingPath of SETTINGS_PATHS) {
      if (VIRTUAL_PATHS.has(settingPath)) {
        continue;
      }
      expect(
        getPath(settings, settingPath),
        `${settingPath} must be present in the saved settings object`,
      ).not.toBeUndefined();
    }
  });

  test("conditional controls and all seven advanced sections render", async ({
    page,
  }) => {
    await openAdmin(page, "settings", "&view=advanced");
    const baseline = await getSettings(page);

    try {
      const whitelistState = cloneSettings(baseline);
      setPath(whitelistState, "matching_rule", 0);
      setPath(whitelistState, "validation.mimetype", 1);
      setPath(whitelistState, "response_code", 301);
      setPath(whitelistState, "comment.pos", 1);
      setPath(whitelistState, "public.matching_rule", 0);
      setPath(whitelistState, "public.target_rule", 1);
      await saveSettings(page, whitelistState);

      await openAdmin(page, "settings", "&view=advanced");
      await expandAllSettingsSections(page);
      expect(await page.locator(".components-panel__body").count()).toBe(7);
      for (const label of [
        "Whitelist of country code",
        "Whitelist of allowed MIME types",
        "Redirect URL",
        "Comment-form message text",
        "Target pages",
        "Target post types",
        "Target categories",
        "Target tags",
        "Protected single-site option names",
        "Maximum log entries",
        "Network API timeout [sec]",
      ]) {
        await expect(
          page.getByText(label, { exact: true }).first(),
          label,
        ).toBeVisible();
      }

      const blacklistState = cloneSettings(whitelistState);
      setPath(blacklistState, "matching_rule", 1);
      setPath(blacklistState, "validation.mimetype", 2);
      setPath(blacklistState, "response_code", 403);
      setPath(blacklistState, "public.matching_rule", 1);
      await saveSettings(page, blacklistState);
      await openAdmin(page, "settings", "&view=advanced");
      await expandAllSettingsSections(page);
      for (const label of [
        "Blacklist of country code",
        "Blacklist of forbidden file extensions",
        "Capabilities to be verified",
        "Response message",
      ]) {
        await expect(
          page.getByText(label, { exact: true }).first(),
          label,
        ).toBeVisible();
      }
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
    }
  });

  test("representative controls save, sanitize, and persist", async ({
    page,
  }) => {
    const runtimeIssues = watchRuntime(page);
    await openAdmin(page, "settings", "&view=advanced");
    const baseline = await getSettings(page);

    try {
      await expandAllSettingsSections(page);
      const validation = page.locator(".ilb-settings-section--validation-rule");
      await validation
        .getByLabel("Matching rule", { exact: true })
        .selectOption("1");
      await validation.getByLabel("Blacklist of country code").fill("ZZ,AU");
      await setToggle(validation, "Use Autonomous System Number (ASN)", true);
      await validation
        .getByLabel("$_SERVER keys for extra IP addresses")
        .fill("HTTP_X_FORWARDED_FOR");
      await validation
        .getByLabel("Whitelist of extra IPs (CIDR, ASN)")
        .fill("192.0.2.0/24\nAS64500");
      await validation
        .getByLabel("Bad signatures in query")
        .fill("../,/wp-config.php,/e2e-signature");
      await validation
        .getByLabel("Prevent malicious file uploading")
        .selectOption("2");
      await validation
        .getByLabel("Blacklist of forbidden file extensions")
        .fill("php,phar,e2e");
      await validation
        .getByLabel("Capabilities to be verified")
        .fill("upload_files,manage_options");
      await setToggle(validation, "Metadata Exploit Protection", true);
      await validation
        .getByLabel("Response code (RFC 2616)")
        .selectOption("403");
      await validation
        .getByLabel("Response message")
        .fill("E2E blocked request");
      await setToggle(
        validation,
        "Simulation mode (log only, do not block)",
        true,
      );

      const backend = page.locator(".ilb-settings-section--validation-target");
      await setToggle(backend, "Comment post", true);
      await backend.getByLabel("Message on comment form").selectOption("1");
      await backend
        .getByLabel("Comment-form message text")
        .fill("<strong>E2E comment notice</strong>");
      await backend.getByLabel("XML-RPC").selectOption("1");
      await backend
        .getByLabel("Max failed login attempts per IP")
        .selectOption("3");
      await backend
        .getByLabel("Plugins area", { exact: true })
        .selectOption("0");
      await backend
        .getByLabel("Themes area", { exact: true })
        .selectOption("0");

      const publicSettings = page.locator(".ilb-settings-section--public");
      await setToggle(publicSettings, "Bypass page cache", true);
      await publicSettings
        .getByLabel("Matching rule", { exact: true })
        .selectOption("1");
      await publicSettings
        .getByLabel("Blacklist of country code")
        .fill("ZZ,AU");
      await publicSettings
        .getByLabel("Response code (RFC 2616)")
        .selectOption("403");
      await publicSettings
        .getByLabel("Response message")
        .fill("E2E public block");
      await publicSettings.getByLabel("Validation target").selectOption("1");
      await setToggle(
        publicSettings,
        "Block badly-behaved bots and crawlers",
        true,
      );
      await publicSettings.getByLabel("Condition: page views").fill("9");
      await publicSettings.getByLabel("Condition: seconds").fill("6");
      await setToggle(publicSettings, "Reverse DNS lookup", true);

      const recording = page.locator(".ilb-settings-section--recording");
      await setToggle(recording, "Anonymize IP address", false);
      await recording.getByLabel("Cache expiration time [sec]").fill("7201");
      await recording.getByLabel("GC interval [sec]").fill("901");
      await recording.getByLabel("Record “Validation logs”").selectOption("6");
      await recording.getByLabel("Logs expiration [days]").fill("8");
      await recording
        .getByLabel("$_POST keys to record")
        .fill("action,log,FILES");
      await recording.getByLabel("Maximum log entries").fill("501");
      await recording.getByLabel("Maximum statistics period [days]").fill("31");
      await setToggle(
        recording,
        "Remove all settings and records at uninstall",
        false,
      );

      await page
        .locator(".ilb-settings-section--provider")
        .getByLabel("Network API timeout [sec]")
        .fill("29");
      await setToggle(
        page.locator(".ilb-settings-section--database"),
        "Auto updating (once a month)",
        false,
      );

      const saveResponse = page.waitForResponse(
        (response) =>
          response.url().includes("/ip-location-block/v1/settings") &&
          response.request().method() === "POST",
      );
      await page.getByRole("button", { name: "Save Changes" }).click();
      expect((await saveResponse).status()).toBe(200);
      await expect(page.locator(".components-snackbar")).toContainText(
        "Settings saved.",
      );

      const saved = await getSettings(page);
      expect(Number(saved.matching_rule)).toBe(1);
      expect(saved.black_list).toBe("ZZ,AU");
      expect(Number(saved.use_asn)).toBe(1);
      expect(saved.validation.proxy).toBe("HTTP_X_FORWARDED_FOR");
      expect(saved.extra_ips.white_list).toContain("192.0.2.0/24");
      expect(Number(saved.validation.mimetype)).toBe(2);
      expect(Number(saved.response_code)).toBe(403);
      expect(saved.response_msg).toBe("E2E blocked request");
      expect(Number(saved.comment.pos)).toBe(1);
      expect(saved.comment.msg).toContain("E2E comment notice");
      expect(Number(saved.login_fails)).toBe(3);
      expect(saved.public.black_list).toBe("ZZ,AU");
      expect(Number(saved.public.target_rule)).toBe(1);
      expect(Number(saved.behavior.view)).toBe(9);
      expect(Number(saved.behavior.time)).toBe(6);
      expect(Number(saved.cache_time)).toBe(7201);
      expect(Number(saved.validation.reclogs)).toBe(6);
      expect(Number(saved.timeout)).toBe(29);
      expect(saved.clean_uninstall).toBeFalsy();
      expect(saved.update.auto).toBeFalsy();
      expect(runtimeIssues).toEqual([]);
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
    }
  });

  test("Simple and Advanced views share unsaved state", async ({ page }) => {
    await openAdmin(page, "settings");
    const baseline = await getSettings(page);

    try {
      await page.getByRole("button", { name: "Simple", exact: true }).click();
      const enable = page.getByLabel("Enable location blocking");
      test.skip(
        await enable.isDisabled(),
        "The configured provider must be verified before testing Simple mode.",
      );
      await setToggle(page, "Enable location blocking", true);
      await page.getByRole("button", { name: "Advanced", exact: true }).click();
      await expandAllSettingsSections(page);
      await expect(
        page
          .locator(".ilb-settings-section--public")
          .getByLabel("Public-facing pages: block by location"),
      ).toBeChecked();
      await page.getByRole("button", { name: "Simple", exact: true }).click();
      await expect(enable).toBeChecked();
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
    }
  });

  test("Simple mode explains and validates the blocked response", async ({
    page,
  }) => {
    await openAdmin(page, "settings", "&view=simple");
    const baseline = await getSettings(page);
    const configured = cloneSettings(baseline);
    const publicValidation =
      Number(getPath(configured, "validation.public")) || 0;
    setPath(
      configured,
      "validation.public",
      publicValidation % 2 === 1 ? publicValidation : publicValidation + 1,
    );
    setPath(configured, "public.matching_rule", 1);
    setPath(configured, "public.response_code", 307);
    setPath(
      configured,
      "public.redirect_uri",
      "https://blocked.iplocationblock.com/",
    );
    setPath(
      configured,
      "public.response_msg",
      "This site is not available in your location.",
    );

    try {
      await saveSettings(page, configured);
      await openAdmin(page, "settings", "&view=simple");

      const response = page.getByLabel("When a visitor is blocked");
      const destination = page.getByLabel("Send blocked visitors to");
      const save = page.getByRole("button", { name: "Save Changes" });

      await expect(response).toHaveValue("redirect");
      await expect(destination).toHaveValue(
        "https://blocked.iplocationblock.com/",
      );
      await expect(
        page.getByText("IP Location Block hosted page", { exact: true }),
      ).toBeVisible();
      await expect(
        page.getByText(
          "Links back to protected pages on this site will still be blocked.",
          { exact: false },
        ),
      ).toBeVisible();
      await expect(
        page.getByRole("link", { name: /Preview destination/ }),
      ).toHaveAttribute("href", "https://blocked.iplocationblock.com/");

      await destination.fill(`${new URL(page.url()).origin}/blocked`);
      await expect(
        page.getByText(/same hostname as your protected site/i),
      ).toBeVisible();
      await expect(save).toBeDisabled();

      await response.selectOption("message");
      const message = page.getByLabel("Blocked message");
      await expect(message).toHaveValue(
        "This site is not available in your location.",
      );
      await expect(save).toBeEnabled();
      await message.fill("Blocked by the site owner.");

      await response.selectOption("redirect");
      await expect(save).toBeDisabled();
      await page.getByRole("button", { name: "Use hosted default" }).click();
      await expect(destination).toHaveValue(
        "https://blocked.iplocationblock.com/",
      );
      await expect(save).toBeEnabled();

      await save.click();
      await expect(page.locator(".components-snackbar")).toContainText(
        "Settings saved.",
      );
      const saved = await getSettings(page);
      expect(saved.public.redirect_uri).toBe(
        "https://blocked.iplocationblock.com/",
      );
      expect(saved.public.response_msg).toBe("Blocked by the site owner.");
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
    }
  });

  test("provider switches preserve blocking and pause regional rules", async ({
    page,
  }) => {
    await openAdmin(page, "settings", "&view=simple");
    const baseline = await getSettings(page);

    test.skip(
      !(await page
        .getByRole("heading", { name: "IP Location Block", exact: true })
        .count()),
      "This regression requires the native provider fixture.",
    );

    const configured = cloneSettings(baseline);
    const publicValidation =
      Number(getPath(configured, "validation.public")) || 0;
    setPath(
      configured,
      "validation.public",
      publicValidation % 2 === 1 ? publicValidation : publicValidation + 1,
    );
    setPath(configured, "public.matching_rule", 0);
    setPath(configured, "public.white_list", "MK,US:State:Kentucky");

    try {
      await saveSettings(page, configured);
      const persisted = await getSettings(page);
      await openAdmin(page, "settings", "&view=simple");

      const locationToggle = page.getByLabel("Enable location blocking");
      await expect(locationToggle).toBeChecked();
      await expect(locationToggle).toBeEnabled();

      await page
        .getByRole("button", { name: "Switch provider", exact: true })
        .click();
      await page
        .getByLabel("Provider", { exact: true })
        .selectOption("IP2Location");
      await page
        .getByRole("button", { name: "Use local provider", exact: true })
        .click();
      await page
        .getByRole("dialog", { name: "Switch to IP2Location?" })
        .getByRole("button", { name: "Continue", exact: true })
        .click();

      await expect(locationToggle).toBeChecked();
      await expect(locationToggle).toBeEnabled();
      await expect(page.getByLabel("Countries")).toBeEnabled();
      await expect(
        page.getByText("North Macedonia (MK)").first(),
      ).toBeVisible();
      await expect(
        page.getByText("Regional rules are paused.", { exact: true }),
      ).toBeVisible();

      const regionalEditor = page.locator(".ilb-simple__precise-editor");
      await expect(regionalEditor).toHaveAttribute("aria-disabled", "true");
      await expect(regionalEditor.getByLabel("Country").first()).toBeDisabled();
      await expect(regionalEditor.getByLabel("Name").first()).toBeDisabled();
      await expect(
        regionalEditor.getByRole("button", {
          name: "Add a regional rule",
          exact: true,
        }),
      ).toBeDisabled();
      expect(await getSettings(page)).toEqual(persisted);

      await page.getByRole("button", { name: "Save Changes" }).click();
      await expect(page.locator(".components-snackbar")).toContainText(
        "Settings saved.",
      );

      const saved = await getSettings(page);
      expect(Number(saved.validation.public) % 2).toBe(1);
      expect(saved.public.white_list).toBe("MK,US:State:Kentucky");
      expect(saved.providers.IP2Location).toBeTruthy();
      expect(saved.providers["IP Location Block"]).toBeFalsy();
    } finally {
      await saveSettings(page, baseline);
      await clearLocationCache(page);
    }
  });

  test("provider disconnect is staged, shared, and undoable", async ({
    page,
  }) => {
    await openAdmin(page, "settings", "&view=simple");
    const baseline = await getSettings(page);
    const disconnect = page.getByRole("button", {
      name: "Disconnect",
      exact: true,
    });
    test.skip(
      !(await disconnect.count()),
      "The configured fixture has no connected provider.",
    );

    await disconnect.click();
    await expect(
      page.getByRole("dialog", { name: "Disconnect IP Location Block?" }),
    ).toBeVisible();
    await page
      .getByRole("button", { name: "Disconnect provider", exact: true })
      .click();

    await expect(page.locator(".ilb-provider-pending")).toBeVisible();
    await expect(page.getByLabel("Enable location blocking")).toBeDisabled();
    expect(await getSettings(page)).toEqual(baseline);

    await page.getByRole("button", { name: "Advanced", exact: true }).click();
    await expect(page.locator(".ilb-provider-pending")).toBeVisible();
    await page
      .getByRole("button", { name: "Geolocation API settings", exact: true })
      .click();
    await expect(
      page.getByRole("checkbox", { name: "IP Location Block", exact: true }),
    ).not.toBeChecked();
    await page.getByRole("button", { name: "Simple", exact: true }).click();
    await expect(
      page.getByRole("heading", {
        name: "State and region blocking",
      }),
    ).toBeVisible();
    await expect(
      page.getByText(
        "Native Mode adds state and region rules using frequently updated geolocation data.",
      ),
    ).toBeVisible();
    await expect(
      page.getByText("Native Mode · Optional", { exact: true }),
    ).toBeVisible();
    const learnLink = page.getByRole("link", {
      name: /Learn more/,
    });
    await expect(learnLink).toBeVisible();
    await expect(learnLink).toHaveAttribute(
      "href",
      "https://iplocationblock.com/docs/blocking-rules/state-region/",
    );
    await expect(
      page.getByRole("button", { name: "Connect API key", exact: true }),
    ).toBeDisabled();
    await page.getByRole("button", { name: "Undo", exact: true }).click();

    await expect(page.locator(".ilb-provider-pending")).toHaveCount(0);
    await expect(disconnect).toBeVisible();
    await expect(page.locator(".ilb-provider-promo")).toHaveCount(0);
    expect(await getSettings(page)).toEqual(baseline);
  });
});
