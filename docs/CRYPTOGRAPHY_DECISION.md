# Decisión Criptográfica: AES-256-CBC vs AES-256-GCM

## Contexto
El sistema utiliza AES-256-CBC para compatibilidad con Laravel Encrypter (`Illuminate\Encryption\Encrypter`).

## Decisión
Mantener AES-256-CBC para interoperabilidad nativa con Laravel, con soporte polimórfico coexistente para AES-256-GCM conforme a NIST SP 800-38D.

## Justificación
- **Interoperabilidad nativa:** Laravel utiliza por defecto `AES-256-CBC` con esquema Encrypt-then-MAC (HMAC-SHA256).
- **Estabilidad de producción:** Migrar exclusivamente a GCM requeriría reescribir todo el subsistema de credenciales en MariaDB y rompería la compatibilidad con el framework PHP.
- **Seguridad comprobada:** AES-256-CBC es criptográficamente seguro cuando se acompaña de un IV criptográfico único de 16 bytes y autenticación HMAC-SHA256 comparada en tiempo constante (`hmac.compare_digest`), mitigando vulnerabilidades de oráculo de padding (Padding Oracle Attacks / OWASP A02:2021).
- **Modo Estricto:** La implementación en Python (`monitor/infrastructure/laravel_encryptor.py`) cuenta con modo `strict: bool = False` que levanta `DecryptionException` tipada ante fallas de descifrado o manipulación del MAC.

## Futuro
Considerar la migración completa a AES-256-GCM como estándar único en la versión mayor `v6.0.0` si el ecosistema Laravel incorpora soporte nativo para dicho esquema o si se requiere autenticación integrada de hardware.

---
*Referencia Técnica Exhaustiva:* [`docs/ADR_CRIPTO_LARAVEL_CBC_GCM.md`](file:///opt/sonar/docs/ADR_CRIPTO_LARAVEL_CBC_GCM.md)
