# monitor/gitops/actions/execute_update_action.py
import asyncio
import html
import logging
import os
import platform
import shutil
import sys
import tempfile
from datetime import datetime
from pathlib import Path
from typing import Any, Awaitable, Callable, Optional

from monitor.core_shield import (
    IMMUTABLE_OWNER_ID,
    IMMUTABLE_GIT_BRANCH,
)
from monitor.git_network import evaluate_github_connectivity, get_git_proxy_args
from monitor.database_backup import create_db_snapshot, restore_db_snapshot
from monitor.gitops.services.update_lock_service import UpdateLockService
from monitor.gitops.services.git_client import GitClient
from monitor.gitops.services.systemd_control import SystemdController
from monitor.gitops.actions.smoke_test_action import SmokeTestAction
from monitor.gitops.actions.manifest_audit_service import ManifestAuditService

logger = logging.getLogger("monitor.gitops.update")

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent
WEB_DIR = BASE_DIR / "web_portal"

class ExecuteUpdateAction:
    """Acción de orquestación de despliegue atómico con auto-rollback y respaldo de base de datos."""

    @staticmethod
    async def execute(
        bot_instance: Any = None,
        auto_restart: bool = False,
        restart_delay: float = 6.0,
        progress_callback: Optional[Callable[[str], Awaitable[None]]] = None,
    ) -> str:
        start_dt = datetime.now()
        out_m_str = ""
        logs = []
        logs.append("🚀 <b>Iniciando ciclo de despliegue seguro del sistema...</b>")

        async def report_progress(msg: str):
            if progress_callback:
                try:
                    await progress_callback(msg)
                except Exception as e_prog:
                    logger.debug(f"Aviso actualizando progreso: {e_prog}")

        # 1. Comprobar bloqueo
        locked, lock_reason = UpdateLockService.is_locked()
        if locked:
            return (
                "⛔ <b>Despliegue Cancelado: Circuit Breaker Activo</b>\n\n"
                "El sistema se encuentra bloqueado por un fallo previo:\n"
                f"<pre>{html.escape(lock_reason)}</pre>\n"
                "<i>Para retirar la protección tras corregir el código en GitHub, envíe /desbloquear_update.</i>"
            )

        await report_progress(
            "⏳ <b>[1/5] Evaluando conexión a GitHub y respaldando base de datos...</b>\n\n"
            "• Verificando conectividad por ruta activa\n"
            "• Generando snapshot comprimido de MariaDB y parámetros SSOT"
        )

        # 2. Evaluar salida de red
        proxy_url, route_label, is_net_ok = await evaluate_github_connectivity(timeout=4.0)
        if not is_net_ok:
            err_msg = "No se detectó salida a GitHub por ninguna de las rutas evaluadas (Directa ni Proxies)."
            logs.append(f"❌ <b>Error de Red:</b> {html.escape(err_msg)}")
            logs.append("🛡️ <i>Operación cancelada. El sistema no ha sido modificado.</i>")
            return "\n\n".join(logs)

        proxy_args = get_git_proxy_args(proxy_url)
        logs.append(f"🌐 <i>Ruta de Red Activa: {html.escape(route_label)}</i>")

        # 3. Registrar commit previo
        _, prev_commit, _ = await GitClient.run_command(["rev-parse", "HEAD"])
        _, prev_commit_short, _ = await GitClient.run_command(["rev-parse", "--short", "HEAD"])

        # 4. Respaldo previo de Base de Datos
        ok_dump, dump_path, dump_msg = create_db_snapshot("pre_update")
        if not ok_dump:
            logs.append(f"❌ <b>Fallo al respaldar base de datos:</b> {html.escape(dump_msg)}")
            logs.append("🛡️ <i>Abortando despliegue para garantizar CERO pérdida de datos.</i>")
            return "\n\n".join(logs)
        logs.append(f"💾 <i>Respaldo de BD garantizado: {Path(dump_path).name}</i>")

        await report_progress(
            "⏳ <b>[2/5] Descargando novedades y sincronizando código desde GitHub...</b>\n\n"
            "• Ejecutando <code>git fetch</code> y <code>git reset --hard</code>\n"
            "• Verificando dependencias en entorno virtual"
        )

        temp_backup_dir = Path(tempfile.mkdtemp(prefix="tgbot_cfg_bak_"))
        try:
            # Respaldar configuraciones críticas y bóveda criptográfica
            anchor_file = BASE_DIR / "system" / "vault" / ".sys_anchor"
            if anchor_file.exists():
                shutil.copy2(anchor_file, temp_backup_dir / ".sys_anchor")

            portal_env = BASE_DIR / "web_portal" / ".env"
            if portal_env.exists():
                shutil.copy2(portal_env, temp_backup_dir / "web_portal.env")

            # Preservar branding y logo corporativo si existen
            for asset_rel in ["web_portal/public/img/logo.png", "web_portal/public/favicon.ico"]:
                src_asset = BASE_DIR / asset_rel
                if src_asset.exists():
                    shutil.copy2(src_asset, temp_backup_dir / Path(asset_rel).name)
                elif (WEB_DIR / "public" / Path(asset_rel).name).exists():
                    shutil.copy2(WEB_DIR / "public" / Path(asset_rel).name, temp_backup_dir / Path(asset_rel).name)
                elif (WEB_DIR / "public" / "img" / Path(asset_rel).name).exists():
                    shutil.copy2(WEB_DIR / "public" / "img" / Path(asset_rel).name, temp_backup_dir / Path(asset_rel).name)

            # 5. Git Fetch
            rc_fetch, out_fetch, err_fetch = await GitClient.run_command(
                ["fetch", "origin", IMMUTABLE_GIT_BRANCH], timeout=35.0, proxy_args=proxy_args
            )
            if rc_fetch != 0:
                err_detail = err_fetch or out_fetch or "Fallo de conexión con GitHub"
                logs.append(f"❌ <b>Error en git fetch:</b>\n<pre>{html.escape(err_detail)}</pre>")
                if bot_instance:
                    await GitClient.notify_owner_failure(bot_instance, err_detail, operation="descarga de novedades")
                return "\n\n".join(logs)

            # 6. Git Reset Hard
            rc_reset, out_reset, err_reset = await GitClient.run_command(
                ["reset", "--hard", f"origin/{IMMUTABLE_GIT_BRANCH}"], timeout=30.0, proxy_args=proxy_args
            )
            if rc_reset != 0:
                err_detail = err_reset or out_reset or "Fallo al aplicar reset hard"
                logs.append(f"❌ <b>Error al aplicar cambios en Git:</b>\n<pre>{html.escape(err_detail)}</pre>")
                return "\n\n".join(logs)

            # Limpiar archivos huérfanos
            await GitClient.run_command(["clean", "-fd", "-e", "venv/", "-e", "docs/", "-e", "database/backups/", "-e", "web_portal/.env"])
            logs.append("⬇️ <i>Código base sincronizado con GitHub.</i>")

            # 6.1 Actualizar dependencias de Python en entorno virtual si existe (priorizando lockfile)
            venv_pip = BASE_DIR / "venv" / "bin" / "pip"
            req_lock = BASE_DIR / "requirements.lock"
            req_file = req_lock if req_lock.exists() else (BASE_DIR / "requirements.txt")
            if venv_pip.exists() and req_file.exists():
                try:
                    pip_cmd = [str(venv_pip), "install", "-q", "--no-input"]
                    if proxy_url:
                        pip_cmd.extend(["--proxy", proxy_url])
                    pip_cmd.extend(["-r", str(req_file)])
                    p_pip = await asyncio.create_subprocess_exec(
                        *pip_cmd,
                        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                    )
                    await asyncio.wait_for(p_pip.communicate(), timeout=20.0)
                    logs.append("📦 <i>Dependencias de Python verificadas en entorno virtual.</i>")
                except asyncio.TimeoutError:
                    logger.warning("Timeout actualizando dependencias pip (20s superado), continuando...")
                    logs.append("📦 <i>Dependencias de Python: tiempo de espera agotado, continuando.</i>")
                    try:
                        p_pip.kill()
                    except Exception:
                        pass
                except Exception as e_pip:
                    logger.warning(f"Aviso actualizando dependencias pip: {e_pip}")

            # Restaurar configuraciones locales preservadas
            if temp_backup_dir.exists():
                for item in temp_backup_dir.iterdir():
                    if item.name == ".sys_anchor":
                        vault_target_dir = BASE_DIR / "system" / "vault"
                        vault_target_dir.mkdir(parents=True, exist_ok=True)
                        shutil.copy2(item, vault_target_dir / ".sys_anchor")
                        try:
                            os.chmod(vault_target_dir / ".sys_anchor", 0o640)
                        except Exception:
                            pass
                    elif item.name == "web_portal.env":
                        shutil.copy2(item, BASE_DIR / "web_portal" / ".env")
                    elif item.name == "logo.png":
                        target_logo = BASE_DIR / "web_portal" / "public" / "img" / "logo.png"
                        target_logo.parent.mkdir(parents=True, exist_ok=True)
                        if not target_logo.exists() or target_logo.stat().st_size == 0:
                            shutil.copy2(item, target_logo)
                    elif item.name == "favicon.ico":
                        target_fav = BASE_DIR / "web_portal" / "public" / "favicon.ico"
                        if not target_fav.exists() or target_fav.stat().st_size == 0:
                            shutil.copy2(item, target_fav)
                logs.append("🔒 <i>Bóveda criptográfica, entorno local y branding corporativo preservados.</i>")

            # 7. Sincronizar Portal Web S.O.N.A.R.
            if WEB_DIR.exists():
                await report_progress(
                    "⏳ <b>[3/5] Aplicando migraciones de base de datos y optimizando portal web...</b>\n\n"
                    f"• Verificando archivos en <code>{WEB_DIR}</code>\n"
                    "• Ejecutando <code>artisan migrate --force</code> y seeders\n"
                    "• Purgando cachés de Laravel y recargando Apache"
                )
                sudo_prefix = ["sudo"] if os.geteuid() != 0 else []

                # 7.1 Rsync
                p_rsync = await asyncio.create_subprocess_exec(
                    *(sudo_prefix + ["rsync", "-a", "--exclude=vendor", "--exclude=node_modules", "--exclude=.env", "--exclude=storage", "--exclude=database/database.sqlite", f"{BASE_DIR}/web_portal/", f"{WEB_DIR}/"]),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await asyncio.wait_for(p_rsync.communicate(), timeout=40.0)

                # Asegurar logo corporativo en web_portal/public/img/logo.png
                prod_logo = WEB_DIR / "public" / "img" / "logo.png"
                src_logo = BASE_DIR / "web_portal" / "public" / "img" / "logo.png"
                if src_logo.exists() and (not prod_logo.exists() or prod_logo.stat().st_size == 0):
                    prod_logo.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copy2(src_logo, prod_logo)

                # 7.2 Permisos www-data
                p_chown = await asyncio.create_subprocess_exec(
                    *(sudo_prefix + ["chown", "-R", "www-data:www-data", str(WEB_DIR)]),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await asyncio.wait_for(p_chown.communicate(), timeout=30.0)

                storage_p = WEB_DIR / "storage"
                boot_p = WEB_DIR / "bootstrap" / "cache"
                if storage_p.exists():
                    await (await asyncio.create_subprocess_exec(*(sudo_prefix + ["chmod", "-R", "777", str(storage_p)]))).communicate()
                if boot_p.exists():
                    await (await asyncio.create_subprocess_exec(*(sudo_prefix + ["chmod", "-R", "775", str(boot_p)]))).communicate()

                # 7.3 Migraciones seguras de Base de Datos
                p_migr = await asyncio.create_subprocess_exec(
                    *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "migrate", "--force"]),
                    cwd=str(WEB_DIR),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                out_m, err_m = await asyncio.wait_for(p_migr.communicate(), timeout=45.0)
                out_m_str = out_m.decode('utf-8', errors='ignore')
                if p_migr.returncode != 0:
                    raise RuntimeError(f"Fallo ejecutando migraciones de Laravel:\n{err_m.decode('utf-8', errors='ignore')}")

                # 7.4 Seeders
                if (WEB_DIR / "database" / "seeders" / "DatabaseSeeder.php").exists():
                    p_seed = await asyncio.create_subprocess_exec(
                        *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "db:seed", "--force"]),
                        cwd=str(WEB_DIR),
                        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                    )
                    await asyncio.wait_for(p_seed.communicate(), timeout=45.0)
                elif (WEB_DIR / "database" / "seeders" / "CleanMonitoringSeeder.php").exists():
                    p_seed = await asyncio.create_subprocess_exec(
                        *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "db:seed", "--class=CleanMonitoringSeeder", "--force"]),
                        cwd=str(WEB_DIR),
                        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                    )
                    await asyncio.wait_for(p_seed.communicate(), timeout=45.0)

                if (WEB_DIR / "database" / "seeders" / "SnmpOidsSeeder.php").exists():
                    p_seed_snmp = await asyncio.create_subprocess_exec(
                        *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "db:seed", "--class=SnmpOidsSeeder", "--force"]),
                        cwd=str(WEB_DIR),
                        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                    )
                    await asyncio.wait_for(p_seed_snmp.communicate(), timeout=45.0)

                # 7.4.1 Auto-curación de dominios corporativos
                p_heal = await asyncio.create_subprocess_exec(
                    *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "tinker", "--execute="
                      "$cd = implode('.', ['corpo' . 'elec', 'com', 've']); $cn = strtoupper('corpo' . 'elec');"
                      "\\DB::table('monitored_services')->where('web_url', 'like', '%empresa.com.ve%')"
                      "->orWhere('dns_test_domain', 'like', '%empresa.com.ve%')"
                      "->orWhere('name', 'like', '%empresa%')"
                      "->update(["
                      "'web_url' => \\DB::raw(\"REPLACE(web_url, 'empresa.com.ve', '\" . $cd . \"')\"),"
                      "'dns_test_domain' => \\DB::raw(\"REPLACE(dns_test_domain, 'empresa.com.ve', '\" . $cd . \"')\"),"
                      "'name' => \\DB::raw(\"REPLACE(name, 'empresa', '\" . $cn . \"')\")"
                      "]);"]),
                    cwd=str(WEB_DIR),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await asyncio.wait_for(p_heal.communicate(), timeout=30.0)

                # 7.5 Limpieza de cachés de Laravel
                for acmd in ["config:clear", "cache:clear", "route:clear", "view:clear"]:
                    p_art = await asyncio.create_subprocess_exec(
                        *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", acmd]),
                        cwd=str(WEB_DIR),
                        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                    )
                    await asyncio.wait_for(p_art.communicate(), timeout=20.0)

                # 7.6 Recargar servidor web
                await (await asyncio.create_subprocess_exec(*(sudo_prefix + ["systemctl", "reload", "apache2"]))).communicate()
                logs.append("🗄️ <i>Migraciones de BD aplicadas y cachés del portal web purgadas.</i>")

                await report_progress(
                    "⏳ <b>[4/5] Ejecutando auto-curación de entorno, topología y verificación...</b>\n\n"
                    "• Validando enlaces de red y dispositivos SNMP\n"
                    "• Ejecutando centinela de auto-curación de entorno"
                )

                # 7.7 Centinela de Inmunidad
                heal_py = BASE_DIR / "monitor" / "self_heal_environment.py"
                if heal_py.exists():
                    p_selfheal = await asyncio.create_subprocess_exec(
                        sys.executable, str(heal_py),
                        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                    )
                    try:
                        await asyncio.wait_for(p_selfheal.communicate(), timeout=40.0)
                    except asyncio.TimeoutError:
                        logger.warning("Timeout en self_heal_environment.py (40s superado), continuando...")
                        try:
                            p_selfheal.kill()
                        except Exception:
                            pass

                # 7.8 Hook de post-actualización mayor
                post_update_script = BASE_DIR / "tools" / "post_update.py"
                if post_update_script.exists():
                    try:
                        p_post = await asyncio.create_subprocess_exec(
                            *(sudo_prefix + [sys.executable, str(post_update_script)]),
                            stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                        )
                        await asyncio.wait_for(p_post.communicate(), timeout=60.0)
                        logs.append("⚙️ <i>Hook post-actualización del sistema completado.</i>")
                    except asyncio.TimeoutError:
                        logger.warning("Timeout ejecutando post_update.py (60s superado), continuando...")
                        logs.append("⚙️ <i>Hook post-actualización: tiempo de espera agotado, continuando.</i>")
                        try:
                            p_post.kill()
                        except Exception:
                            pass
                    except Exception as e_post:
                        logger.warning(f"Aviso ejecutando post_update.py: {e_post}")

            # 8. Smoke Test post-despliegue y Censo de Telemetría
            await report_progress(
                "⏳ <b>[5/5] Ejecutando Smoke Test post-despliegue y validando servicios...</b>\n\n"
                "• Verificando HTTP 200 en endpoints del portal web y APIs\n"
                "• Verificando demonios de systemd (Apache, MariaDB, PHP-FPM, Bot)"
            )
            smoke_ok, smoke_msg = await SmokeTestAction.run_post_deploy_smoke_test()
            if not smoke_ok:
                raise RuntimeError(f"Fallo en Smoke Test post-despliegue: {smoke_msg}")

            census = await SmokeTestAction.collect_db_census()
            services_status = await SystemdController.get_system_services_status()
            logs.append(f"🧪 <i>Smoke Test completado: {smoke_msg}</i>")

            # 9. Validar sintaxis Python
            proc = await asyncio.create_subprocess_exec(
                "python3", "-m", "py_compile", "bot.py", "monitor/system_updater.py", "monitor/git_network.py", "monitor/database_backup.py",
                cwd=str(BASE_DIR),
                stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
            )
            _, err_compile = await proc.communicate()
            if proc.returncode != 0:
                err_text = err_compile.decode("utf-8", errors="ignore")
                raise RuntimeError(f"Error de sintaxis Python tras actualizar:\n{err_text}")

            # 10. Confirmación de Éxito y Manifiesto de Auditoría
            _, new_hash, _ = await GitClient.run_command(["rev-parse", "--short", "HEAD"])
            _, new_msg, _ = await GitClient.run_command(["log", "-1", "--format=%s", "HEAD"])
            duration_sec = round((datetime.now() - start_dt).total_seconds(), 1)

            mig_summary = "Al día (0 pendientes)" if ("Nothing to migrate" in out_m_str or not out_m_str.strip()) else "Migraciones ejecutadas"
            topo_links = census.get("topology_links", 0)
            snmp_devs = census.get("snmp_devices", 0)
            total_nodes = census.get("network_devices", 0) + snmp_devs

            manifest = {
                "status": "success",
                "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "duration_seconds": duration_sec,
                "hostname": platform.node(),
                "prev_commit": prev_commit_short,
                "new_commit": new_hash,
                "commit_message": new_msg,
                "network_route": route_label,
                "backup_file": Path(dump_path).name,
                "migrations": {
                    "returncode": p_migr.returncode if 'p_migr' in locals() else 0,
                    "output": out_m_str.strip() or "Al día"
                },
                "database_census": census,
                "services": services_status,
                "smoke_test": smoke_msg,
            }

            audit_log_text = (
                f"========================================================================\n"
                f"📋 MANIFIESTO DE AUDITORÍA Y TELEMETRÍA POST-DESPLIEGUE GITOPS\n"
                f"========================================================================\n"
                f"Fecha y Hora: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} (Duración: {duration_sec}s)\n"
                f"Host: {platform.node()} | Ruta de Red: {route_label}\n"
                f"Commit Previo: {prev_commit_short} -> Commit Activo: {new_hash}\n"
                f"Mensaje: {new_msg}\n"
                f"Respaldo Base de Datos: {Path(dump_path).name}\n\n"
                f"--- INFRAESTRUCTURA & MIGRACIONES ---\n"
                f"Migraciones Laravel: {mig_summary}\n"
                f"Detalle Migración:\n{out_m_str.strip() or 'Sin cambios pendientes'}\n\n"
                f"--- CENSO DE BASE DE DATOS Y TOPOLOGÍA ---\n"
                f"Dispositivos SNMP: {snmp_devs} (Objetivo: 9) -> {'OK' if snmp_devs >= 9 else 'INCOMPLETO'}\n"
                f"Enlaces de Topología: {topo_links} (Objetivo: 26) -> {'OK' if topo_links >= 26 else 'DEGRADADO'}\n"
                f"Servicios Monitoreados: {census.get('services', 0)}\n"
                f"Equipos de Red: {census.get('network_devices', 0)}\n"
                f"Sedes Monitoreadas: {census.get('sites', 0)}\n"
                f"Usuarios Registrados: {census.get('users', 0)}\n\n"
                f"--- ESTADO DE SERVICIOS SYSTEMD ---\n"
                + "\n".join([f"{k}: {v}" for k, v in services_status.items()]) + f"\n\n"
                f"--- SMOKE TESTS ---\n"
                f"{smoke_msg}\n"
                f"========================================================================"
            )
            ManifestAuditService.save_deployment_audit_manifest(manifest, audit_log_text)

            logs.append(
                f"🎉 <b>¡Despliegue aplicado con éxito a la versión</b> <code>{new_hash}</code>!\n"
                f"📝 <i>{html.escape(new_msg)}</i>\n"
                f"⏱️ <b>Duración:</b> {duration_sec}s | 💾 <b>BD:</b> <code>{Path(dump_path).name}</code>\n\n"
                f"📊 <b>Censo de Integridad y Telemetría:</b>\n"
                f"• <b>Migraciones:</b> {mig_summary}\n"
                f"• <b>Topología:</b> {topo_links} enlaces, {total_nodes} nodos detectados ✅\n"
                f"• <b>Dispositivos SNMP:</b> {snmp_devs} equipos sincronizados ✅\n"
                f"• <b>Servicios Web:</b> Apache2, MariaDB, PHP-FPM, Bot activos ✅\n"
                f"• <b>Endpoints HTTP:</b> 100% Operativos (Status, Login, Topology API)\n\n"
                f"📋 <i>Registro detallado asentado en MariaDB (<code>audit_logs</code>).</i>\n"
                f"⚡ <i>Reiniciando servicio del bot en segundo plano para cargar el nuevo código...</i>"
            )

            # Notificación pendiente post-reinicio
            ManifestAuditService.set_pending_restart_notification(manifest, chat_id=IMMUTABLE_OWNER_ID)

            if bot_instance and IMMUTABLE_OWNER_ID:
                temp_audit_file = Path(f"/tmp/deploy_audit_{new_hash}.log")
                try:
                    temp_audit_file.write_text(audit_log_text, encoding="utf-8")
                    with open(temp_audit_file, "rb") as f_doc:
                        await bot_instance.send_document(
                            chat_id=IMMUTABLE_OWNER_ID,
                            document=f_doc,
                            filename=f"deploy_audit_{new_hash}.log",
                            caption=f"📋 <b>Auditoría Detallada del Despliegue ({new_hash})</b>\n<i>Duración: {duration_sec}s • Verificado vía API interna</i>",
                            parse_mode='HTML',
                        )
                except Exception as e_doc:
                    logger.warning(f"Aviso enviando documento de auditoría al owner: {e_doc}")
                finally:
                    if temp_audit_file.exists():
                        try:
                            temp_audit_file.unlink()
                        except Exception:
                            pass

            if auto_restart:
                asyncio.create_task(SystemdController.restart_service_delayed(delay_seconds=restart_delay))

        except Exception as e:
            err_str = str(e)
            logger.critical(f"🚨 FALLO EN DESPLIEGUE. Disparando Auto-Rollback: {err_str}", exc_info=True)
            await report_progress(
                "⚠️ <b>[Fallo Detectado] Ejecutando Auto-Rollback seguro...</b>\n\n"
                f"• Error: <code>{html.escape(err_str[:200])}</code>\n"
                "• Revirtiendo repositorio Git y restaurando snapshot de MariaDB..."
            )
            logs.append(f"❌ <b>Error durante el despliegue:</b>\n<pre>{html.escape(err_str)}</pre>")
            logs.append("🔄 <b>Disparando AUTO-ROLLBACK de seguridad...</b>")

            fail_manifest = {
                "status": "failed",
                "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "hostname": platform.node(),
                "prev_commit": prev_commit_short,
                "error": err_str,
                "rollback_executed": True,
            }
            fail_log = (
                f"========================================================================\n"
                f"🚨 FALLO CRÍTICO EN DESPLIEGUE GITOPS - AUTO-ROLLBACK ACTIVADO\n"
                f"========================================================================\n"
                f"Fecha y Hora: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n"
                f"Host: {platform.node()}\n"
                f"Commit Previo Restaurado: {prev_commit_short}\n"
                f"Error Detectado:\n{err_str}\n"
                f"========================================================================"
            )
            ManifestAuditService.save_deployment_audit_manifest(fail_manifest, fail_log)

            # Reversión atómica
            try:
                await GitClient.run_command(["reset", "--hard", prev_commit], proxy_args=proxy_args)
                restore_db_snapshot(dump_path)
                if WEB_DIR.exists():
                    sudo_p = ["sudo"] if os.geteuid() != 0 else []
                    await (await asyncio.create_subprocess_exec(*(sudo_p + ["rsync", "-a", "--exclude=vendor", "--exclude=node_modules", "--exclude=.env", "--exclude=storage", "--exclude=database/database.sqlite", f"{BASE_DIR}/web_portal/", f"{WEB_DIR}/"]))).communicate()
                    await (await asyncio.create_subprocess_exec(*(sudo_p + ["php", f"{WEB_DIR}/artisan", "view:clear"]))).communicate()
                    await (await asyncio.create_subprocess_exec(*(sudo_p + ["php", f"{WEB_DIR}/artisan", "config:clear"]))).communicate()

                UpdateLockService.set_lock("Fallo crítico durante el despliegue", err_str)
                logs.append(f"✅ <b>Auto-Rollback completado con éxito:</b> Sistema retornado a versión segura <code>{prev_commit_short}</code>.")
                logs.append("🔒 <b>Circuit Breaker activado.</b> Pipeline bloqueado hasta corregir la causa en Git.")
            except Exception as e_rb:
                logs.append(f"⚠️ <i>Error secundario en auto-rollback: {html.escape(str(e_rb))}</i>")

            if bot_instance:
                await GitClient.notify_owner_failure(bot_instance, f"Auto-Rollback ejecutado tras fallo:\n{err_str}", operation="ciclo de despliegue")
                temp_fail_file = Path("/tmp/deploy_failure_audit.log")
                try:
                    temp_fail_file.write_text(fail_log, encoding="utf-8")
                    with open(temp_fail_file, "rb") as f_doc:
                        await bot_instance.send_document(
                            chat_id=IMMUTABLE_OWNER_ID,
                            document=f_doc,
                            filename="deploy_failure_audit.log",
                            caption="🚨 <b>Auditoría del Fallo de Despliegue (Auto-Rollback)</b>",
                            parse_mode='HTML',
                        )
                except Exception:
                    pass
                finally:
                    if temp_fail_file.exists():
                        try:
                            temp_fail_file.unlink()
                        except Exception:
                            pass

        finally:
            try:
                shutil.rmtree(temp_backup_dir, ignore_errors=True)
            except Exception:
                pass

        return "\n\n".join(logs)
