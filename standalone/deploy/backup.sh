#!/bin/sh
set -eu
umask 077
# Run as chroma-qa. Paths and the public age recipient are operator-controlled.
: "${CQA_DATABASE_PATH:?}" "${CQA_SOURCE_INSTANCE:?}" "${CQA_MEDIA_DIRECTORY:?}"
: "${CQA_SNAPSHOT_PATH:?}" "${CQA_BACKUP_DIRECTORY:?}" "${CQA_BACKUP_RECIPIENT_FILE:?}"
cqa_stamp=$(date -u +%Y%m%dT%H%M%SZ)
cqa_work=$(mktemp -d /var/lib/chroma-qa/backup-work-XXXXXX)
cqa_output="$CQA_BACKUP_DIRECTORY/qa-$cqa_stamp.tar.age"
test ! -e "$cqa_output"
node tools/recovery.mjs backup "$CQA_DATABASE_PATH" "$CQA_SOURCE_INSTANCE" "$CQA_MEDIA_DIRECTORY" "$CQA_SNAPSHOT_PATH" "$cqa_work/bundle"
tar -C "$cqa_work" -cf "$cqa_work/bundle.tar" bundle
age -R "$CQA_BACKUP_RECIPIENT_FILE" -o "$cqa_output" "$cqa_work/bundle.tar"
sha256sum "$cqa_output"
# Plaintext cleanup is deliberately separate: retain until encrypted restore validation.
# A controlled retention job must remove only verified backup-work-* directories.
