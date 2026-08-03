#!/usr/bin/env bash

set -Eeuo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
compose_file="${repository_root}/compose.e2e.yaml"
project_name="${CARDNEST_E2E_PROJECT_NAME:-cardnest-real-e2e}"
frontend_port="${CARDNEST_E2E_FRONTEND_PORT:-15173}"
compose_command=(docker compose --project-name "${project_name}" --file "${compose_file}")

if [[ ! -f "${repository_root}/backend/vendor/autoload.php" ]]; then
  echo "Missing backend dependencies. Run Composer install before the real E2E suite." >&2
  exit 1
fi

if [[ ! -x "${repository_root}/frontend/node_modules/.bin/playwright" ]]; then
  echo "Missing frontend dependencies. Run npm ci in frontend before the real E2E suite." >&2
  exit 1
fi

cleanup() {
  "${compose_command[@]}" down --volumes --remove-orphans
}

trap cleanup EXIT

"${compose_command[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
"${compose_command[@]}" up --build --detach --wait --wait-timeout 180

running_services="$("${compose_command[@]}" ps --services --status running)"
if ! printf '%s\n' "${running_services}" | grep --quiet --line-regexp 'worker'; then
  echo "The real E2E Messenger worker is not running." >&2
  "${compose_command[@]}" logs worker
  exit 1
fi

(
  cd "${repository_root}/frontend"
  CARDNEST_E2E_BASE_URL="http://127.0.0.1:${frontend_port}" \
  CARDNEST_E2E_COMPOSE_FILE="${compose_file}" \
  CARDNEST_E2E_PROJECT_NAME="${project_name}" \
  npm run test:e2e:real
)
