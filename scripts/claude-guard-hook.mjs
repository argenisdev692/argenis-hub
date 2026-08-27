#!/usr/bin/env node
/**
 * PreToolUse hook — stop filesystem wandering into dependency / build trees.
 * -------------------------------------------------------------------------
 * `permissions.deny` on `Read(...)` already covers the Read/Glob tools and the
 * file commands Claude Code recognises inside Bash (`cat`, `head`, `tail`,
 * `sed`). It does NOT cover `grep`, `find`, `ls`, `rg` or `wc`, which is how a
 * session ends up walking `vendor/` and `node_modules/` for twenty minutes.
 *
 * This hook closes that gap: a read-ish Bash command whose arguments point into
 * a forbidden tree is blocked (exit 2) and the reason is written to stderr, so
 * Claude reads the redirect and uses `composer show` / `package.json` / the
 * codebase-memory graph instead.
 *
 * Escape hatch: append `#allow-vendor` to the command to run it anyway.
 *
 * Wired in `.claude/settings.json` under hooks.PreToolUse (matcher: Bash).
 */

/** Commands that read or traverse the filesystem. Executing a binary is fine. */
const READ_COMMANDS =
    /(^|[\s;|&(])(cat|head|tail|sed|less|more|grep|egrep|fgrep|rg|ag|find|ls|dir|tree|wc|awk|strings|xxd|od)(\s|$)/;

/**
 * Directory trees that never answer a question about THIS application, matched
 * as a path argument (`vendor/…`, `./node_modules/…`). Unambiguous: the trailing
 * slash proves it is a path and not a search pattern.
 */
const FORBIDDEN_PATH = /(^|[\s;|&(=])(\.\/)?(vendor|node_modules|backup)\//;

/**
 * The same trees named bare as the ROOT of a traversal (`find node_modules …`,
 * `ls -d vendor`). Restricted to traversal commands so that a search term —
 * `grep -n vendor composer.json` — is not mistaken for a path.
 */
const FORBIDDEN_ROOT =
    /(^|[\s;|&(])(find|ls|dir|tree|du)\s+(-{1,2}\S+\s+)*(\.\/)?(vendor|node_modules|backup)(\s|$)/;

/**
 * A pattern-first searcher whose LAST argument is the bare tree — `rg Foo vendor`.
 * Anchoring at the end keeps the search TERM (`grep -n vendor composer.json`)
 * out of scope, since there the tree name is not the final token.
 */
const FORBIDDEN_SEARCH_TARGET =
    /(^|[\s;|&(])(grep|egrep|fgrep|rg|ag)\s+\S.*\s(\.\/)?(vendor|node_modules|backup)\/?\s*$/;

/** Deliberate, auditable override. */
const OVERRIDE = '#allow-vendor';

/**
 * `echo`/`printf` arguments are prose, not paths — a status line that happens to
 * mention `backup/` must not read as an attempt to open it. Everything up to the
 * next shell separator is dropped before the path rules run.
 *
 * @param {string} command
 * @returns {string}
 */
function stripEchoedText(command) {
    return command.replace(/(^|[\s;|&(])(echo|printf)\s+[^;|&]*/g, '$1');
}

/**
 * @param {string} command
 * @returns {string|null} the reason to block, or null when the command is fine
 */
function findViolation(command) {
    if (command.includes(OVERRIDE)) {
        return null;
    }

    if (!READ_COMMANDS.test(command)) {
        return null;
    }

    const inspected = stripEchoedText(command);
    const pathHit = inspected.match(FORBIDDEN_PATH);
    const rootHit = inspected.match(FORBIDDEN_ROOT);
    const searchHit = inspected.match(FORBIDDEN_SEARCH_TARGET);

    if (pathHit === null && rootHit === null && searchHit === null) {
        return null;
    }

    const tree = pathHit?.[3] ?? rootHit?.[5] ?? searchHit?.[4] ?? 'that directory';

    return [
        `Blocked: reading/searching \`${tree}/\` is not allowed in this project.`,
        '',
        'Use the real source of truth instead:',
        '  - installed PHP package + version -> `composer show --direct` or `composer show <vendor/package>`',
        '  - installed JS package + version  -> `package.json`, or `node -p "require(\'./package.json\').dependencies"`',
        '  - package API / usage             -> Context7 (resolve-library-id, then query-docs), scoped to the installed version',
        '  - application structure           -> codebase-memory-mcp (search_graph / get_code_snippet), then app/, src/, resources/js/, routes/',
        '',
        `If you genuinely must inspect the package source, append ${OVERRIDE} to the command.`,
    ].join('\n');
}

let raw = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
    raw += chunk;
});
process.stdin.on('end', () => {
    /** @type {{ tool_name?: string, tool_input?: { command?: string } }} */
    let payload;

    try {
        payload = JSON.parse(raw);
    } catch {
        process.exit(0); // Never break the session on a malformed payload.
    }

    if (payload.tool_name !== 'Bash') {
        process.exit(0);
    }

    const violation = findViolation(payload.tool_input?.command ?? '');

    if (violation === null) {
        process.exit(0);
    }

    process.stderr.write(`${violation}\n`);
    process.exit(2);
});
