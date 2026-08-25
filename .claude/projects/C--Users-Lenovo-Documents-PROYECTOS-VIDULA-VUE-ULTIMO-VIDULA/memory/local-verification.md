---
name: local-verification
description: How to typecheck/verify frontend locally on this Windows host (vite build fails natively)
metadata:
  type: project
---

On this Windows host, `vite build` (and `npm run build`) fails with `Cannot find module './rolldown-binding.win32-x64-msvc.node'` — Vite uses rolldown and the native binary is not installed for win32. This is why the project builds through Sail/Docker (Linux).

**How to apply:** For a fast local check without Docker, run the strict typecheck directly:
`node node_modules/vue-tsc/bin/vue-tsc.js --noEmit -p tsconfig.json` (exit 0 = clean). This compiles all `.vue` SFC templates + TS under strict mode. For a real bundle/build, use `./vendor/bin/sail npm run build`. Do not treat a local `vite build` rolldown crash as a code error.
