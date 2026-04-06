#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./ops/deploy/deploy-cpanel-docroot.sh /path/to/repo /path/to/docroot
# Example:
#   ./ops/deploy/deploy-cpanel-docroot.sh /home/egxvvhji/matiq-repo /home/egxvvhji/matiq.cepat.digital

if [[ $# -lt 2 ]]; then
	echo "Usage: $0 <repo_path> <docroot_path>"
	exit 1
fi

REPO_PATH="$1"
DOCROOT_PATH="$2"
BRANCH="${BRANCH:-main}"

if [[ ! -d "$REPO_PATH/.git" ]]; then
	echo "ERROR: repo path is not a git repository: $REPO_PATH"
	exit 1
fi

mkdir -p "$DOCROOT_PATH/api"

echo "[1/4] Updating repository from origin/$BRANCH"
git -C "$REPO_PATH" fetch origin "$BRANCH"
git -C "$REPO_PATH" pull --ff-only origin "$BRANCH"

echo "[2/4] Syncing frontend files"
cp -f "$REPO_PATH/index.html" "$DOCROOT_PATH/index.html"
cp -f "$REPO_PATH/app-main.js" "$DOCROOT_PATH/app-main.js"
cp -f "$REPO_PATH/runtime-config.js" "$DOCROOT_PATH/runtime-config.js"

echo "[3/4] Syncing cPanel PHP gateway files"
cp -f "$REPO_PATH/cpanel-public/.htaccess" "$DOCROOT_PATH/.htaccess"
cp -f "$REPO_PATH/cpanel-public/runtime-config.php" "$DOCROOT_PATH/runtime-config.php"
cp -f "$REPO_PATH/cpanel-public/api/index.php" "$DOCROOT_PATH/api/index.php"

echo "[4/4] Deploy finished"
echo "Docroot updated: $DOCROOT_PATH"
echo "Next checks:"
echo "  - https://matiq.cepat.digital/api/index.php/health"
echo "  - https://matiq.cepat.digital/"
