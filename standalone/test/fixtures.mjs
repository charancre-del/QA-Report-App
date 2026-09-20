// Deliberately synthetic. No production records or identities.
import { packSnapshot } from '../src/snapshot.mjs';
export const source = 'synthetic-wordpress';
export function payload() {
  const data = {
    schools: [{ id: '11', name: 'Synthetic North', region: 'test-north' }, { id: '12', name: 'Synthetic South', region: 'test-south' }],
    reports: [
      { id: '9007199254740993', school_id: '11', user_id: '101', previous_report_id: null, inspection_date: '2026-01-02', report_type: 'tier1', status: 'approved', overall_rating: 'meets', closing_notes: '<script>unsafe()</script>' },
      { id: '22', school_id: '12', user_id: '102', previous_report_id: null, inspection_date: '2026-01-03', report_type: 'tier1', status: 'draft', overall_rating: 'pending', closing_notes: 'Synthetic private south note' }
    ],
    responses: [{ id: '31', report_id: '9007199254740993', section_key: 'test', item_key: 'synthetic_item', rating: 'yes', notes: 'Synthetic observed note' }],
    photos: [{ id: '41', report_id: '9007199254740993', drive_file_id: 'wp_51', caption: 'Synthetic photo', section_key: 'test' }],
    ai_summaries: [{ id: '61', report_id: '9007199254740993', executive_summary: 'Synthetic historical summary' }],
    templates: [{ id: '71', name: 'Synthetic template', created_by: '101', responses_json: '{}' }],
    workflow_comments: [{ id: '81', report_id: '9007199254740993', user_id: '101', action: 'approve', comment: 'Synthetic comment', created_at: '2026-01-03 00:00:00' }]
  };
  return { source: { instance: source, url: 'https://synthetic.invalid' }, exported_at: '2026-01-04T00:00:00Z',
    consistency: 'repeatable-read', tables: Object.fromEntries(Object.entries(data).map(([name, rows]) =>
      [name, { columns: Object.keys(rows[0]), schema: `CREATE TABLE cqa_${name} (id BIGINT)`, count: rows.length, rows }])),
    principals: [{ id: '101', exists: true }, { id: '102', exists: true }],
    media: [{ photo_id: '41', source_ref: 'wp_51', kind: 'wordpress', bytes_copied: false, access_verified: false }] };
}
export const snapshot = () => packSnapshot(payload());
export const grants = () => [{ subject: 'test-issuer|opaque-101', source_instance: source, source_user_id: '101',
  action: 'archive:read', school_ids: ['11'], all_schools: false, disabled: false,
  reviewed_by: 'synthetic-test-reviewer', expires_at: '2099-01-01T00:00:00Z' }];
