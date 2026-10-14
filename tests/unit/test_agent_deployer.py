"""
Pruebas unitarias para monitor/agent_deployer.py
Verifica la orquestación de despliegue remoto en Windows y Linux,
construcción de comandos y validación en base de datos.
"""

from unittest.mock import MagicMock, patch
import pytest

from monitor.agent_deployer import (
    get_target_url,
    deploy_linux_ssh,
    deploy_windows_winrm,
    verify_asset_in_db,
    run_deployment,
    DEV_SERVER_URL,
    PROD_SERVER_URL,
)


class TestAgentDeployer:
    def test_get_target_url(self):
        url_dev, base_dev = get_target_url("dev")
        assert url_dev == DEV_SERVER_URL
        assert base_dev == "http://10.20.23.221"

        url_prod, base_prod = get_target_url("prod")
        assert url_prod == PROD_SERVER_URL
        assert base_prod == "http://10.20.23.252"

    def test_deploy_linux_ssh_mocked(self):
        mock_client = MagicMock()
        mock_channel = MagicMock()
        mock_channel.recv_exit_status.return_value = 0

        mock_stdout = MagicMock()
        mock_stdout.channel = mock_channel
        mock_stdout.read.return_value = b"[+] Despliegue finalizado exitosamente"

        mock_stderr = MagicMock()
        mock_stderr.read.return_value = b""

        mock_client.exec_command.return_value = (MagicMock(), mock_stdout, mock_stderr)

        with patch("paramiko.SSHClient", return_value=mock_client):
            res = deploy_linux_ssh(
                host="10.20.23.100",
                user="admin_soporte",
                password="secret_password",
                target="dev",
                action="install",
            )

        assert res["success"] is True
        assert res["host"] == "10.20.23.100"
        assert res["os"] == "linux"
        assert "Despliegue finalizado" in res["stdout"]

    def test_deploy_linux_ssh_switch(self):
        mock_client = MagicMock()
        mock_channel = MagicMock()
        mock_channel.recv_exit_status.return_value = 0

        mock_stdout = MagicMock()
        mock_stdout.channel = mock_channel
        mock_stdout.read.return_value = b"[+] Agente GLPI conmutado a prod"
        mock_stderr = MagicMock()
        mock_stderr.read.return_value = b""

        mock_client.exec_command.return_value = (MagicMock(), mock_stdout, mock_stderr)

        with patch("paramiko.SSHClient", return_value=mock_client):
            res = deploy_linux_ssh(
                host="10.20.23.100",
                user="admin_soporte",
                target="prod",
                action="switch",
            )

        assert res["success"] is True
        # Verifica que el comando contenga /deploy/switch/linux
        called_cmd = mock_client.exec_command.call_args[0][0]
        assert "/deploy/switch/linux" in called_cmd

    def test_deploy_windows_winrm_mocked(self):
        mock_session = MagicMock()
        mock_resp = MagicMock()
        mock_resp.status_code = 0
        mock_resp.std_out = b"DESPLIEGUE FINALIZADO EXITOSAMENTE"
        mock_resp.std_err = b""
        mock_session.run_ps.return_value = mock_resp

        with patch("winrm.Session", return_value=mock_session) as mock_winrm_cls:
            res = deploy_windows_winrm(
                host="10.20.23.150",
                user="administrador",
                password="DomainPassword123",
                domain="CORP",
                target="dev",
                action="install",
            )

        assert res["success"] is True
        assert res["host"] == "10.20.23.150"
        assert res["os"] == "windows"
        assert "FINALIZADO" in res["stdout"]

        # Verifica que se haya autenticado con el formato DOMINIO\usuario
        init_args = mock_winrm_cls.call_args
        assert init_args[1]["auth"] == ("CORP\\administrador", "DomainPassword123")

    def test_verify_asset_in_db_mocked(self):
        mock_conn = MagicMock()
        mock_cur = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cur

        fake_row = {
            "id": 42,
            "name": "PC-FINANZAS-01",
            "ip_address": "10.20.23.150",
            "operating_system": "Windows 10 Pro (x86)",
            "model": "HP Compaq Pro 6300",
            "serial_number": "MXL24519FF",
        }
        mock_cur.fetchone.return_value = fake_row

        with patch("monitor.monitor_db.get_db_connection", return_value=mock_conn):
            v = verify_asset_in_db("10.20.23.150")

        assert v["verified"] is True
        assert v["asset"]["id"] == 42
        assert v["asset"]["name"] == "PC-FINANZAS-01"

    def test_run_deployment_full_flow(self):
        with patch("monitor.agent_deployer.deploy_linux_ssh") as mock_deploy:
            mock_deploy.return_value = {"success": True, "host": "10.20.23.100", "os": "linux"}
            with patch("monitor.agent_deployer.verify_asset_in_db") as mock_verify:
                mock_verify.return_value = {"verified": True, "asset": {"id": 10}}
                with patch("time.sleep"):
                    res = run_deployment(
                        host="10.20.23.100",
                        os_type="linux",
                        user="root",
                        action="install",
                        wait_verify=True,
                    )

        assert res["success"] is True
        assert res["db_verification"]["verified"] is True

    def test_deploy_windows_rpc_webclient_command(self):
        from monitor.agent_deployer import deploy_windows_rpc
        mock_proc = MagicMock()
        mock_proc.returncode = 0
        mock_proc.stdout = "[*] SMBv2.1 dialect used\r\n[+] Instalacion finalizada"
        mock_proc.stderr = ""

        with patch("subprocess.run", return_value=mock_proc) as mock_run:
            res = deploy_windows_rpc(
                host="10.20.23.245",
                user="admin_soporte",
                password="password123",
                target="dev",
                action="install",
            )

        assert res["success"] is True
        assert res["method"] == "rpc/wmi"
        called_args = mock_run.call_args[0][0]
        # Verificar que se pase -codec latin1
        assert "-codec" in called_args
        assert "latin1" in called_args
        # Verificar que el comando de PowerShell use WebClient y no irm
        cmd_str = called_args[-1]
        assert "DownloadString" in cmd_str
        assert "irm " not in cmd_str

    def test_deploy_windows_rpc_decoding_warning_not_fatal(self):
        from monitor.agent_deployer import deploy_windows_rpc
        mock_proc = MagicMock()
        mock_proc.returncode = 0
        mock_proc.stdout = "[*] SMBv2.1 dialect used\r\n[-] Decoding error detected, consider running chcp.com\r\n[+] OK"
        mock_proc.stderr = ""

        with patch("subprocess.run", return_value=mock_proc):
            res = deploy_windows_rpc(
                host="10.20.23.245",
                user="admin_soporte",
                password="password123",
                target="dev",
                action="install",
            )

        assert res["success"] is True
        assert res["method"] == "rpc/wmi"
