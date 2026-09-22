# 🛡️ Plataforma de Monitoreo y Administración de Red
### *Sistema de Supervisión de Infraestructura y Telemetría de Servicios*

[![Python](https://img.shields.io/badge/Python-3.11+-3776AB?style=flat&logo=python&logoColor=white)](https://www.python.org/)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.11+-003545?style=flat&logo=mariadb&logoColor=white)](https://mariadb.org/)
[![Chart.js](https://img.shields.io/badge/Chart.js-4.x-FF6384?style=flat&logo=chartdotjs&logoColor=white)](https://www.chartjs.org/)
[![Apache](https://img.shields.io/badge/Apache-2.4-D22128?style=flat&logo=apache&logoColor=white)](https://httpd.apache.org/)
[![TailwindCSS](https://img.shields.io/badge/Tailwind-3.x-38B2AC?style=flat&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![License](https://img.shields.io/badge/License-AGPLv3-blue.svg)](LICENSE)

---

## 🌟 Descripción General

Esta plataforma es una solución integral para la supervisión de infraestructura tecnológica, telemetría de servicios de red y administración remota orientada a centros de operaciones y servidores Linux.

El sistema unifica un portal web interactivo desarrollado en **Laravel 11**, demonios de administración y alertas vía **Telegram**, motores concurrentes de escaneo en **Python** y soporte para despliegues distribuidos en clúster (**Master / Slave**).

---

## 🚀 Características Principales

- **Tablero Web en Tiempo Real:** Visualización del estado de servicios corporativos, sedes remotas y proxies con refresco reactivo asíncrono.
- **Histórico de Latencias (24 Horas):** Gráficas de osciloscopio interactivo (*sparklines*) con detección de micro-cortes y porcentaje de disponibilidad.
- **Bot de Gestión Telegram:** Consultas de estado, alertas tempranas de incidentes, diagnóstico de recursos de hardware y asistente virtual corporativo.
- **Arquitectura de Clúster (Master / Slave):** Despliegue de nodos primarios y réplicas con sincronización periódica de instantáneas de telemetría protegidas por token.
- **Autenticación Centralizada y Local:** Soporte para cuentas locales administrativas y autenticación mediante Directorio Activo (LDAP) con aprovisionamiento automático y control de acceso basado en roles (RBAC).
- **Acceso Remoto Integrado:** Consola Telnet interactiva en navegador (Xterm.js) y visualizador VNC embebido para administración de equipos de red.
- **Alertas de Sistema y Protección Física:** Monitoreo térmico continuo del procesador y notificaciones de encendido y accesos al servidor.

---

## 📋 Requisitos del Sistema

- **Sistema Operativo:** Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server 22.04 / 24.04 LTS.
- **PHP:** Versión 8.2 o superior (con extensiones `pdo_mysql`, `curl`, `gd`, `xml`, `mbstring`, `ldap`).
- **Python:** Versión 3.11 o superior.
- **Base de Datos:** MariaDB 10.11+ o MySQL 8.0+.
- **Servidor Web:** Apache 2.4 o Nginx.

---

## 🛠️ Instalación y Puesta en Marcha

El repositorio incluye un instalador automatizado para desplegar la plataforma:

```bash
# 1. Clonar el repositorio
git clone https://github.com/britojq/Monitoreo-VS.git /scripts/telegram-admin-bot

# 2. Acceder al directorio
cd /scripts/telegram-admin-bot

# 3. Ejecutar el script de instalación
sudo bash installer/install.sh
```

El script se encarga de aprovisionar las dependencias del sistema operativo, configurar el entorno virtual de Python, crear las bases de datos requeridas y desplegar el portal web.

---

## 💻 Herramienta CLI (`estatus`)

La plataforma incorpora la herramienta de línea de comandos `estatus` para operaciones directas desde la terminal:

```bash
# Sincronización de telemetría web
estatus web

# Chequeo de servicios monitoreados
estatus servicios

# Chequeo de sedes y enlaces
estatus sedes

# Diagnóstico completo de infraestructura
estatus completo
```

---

## 🤖 Comandos Principales de Telegram

| Comando | Descripción |
| :--- | :--- |
| `/servicios` | Resumen del estado de los servicios monitoreados. |
| `/sedes` | Estado de conectividad y tiempos de respuesta de sedes. |
| `/monitoreo` | Reporte consolidado de infraestructura. |
| `/recursos` | Métricas de uso de CPU, memoria RAM y almacenamiento. |
| `/temperatura`| Lectura térmica de hardware y estado de protección. |
| `/botstatus` | Diagnóstico de conectividad de red y proxies. |
| `/info` | Información general de la plataforma y términos. |

---

## ⚖️ Licencia

Este proyecto se distribuye bajo los términos de la licencia **GNU Affero General Public License v3.0 (AGPLv3)**. Consulte el archivo `LICENSE` para más información.
