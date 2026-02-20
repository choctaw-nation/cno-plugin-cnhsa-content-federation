#!/usr/bin/env bash
set -euo pipefail

LOCAL_RUN_DIR="${LOCAL_RUN_DIR:-$HOME/Library/Application Support/Local/run}"

if [[ ! -d "$LOCAL_RUN_DIR" ]]; then
  echo "Local run directory not found: $LOCAL_RUN_DIR" >&2
  exit 1
fi

# Collect candidate sockets (avoid mapfile; compatible with older bash)
sockets="$(find "$LOCAL_RUN_DIR" -path "*/mysql/mysqld.sock" 2>/dev/null || true)"

if [[ -z "${sockets//[[:space:]]/}" ]]; then
  echo "No Local MySQL socket found under: $LOCAL_RUN_DIR" >&2
  echo "Is Local running and is your site started?" >&2
  exit 1
fi

# Count results
count="$(printf "%s\n" "$sockets" | sed '/^$/d' | wc -l | tr -d ' ')"

# If only one, print it
if [[ "$count" -eq 1 ]]; then
  printf "%s\n" "$sockets" | sed -n '1p'
  exit 0
fi

# Many sockets: prompt user
echo "Multiple Local MySQL sockets found:"
i=0
while IFS= read -r sock; do
  [[ -z "$sock" ]] && continue
  site_id="$(printf "%s" "$sock" | sed -E "s|^$LOCAL_RUN_DIR/([^/]+)/.*|\1|")"
  printf "  [%d] %s  (%s)\n" "$i" "$sock" "$site_id"
  i=$((i+1))
done <<< "$sockets"

echo
read -r -p "Choose a socket [0-$((count-1))]: " idx

# Validate idx
case "$idx" in
  ''|*[!0-9]*)
    echo "Invalid selection." >&2
    exit 1
    ;;
esac
if [[ "$idx" -lt 0 || "$idx" -ge "$count" ]]; then
  echo "Invalid selection." >&2
  exit 1
fi

# Print the chosen socket (idx is 0-based; sed is 1-based)
line=$((idx+1))
printf "%s\n" "$sockets" | sed -n "${line}p"
