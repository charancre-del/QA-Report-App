import { readFileSync } from 'node:fs';
// Evidence gate only; never deploys, edits WordPress, switches DNS, or deletes sources.
const required = ['source_identity','source_backup_restore','migration_totals','private_media_migration','offline_draft_drain',
  'current_role_review','oidc_subject_and_denial','reviewed_checklist_parity','legacy_ui_and_pdf_parity','monday_disposition',
  'notification_delivery_or_approved_exclusion','runtime_and_capacity','encrypted_backup_restore','operator_uat','release_approval','rollback_rehearsal'];
try {
  const evidence = JSON.parse(readFileSync(process.argv[2], 'utf8'));
  const blocked = required.filter(key => evidence.gates?.[key]?.status !== 'verified' || !evidence.gates[key].reviewer || !evidence.gates[key].evidence_ref);
  if (evidence.source?.url !== 'https://x3yyntt5tp-staging.wpdns.site' || evidence.source?.database !== 'x3yyadl_staging' || evidence.source?.table_prefix !== 'wp_') blocked.push('staging_target_identity');
  console.log(JSON.stringify({ cutover_ready: blocked.length === 0, blocked }, null, 2));
  process.exitCode = blocked.length ? 1 : 0;
} catch { console.error('A reviewed release evidence file is required'); process.exitCode = 1; }
