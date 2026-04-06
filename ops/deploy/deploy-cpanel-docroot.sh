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

copy_if_needed() {
	local src="$1"
	local dst="$2"

	if [[ ! -f "$src" ]]; then
		echo "ERROR: source file not found: $src"
		exit 1
	fi

	# If source and destination point to the same inode/path, skip safely.
	if [[ -e "$dst" ]]; then
		local src_real dst_real
		src_real="$(readlink -f "$src")"
		dst_real="$(readlink -f "$dst")"
		if [[ "$src_real" == "$dst_real" ]]; then
			echo "SKIP: $dst (same file)"
			return 0
		fi
	fi

	cp -f "$src" "$dst"
}

if [[ ! -d "$REPO_PATH/.git" ]]; then
	echo "ERROR: repo path is not a git repository: $REPO_PATH"
	exit 1
fi

mkdir -p "$DOCROOT_PATH/api"

echo "[1/4] Updating repository from origin/$BRANCH"
git -C "$REPO_PATH" fetch origin "$BRANCH"
git -C "$REPO_PATH" pull --ff-only origin "$BRANCH"

echo "[2/4] Syncing frontend files"
copy_if_needed "$REPO_PATH/index.html" "$DOCROOT_PATH/index.html"
copy_if_needed "$REPO_PATH/app-main.js" "$DOCROOT_PATH/app-main.js"
copy_if_needed "$REPO_PATH/runtime-config.js" "$DOCROOT_PATH/runtime-config.js"

echo "[3/4] Syncing cPanel PHP gateway files"
copy_if_needed "$REPO_PATH/cpanel-public/.htaccess" "$DOCROOT_PATH/.htaccess"
copy_if_needed "$REPO_PATH/cpanel-public/runtime-config.php" "$DOCROOT_PATH/runtime-config.php"
copy_if_needed "$REPO_PATH/cpanel-public/api/index.php" "$DOCROOT_PATH/api/index.php"

echo "[4/4] Deploy finished"
echo "Docroot updated: $DOCROOT_PATH"
echo "Next checks:"
echo "  - https://matiq.cepat.digital/api/index.php/health"
echo "  - https://matiq.cepat.digital/"
