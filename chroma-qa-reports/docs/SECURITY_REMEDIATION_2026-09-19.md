# QA authorization and credential remediation

Date: 2026-09-19
Baseline commit: `f35a9ab448e349a865727aa14fe162edb52f6db0`

## Security invariant

- Settings and stored credential state are available only to `manage_options` or `cqa_manage_settings` users.
- Stored OAuth, Gemini, Google Picker, and Maps credential values are never prefilled into settings HTML or returned by the settings REST endpoint.
- A blank sensitive setting preserves the current value; it does not erase or disclose it.
- Report access is enforced by object ownership plus explicit global, region, or school scope.
- School access is enforced by `cqa_region` or `cqa_school_id`; missing scope metadata fails closed.
- Report, response, photo, PDF, AI, and rendered-page operations reuse the centralized access policy.
- New Google SSO users receive `cqa_program_manager` and no data scope until an administrator explicitly assigns a school or approves a broader QA role.
- Only users with `cqa_edit_all_reports` can create or transition a report to `approved`.

## Required deployment checks

Do not deploy this change until an authorized operator has completed and recorded all of the following:

1. Inventory current QA users, roles, `cqa_region`, and `cqa_school_id` assignments without exporting report contents or personal data.
2. Remove unjustified `cqa_super_admin`, `cqa_qa_officer`, `cqa_manage_settings`, `cqa_view_all_reports`, and `cqa_edit_all_reports` grants.
3. Assign every regional director an exact `cqa_region` matching stored school region values.
4. Assign every program manager or create-only user an explicit `cqa_school_id`, or leave the account intentionally fail-closed.
5. Review the stored `cqa_sso_default_role`; runtime provisioning now ignores unsafe broad defaults, but the legacy option should be corrected for operational clarity.
6. Rotate the Google OAuth client secret and Gemini API key because prior application versions could expose their stored values to report creators.
7. Review Google Picker and Maps browser keys. Restrict them by HTTPS referrer and allowed Google APIs; rotate them if prior unrestricted exposure cannot be ruled out.
8. Exercise negative tests in a staging WordPress environment for a global QA officer, regional director, program manager, report owner, unrelated report owner, and unscoped new SSO user.
9. Verify denied requests return `403` and do not create, update, delete, export, upload, invoke AI, or emit a success notification.
10. Record the deployed plugin artifact digest, WordPress environment, rollback artifact, approver, and timestamp.

Credential rotation, current-user review, staging WordPress execution, and production deployment were not performed by this source-only remediation.

## Verification commands

```text
composer install
composer test
composer audit
npm ci
npm test -- --runInBand --detectOpenHandles
npm audit --audit-level=high
```

The PHP test environment requires the standard DOM, SimpleXML, XML, XMLWriter, and Mbstring extensions.
