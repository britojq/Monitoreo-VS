import asyncio
from playwright.async_api import async_playwright

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            viewport={'width': 1500, 'height': 1000},
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

        # 1. Visitar /admin/discovery
        await page.goto("http://127.0.0.1/admin/discovery", wait_until="networkidle")
        await page.wait_for_timeout(500)

        # Buscar 10.20.23.221
        await page.fill('input[name="search"]', '10.20.23.221')
        await page.press('input[name="search"]', 'Enter')
        await page.wait_for_timeout(1000)

        await page.screenshot(path=f"{artifacts_dir}/ecosystem_discovery_integrated.png", full_page=False)
        print("Captured ecosystem_discovery_integrated.png")

        content_disc = await page.content()
        assert "EN INVENTARIO" in content_disc, "Debe mostrar insignia EN INVENTARIO"
        assert "CENCARATIT (Servidor Desarrollo)" in content_disc, "Debe mostrar Nombre Etiqueta de inventario"
        assert "00:D8:61:44:95:B6" in content_disc, "Debe mostrar MAC física real de Lenovo"
        assert "360" not in content_disc, "NO debe contener '360'"
        print("✅ Verificación en /admin/discovery exitosa: Estado 'EN INVENTARIO', Nombre Etiqueta y MAC física verificados.")

        # 2. Visitar /admin/devices
        await page.goto("http://127.0.0.1/admin/devices", wait_until="networkidle")
        await page.wait_for_timeout(500)

        # Buscar CENCARATIT
        await page.fill('input[name="search"]', '10.20.23.221')
        await page.press('input[name="search"]', 'Enter')
        await page.wait_for_timeout(1000)

        # Expandir acordeón del activo 11519
        await page.evaluate("toggleAssetAccordion(11519)")
        await page.wait_for_timeout(800)

        await page.screenshot(path=f"{artifacts_dir}/ecosystem_device_accordion.png", full_page=False)
        print("Captured ecosystem_device_accordion.png")

        content_dev = await page.content()
        assert "Ecosistema" in content_dev and "Telemetr" in content_dev, "Debe mostrar bloque de Ecosistema & Telemetría"
        assert "Radar" in content_dev and "ARP" in content_dev, "Debe mostrar Radar & ARP"
        assert "Métricas SNMP" in content_dev, "Debe mostrar Métricas SNMP"
        assert "Tráfico de Red" in content_dev, "Debe mostrar Tráfico de Red"
        assert "Encendido Remoto" in content_dev, "Debe mostrar Encendido Remoto"
        assert "360°" not in content_dev, "NO debe contener '360°'"
        assert "SNMP 360" not in content_dev, "NO debe contener 'SNMP 360'"
        assert "Métricas 360" not in content_dev, "NO debe contener 'Métricas 360'"
        assert "Consola 360" not in content_dev, "NO debe contener 'Consola 360'"
        assert "Ficha 360" not in content_dev, "NO debe contener 'Ficha 360'"
        print("✅ Verificación en /admin/devices exitosa: Bloque unificado de telemetría visible y funcionando.")

        await browser.close()

if __name__ == '__main__':
    asyncio.run(main())
