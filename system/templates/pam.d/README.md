# Integración PAM para Alertas SSH y Escalamiento de Privilegios

Este módulo define las directivas necesarias para enganchar el centinela de seguridad (`ssh_alert.sh`) en PAM:

1. **/etc/pam.d/sshd**:
   Añadir al final de la sección session:
   `session optional pam_exec.so seteuid /bin/bash /scripts/telegram-admin-bot/monitor/ssh_alert.sh`

2. **/etc/pam.d/sudo** y **/etc/pam.d/su**:
   Añadir como requisito de sesión:
   `session requisite pam_exec.so seteuid stdout /bin/bash /scripts/telegram-admin-bot/monitor/ssh_alert.sh`

El script `tools/system_sync.py` valida e inserta automáticamente estas directivas si no existen en los archivos `/etc/pam.d/`.
