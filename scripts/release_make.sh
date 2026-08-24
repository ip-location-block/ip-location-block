#!/bin/bash
# The former script committed arbitrary working-tree changes and deployed SVN
# trunk directly. Releases now go through the reviewed GitHub v-tag workflow.

set -euo pipefail

cat >&2 <<'EOF'
scripts/release_make.sh is retired.

Build locally with scripts/release_prepare.sh, then publish an annotated v* tag.
The GitHub release workflow validates, builds, and selects the safe WordPress.org
deployment mode. Release candidates create an SVN tag without changing trunk.
EOF

exit 1
