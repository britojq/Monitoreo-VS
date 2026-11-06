#!/bin/bash
##############################################################################################################
#
#     License: GNU Affero General Public License v3.0  https://cutt.ly/SxMDfO2
#      Author: Operador ATIT (@britojab:@britojq), https://britojab.com
#		Copyright (c) 2021 Operador ATIT
#
############################################

# Escanea TODO el sistema en busca de archivos grandes INUSUALES, detección proactiva de anomalías

export LC_ALL=C

echo "🔍 Iniciando diagnóstico avanzado (escaneo completo del sistema)"
df -h /

echo -e "\n--- [1/8] Directorios grandes en la raíz (/) ---"
sudo du -shx /* 2>/dev/null | sort -hr | head -n 15

# Directorios estándar comunes en /
STANDARD_DIRS="bin boot dev etc home lib lib32 lib64 libx32 media mnt opt proc root run sbin srv sys tmp usr var"

echo -e "\n--- [2/8] Directorios NO estándar en / que usan mucho espacio ---"
NON_STANDARD_LARGE=()
while IFS= read -r line; do
    size=$(echo "$line" | awk '{print $1}')
    dir=$(echo "$line" | awk '{print $2}' | sed 's|/||')
    is_standard=false
    for std in $STANDARD_DIRS; do
        if [[ "$dir" == "$std" ]]; then
            is_standard=true
            break
        fi
    done
    if [[ "$is_standard" == false ]] && [[ -n "$dir" ]]; then
        echo "⚠️  NO estándar: /$dir ($size)"
        NON_STANDARD_LARGE+=("/$dir")
    fi
done < <(sudo du -shx /* 2>/dev/null | sort -hr)

if [[ ${#NON_STANDARD_LARGE[@]} -eq 0 ]]; then
    echo "✅ No se encontraron directorios no estándar grandes en /"
fi

# ============================================================================
# Búsqueda de archivos "sospechosos" en TODO el sistema
# ============================================================================
echo -e "\n--- [3/8] Buscando archivos grandes (>100 MiB) con ubicación o nombre SOSPECHOSO ---"

# Palabras clave que indican archivos temporales, duplicados o basura
SUS_WORDS=("core\." "dump" "backup" "temp" "old" "copy" "bak" "log" "crash" "tmp" "swap" "swapfile" "hiberfil" "pagefile")

# Buscar archivos grandes en TODO el sistema (sin excluir /usr, /var, etc.)
# Pero filtrar los que estén en zonas sensibles o tengan nombres sospechosos
{
    # 1. Archivos grandes en zonas NO destinadas a datos grandes
    sudo find / -xdev -type f -size +100M \( \
        -path "/etc/*" -o \
        -path "/bin/*" -o \
        -path "/sbin/*" -o \
        -path "/lib/*" -o \
        -path "/lib32/*" -o \
        -path "/lib64/*" -o \
        -path "/boot/*" -o \
        -path "/root/*" -o \
        -path "/tmp/*" -o \
        -path "/var/tmp/*" \
    \) -exec du -h {} + 2>/dev/null

    # 2. Archivos grandes con nombres sospechosos (en cualquier lugar)
    for word in "${SUS_WORDS[@]}"; do
        sudo find / -xdev -type f -size +100M -iname "*$word*" -exec du -h {} + 2>/dev/null
    done

    # 3. Archivos grandes en directorios NO estándar de raíz (ej: /datos, /mibackup)
    for d in "${NON_STANDARD_LARGE[@]}"; do
        sudo find "$d" -xdev -type f -size +100M -exec du -h {} + 2>/dev/null
    done
} | sort -u -hr | head -n 20 | while read size file; do
    echo "🔍 SOSPECHOSO: $size → $file"
done

if ! { { sudo find / -xdev -type f -size +100M \( \
        -path "/etc/*" -o -path "/bin/*" -o -path "/sbin/*" -o -path "/lib/*" -o -path "/boot/*" -o -path "/root/*" -o -path "/tmp/*" -o -path "/var/tmp/*" \
    \) -exec echo 1 \; 2>/dev/null; }; } | head -n1 | grep -q .; then
    if ! { for w in "${SUS_WORDS[@]}"; do sudo find / -xdev -type f -size +100M -iname "*$w*" -exec echo 1 \; 2>/dev/null; done; } | head -n1 | grep -q .; then
        if [[ ${#NON_STANDARD_LARGE[@]} -eq 0 ]]; then
            echo "✅ No se encontraron archivos grandes sospechosos en ubicaciones inusuales."
        fi
    fi
fi


echo -e "\n--- [4/8] Cachés de usuario ---"
for user_home in /home/*; do
    if [[ -d "$user_home" && -d "$user_home/.cache" ]]; then
        cache_size=$(du -sh "$user_home/.cache" 2>/dev/null | cut -f1)
        echo "Caché de usuario $(basename "$user_home"): $cache_size"
    fi
done
if [[ -d "/root/.cache" ]]; then
    root_cache=$(du -sh /root/.cache 2>/dev/null | cut -f1)
    echo "Caché de root: $root_cache"
fi

echo -e "\n--- [5/8] Limpieza segura sugerida (interactiva) ---"
APT_CACHE_SIZE=$(sudo du -sh /var/cache/apt/archives/ 2>/dev/null | cut -f1 || echo "0B")
read -p "¿Limpiar caché de APT (~$APT_CACHE_SIZE)? [s/N]: " -n 1 -r
echo
[[ $REPLY =~ ^[Ss]$ ]] && { sudo apt clean; echo "✅ Caché de APT limpiada."; }

read -p "¿Ejecutar 'apt autoremove --purge'? [s/N]: " -n 1 -r
echo
[[ $REPLY =~ ^[Ss]$ ]] && { sudo apt autoremove --purge -y; echo "✅ Paquetes innecesarios eliminados."; }

read -p "¿Reducir logs del sistema a 50 MiB? [s/N]: " -n 1 -r
echo
[[ $REPLY =~ ^[Ss]$ ]] && { sudo journalctl --vacuum-size=50M; echo "✅ Logs limitados."; }

read -p "¿Eliminar logs rotados (.gz, .1, .2, etc.) en /var/log? [s/N]: " -n 1 -r
echo
[[ $REPLY =~ ^[Ss]$ ]] && {
    sudo find /var/log \( -name "*.gz" -o -name "*.[0-9]" -o -name "*.old" \) -delete 2>/dev/null
    echo "✅ Logs rotados eliminados."
}

echo -e "\n--- [6/8] Verificación de inodos ---"
INODE_USE=$(df -i / | awk 'NR==2 {print $5}')
echo "Uso de inodos en /: $INODE_USE"
if [[ "$INODE_USE" == "100%" ]]; then
    echo "⚠️  ¡Los inodos están agotados! Podrías tener millones de archivos pequeños."
    echo "Ejemplo: sudo find / -xdev -type f -size -4k | wc -l"
fi

echo -e "\n--- [7/8] Archivos grandes conocidos (TOP 10 en todo el sistema) ---"
echo "Estos pueden ser legítimos (videos, ISOs, VMs...), ¡revisa con cuidado!"
sudo find / -xdev -type f -size +200M -exec du -h {} + 2>/dev/null | sort -hr | head -n 10

echo -e "\n--- [8/8] Resumen final ---"
df -h /

echo -e "\n💡 Recomendaciones:"
if [[ ${#NON_STANDARD_LARGE[@]} -gt 0 ]]; then
    echo "- Revisa los directorios no estándar listados."
fi
echo "- Si ves archivos como 'core', 'dump', o 'backup' grandes, podrían ser basura."
echo "- Si deseas una exploracion mas profunda, Usa 'ncdu' para explorar visualmente:/"
echo "- Ejecuta desde un terminal: sudo apt install ncdu && sudo ncdu /"
echo "- ¡Nunca elimines archivos del sistema sin saber su propósito!"
echo -e "\n✨ Diagnóstico completo terminado."
