#!/usr/bin/env node
/**
 * PostToolUse hook — regenerate `.cursor/` whenever `.claude/` changes.
 * ------------------------------------------------------------------
 * Claude Code pipes the tool payload as JSON on stdin. We only run the sync
 * when the edited file is part of the canonical AI config, so ordinary source
 * edits stay instant.
 *
 * Wired in `.claude/settings.json` under hooks.PostToolUse (matcher: Edit|Write).
 */

import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join, dirname, resolve, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/** Paths that make `.cursor/` stale. Anything else is ignored. */
const WATCHED_ROOTS = ['.claude/rules', '.claude/skills', '.claude/commands', '.claude/agents', '.claude/BACKEND-PHP', '.claude/FRONTEND', '.claude/OWASP'];
const WATCHED_FILES = ['.claude/settings.json', '.mcp.json', 'CLAUDE.md', 'composer.json', 'package.json', 'components.json'];

function readStdin() {
  try {
    return readFileSync(0, 'utf8');
  } catch {
    return '';
  }
}

function toRelative(filePath) {
  const rel = relative(ROOT, resolve(ROOT, filePath)).split(sep).join('/');
  return rel.startsWith('..') ? null : rel;
}

function isWatched(rel) {
  if (!rel) return false;
  if (WATCHED_FILES.includes(rel)) return true;
  return WATCHED_ROOTS.some((root) => rel === root || rel.startsWith(`${root}/`));
}

function main() {
  let payload = {};
  try {
    payload = JSON.parse(readStdin() || '{}');
  } catch {
    process.exit(0);
  }

  const filePath = payload?.tool_input?.file_path ?? payload?.tool_input?.filePath;
  const rel = filePath ? toRelative(filePath) : null;

  if (!isWatched(rel)) {
    process.exit(0);
  }

  const result = spawnSync(process.execPath, [join(ROOT, 'scripts', 'ai-config-sync.mjs'), '--quiet'], {
    cwd: ROOT,
    encoding: 'utf8',
  });

  // Drift (exit 1) must not fail the tool call — surface it as a note instead.
  if (result.status === 1) {
    console.log(`[syn] .cursor/ regenerated from ${rel} — doc drift reported, run \`npm run syn\` for details.`);
  } else if (result.status === 0) {
    console.log(`[syn] .cursor/ regenerated from ${rel}.`);
  } else if (result.error) {
    console.log(`[syn] sync failed: ${result.error.message}`);
  }

  process.exit(0);
}

main();
