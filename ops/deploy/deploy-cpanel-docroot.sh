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

resolve_source() {
	local preferred="$1"
	local fallback="$2"

	if [[ -f "$preferred" ]]; then
		echo "$preferred"
		return 0
	fi

	if [[ -f "$fallback" ]]; then
		echo "$fallback"
		return 0
	fi

	echo ""
}

backup_untracked_conflicts() {
	local backup_root="$REPO_PATH/.deploy-backup/pre-pull-$(date +%Y%m%d-%H%M%S)"
	local moved=0
	local path status dest_dir

	for path in ".htaccess" "api/index.php" "runtime-config.php"; do
		status="$(git -C "$REPO_PATH" status --porcelain --untracked-files=all -- "$path" | head -n 1 || true)"
		if [[ "$status" == \?\?* ]]; then
			dest_dir="$backup_root/$(dirname "$path")"
			mkdir -p "$dest_dir"
			mv -f "$REPO_PATH/$path" "$dest_dir/"
			echo "BACKUP: moved untracked $path to $dest_dir/"
			moved=1
		fi
	done

	if [[ $moved -eq 1 ]]; then
		echo "Backup complete: $backup_root"
	fi
}

echo "[1/4] Updating repository from origin/$BRANCH"
git -C "$REPO_PATH" fetch origin "$BRANCH"
backup_untracked_conflicts
git -C "$REPO_PATH" pull --ff-only origin "$BRANCH"

echo "[2/4] Syncing frontend files"
copy_if_needed "$REPO_PATH/index.html" "$DOCROOT_PATH/index.html"
copy_if_needed "$REPO_PATH/app-main.js" "$DOCROOT_PATH/app-main.js"
copy_if_needed "$REPO_PATH/runtime-config.js" "$DOCROOT_PATH/runtime-config.js"

echo "[3/4] Syncing cPanel PHP gateway files"
HTACCESS_SRC="$(resolve_source "$REPO_PATH/.htaccess" "$REPO_PATH/cpanel-public/.htaccess")"
RUNTIME_CFG_SRC="$(resolve_source "$REPO_PATH/runtime-config.php" "$REPO_PATH/cpanel-public/runtime-config.php")"
API_INDEX_SRC="$(resolve_source "$REPO_PATH/api/index.php" "$REPO_PATH/cpanel-public/api/index.php")"

if [[ -z "$HTACCESS_SRC" || -z "$RUNTIME_CFG_SRC" || -z "$API_INDEX_SRC" ]]; then
	echo "ERROR: gateway source files are missing in both root and cpanel-public paths"
	exit 1
fi

copy_if_needed "$HTACCESS_SRC" "$DOCROOT_PATH/.htaccess"
copy_if_needed "$RUNTIME_CFG_SRC" "$DOCROOT_PATH/runtime-config.php"
copy_if_needed "$API_INDEX_SRC" "$DOCROOT_PATH/api/index.php"

echo "[4/4] Deploy finished"
echo "Docroot updated: $DOCROOT_PATH"
echo "Next checks:"
echo "  - https://matiq.cepat.digital/api/index.php/health"
echo "  - https://matiq.cepat.digital/"
