#!/bin/bash

HAM_PLUGIN="wp-content/plugins/ham-plugin/ham-plugin.php"

# --- GET CURRENT VERSION ---
CUR_VERSION=$(grep -m1 -Eo 'Version: *[0-9]+\.[0-9]+\.[0-9]+' "$HAM_PLUGIN" | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+')

if [ -z "$CUR_VERSION" ]; then
  echo "Could not determine current version!"
  exit 1
fi

# --- BUMP PATCH ---
IFS='.' read -r MAJOR MINOR PATCH <<< "$CUR_VERSION"
PATCH=$((PATCH + 1))
NEW_VERSION="$MAJOR.$MINOR.$PATCH"

echo "Bumping version: $CUR_VERSION → $NEW_VERSION"

# --- UPDATE ham-plugin.php ---
sed -i '' -E "s/Version: *[0-9]+\.[0-9]+\.[0-9]+/Version: $NEW_VERSION/" "$HAM_PLUGIN"
git add "$HAM_PLUGIN"
echo "HAM plugin version bumped to $NEW_VERSION and staged for commit."