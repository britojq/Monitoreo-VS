# 📜 REGISTRO DE DECISIÓN ARQUITECTÓNICA (ADR)
## ADR-001: Coexistencia Criptográfica Dual AES-256-CBC y AES-256-GCM (NIST SP 800-38D)

* **Estado:** Aprobado / Implementado
* **Fecha:** 2 de Noviembre, 2026
* **Versión del Sistema:** v5.46.90
* **Componentes Afectados:**
  - [`monitor/infrastructure/laravel_encryptor.py`](file:///opt/sonar/monitor/infrastructure/laravel_encryptor.py)
  - [`web_portal/app/Models/User.php`](file:///opt/sonar/web_portal/app/Models/User.php)
  - [`tests/unit/test_laravel_encryptor_gcm.py`](file:///opt/sonar/tests/unit/test_laravel_encryptor_gcm.py)
* **Auditoría de Referencia:** [`docs/auditoria_exhaustiva_v5.46.80.md`](file:///opt/sonar/docs/auditoria_exhaustiva_v5.46.80.md) (Hallazgo SEC-M1)

---

### 1. 📋 Contexto y Planteamiento del Problema
En la auditoría exhaustiva v5.46.80 se identificó una inconsistencia entre la documentación histórica del proyecto (que mencionaba cumplimiento de NIST SP 800-38D mediante `AES-256-GCM`) y la implementación concreta en Python/Laravel (que utilizaba `AES-256-CBC` con HMAC-SHA256).

Esta disparidad se originó por el diseño predeterminado del framework web:
1. **Laravel Encrypter (`Illuminate\Encryption\Encrypter`):** Emplea de forma estándar `AES-256-CBC` con firma MAC HMAC-SHA256 (`encrypt-then-mac`) para el cifrado de sesiones, cookies y campos de base de datos.
2. **Estándares Modernos AppSec (NIST SP 800-38D / OWASP A02:2021):** Recomiendan el cifrado autenticado con datos asociados (AEAD) mediante `AES-256-GCM` debido a su rendimiento superior y su resistencia matemática intrínseca contra ataques de oráculo de padding (Padding Oracle Attacks) sin depender de un flujo HMAC secundario.

---

### 2. 🎯 Decisión Arquitectónica
Se adopta formalmente una **arquitectura de interoperabilidad criptográfica dual y transparente**:

1. **Retrocompatibilidad Nativa con Laravel (AES-256-CBC):**
   - El cifrado predeterminado para interoperabilidad con PHP (`encrypt_laravel_string`) utiliza `AES-256-CBC` con PKCS7 padding y autenticación HMAC-SHA256 con verificación en tiempo constante (`hmac.compare_digest`), replicando bit a bit el comportamiento de Laravel 10/11/12/13.
2. **Soporte de Alta Seguridad NIST SP 800-38D (AES-256-GCM):**
   - Se incorpora `encrypt_laravel_string_gcm` utilizando `cryptography.hazmat.primitives.ciphers.aead.AESGCM` con nonces criptográficamente seguros de 12 bytes (96 bits) y tags de autenticación de 16 bytes (128 bits).
3. **Descifrado Polimórfico Auto-Detectado:**
   - La función unificada `decrypt_laravel_string(encrypted_str, strict=False)` inspecciona automáticamente la estructura del payload JSON desempaquetado:
     - Si contiene la clave `"tag"`: se decodifica de forma nativa mediante `AESGCM`.
     - Si contiene las claves `"iv"` y `"mac"`: se valida el HMAC y se descifra mediante `Cipher(algorithms.AES, modes.CBC)`.
4. **Modo Estricto contra Silenciamiento de Errores (OWASP A02 / ERR-001):**
   - Se introduce el parámetro `strict: bool = False`. En modo normal retorna `""` con logging de diagnóstico para evitar caídas de demonios; en modo estricto (`strict=True`) levanta `DecryptionException` tipada para auditorías forenses y pruebas automatizadas.

---

### 3. ⚖️ Consecuencias y Beneficios
* ✅ **Cero Regresiones:** Todas las credenciales previamente cifradas por Laravel en MariaDB se descifran con éxito y sin requerir migraciones de datos disruptivas.
* ✅ **Cumplimiento NIST SP 800-38D:** Las nuevas cargas de trabajo pueden optar por cifrado autenticado AEAD sin fricción.
* ✅ **Consistencia Documental Total:** Queda erradicada la ambigüedad documental señalada por el auditor externo (SEC-M1).
* ✅ **Resiliencia de Caché en RAM:** La clave maestra `APP_KEY` se desenrolla en memoria volátil compartida (`/dev/shm`) con TTL de 300s, erradicando lecturas de archivos planos en disco (Reglas de Oro #6 y #13).
