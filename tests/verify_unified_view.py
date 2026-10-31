import asyncio
from playwright.async_api import async_playwright
import os

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            viewport={'width': 1400, 'height': 900},
            extra_http_headers={'X-Automated-Test': 'true'}
        )
        page = await context.new_page()

        # Login
        await page.goto("http://127.0.0.1/login", wait_until="networkidle")
        await page.fill('input[name="login"]', 'britojq@gmail.com')
        await page.fill('input[name="password"]', 'PeneloPe91*')
        await page.click('button[type="submit"]')
        await page.wait_for_url("**/admin**", timeout=10000)
        await page.wait_for_timeout(500)

        artifacts_dir = "/home/britojab/.gemini/antigravity-cli/brain/faadaf35-22d4-442e-bbe4-587c2f2f1ffd"

        # 1. Test Radar View (/admin/discovery)
        await page.goto("http://127.0.0.1/admin/discovery")
        await page.wait_for_load_state('networkidle')
        await page.screenshot(path=f"{artifacts_dir}/unified_radar_view.png", full_page=False)
        print("Captured unified_radar_view.png")

        # Hover on first action button in Radar (Subnet)
        subnet_btn = page.locator('button[onclick="openSubnetModal()"]')
        if await subnet_btn.count() > 0:
            await subnet_btn.hover()
            await page.wait_for_timeout(400)
            await page.screenshot(path=f"{artifacts_dir}/unified_radar_tooltip.png")
            print("Captured unified_radar_tooltip.png")

        # 2. Test SNMP View (/admin/discovery?tab=snmp)
        await page.click('a[title="Monitoreo y Telemetría SNMP"]')
        await page.wait_for_load_state('networkidle')
        await page.screenshot(path=f"{artifacts_dir}/unified_snmp_view.png", full_page=False)
        print("Captured unified_snmp_view.png")

        # Hover on SNMP Add button
        snmp_add_btn = page.locator('button[onclick="openNewDeviceModal()"]')
        if await snmp_add_btn.count() > 0:
            await snmp_add_btn.hover()
            await page.wait_for_timeout(400)
            await page.screenshot(path=f"{artifacts_dir}/unified_snmp_tooltip.png")
            print("Captured unified_snmp_tooltip.png")

        # 3. Test old /admin/snmp redirect
        await page.goto("http://127.0.0.1/admin/snmp")
        await page.wait_for_load_state('networkidle')
        current_url = page.url
        print(f"Old /admin/snmp redirected to: {current_url}")

        # Check sidebar links
        sidebar_text = await page.locator('aside').inner_text()
        print("Sidebar contains 'Radar & Telemetría SNMP':", 'Radar & Telemetría SNMP' in sidebar_text)
        print("Sidebar contains '360':", '360' in sidebar_text)

        await browser.close()

if __name__ == '__main__':
    asyncio.run(main())
