import { execFileSync } from 'node:child_process';
const diff = execFileSync('git', ['diff','--cached','--no-ext-diff','--unified=0'], { encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
const added = diff.split('\n').filter(line => line.startsWith('+') && !line.startsWith('+++')).join('\n');
const signatures = {
  private_key: /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,
  aws_access_key: /\bAKIA[0-9A-Z]{16}\b/,
  google_api_key: /\bAIza[0-9A-Za-z_-]{35}\b/,
  github_token: /\b(?:ghp_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{40,})\b/,
  credential_url: /(?:postgres(?:ql)?|mysql|mongodb):\/\/[^\s:]+:[^\s@]+@/
};
const findings = Object.entries(signatures).filter(([,pattern]) => pattern.test(added)).map(([name]) => name);
console.log(JSON.stringify({ checked: 'staged added lines', credential_signature_findings: findings }));
process.exitCode = findings.length ? 1 : 0;
