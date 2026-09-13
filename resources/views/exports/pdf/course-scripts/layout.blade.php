{{--
    Course Scripts deliverable layout (spec 002-course-scripts, FR-47).

    A document a presenter records from, not a corporate report: no brand band,
    labels in the course language, DejaVu Sans for full UTF-8 coverage. Every
    value is escaped with {{ }} — model output is untrusted (OWASP LLM05).

    Sections: doc_title, content. Data: $t (RenderVocabulary labels), $language.
--}}
<!DOCTYPE html>
<html lang="{{ $language ?? 'es' }}">
<head>
    <meta charset="utf-8">
    <title>@yield('doc_title')</title>
    <style>
        @page { margin: 56px 48px 60px 48px; }
        * { font-family: DejaVu Sans, sans-serif; }
        html, body { margin: 0; padding: 0; }
        body { font-size: 10.5px; line-height: 1.5; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 12px 0; color: #111827; }
        h2 { font-size: 13px; margin: 18px 0 8px 0; padding-bottom: 3px; border-bottom: 1.5px solid #4338ca; color: #312e81; }
        h3 { font-size: 11.5px; margin: 12px 0 6px 0; color: #1f2937; }
        p { margin: 0 0 7px 0; }
        ul { margin: 0 0 8px 0; padding-left: 16px; }
        li { margin-bottom: 3px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta td { padding: 3px 8px; border: 1px solid #e5e7eb; font-size: 10px; }
        .meta td.label { width: 28%; background: #f3f4f6; font-weight: bold; }
        .notice { border-left: 3px solid #b45309; background: #fffbeb; padding: 6px 10px; margin: 8px 0; font-size: 9.5px; }
        .continuity { background: #eef2ff; padding: 8px 10px; margin: 8px 0 4px 0; }
        .demo { display: inline; font-size: 9px; font-weight: bold; color: #ffffff; background: #4338ca; padding: 1px 5px; }
        .narration { margin: 6px 0; padding: 6px 10px; border-left: 3px solid #9ca3af; font-style: italic; }
        .prompt { margin: 6px 0; border: 1px solid #4338ca; background: #f5f3ff; }
        .prompt .tag, .result .tag, .actions .tag, .show .tag, .table-tag, .note .tag {
            display: block; font-size: 8.5px; font-weight: bold; letter-spacing: .06em; text-transform: uppercase; padding: 3px 8px;
        }
        .prompt .tag { color: #ffffff; background: #4338ca; }
        .prompt pre { margin: 0; padding: 6px 8px; white-space: pre-wrap; font-size: 9.5px; }
        .result { margin: 6px 0; border: 1px solid #047857; background: #ecfdf5; }
        .result .tag { color: #065f46; }
        .result p { padding: 0 8px 6px 8px; margin: 0; }
        .actions { margin: 6px 0; border: 1px solid #d1d5db; }
        .actions .tag { color: #374151; background: #f3f4f6; }
        .actions ul { padding: 4px 8px 4px 24px; margin: 0; }
        .show { margin: 6px 0; border: 1px dashed #b45309; background: #fffbeb; }
        .show .tag { color: #92400e; }
        .show p { padding: 0 8px 6px 8px; margin: 0; }
        .table-tag { color: #312e81; padding-left: 0; }
        .note { margin: 6px 0; color: #6b7280; font-size: 9.5px; }
        .note .tag { display: inline; padding: 0 4px 0 0; }
        table.grid { width: 100%; border-collapse: collapse; margin: 4px 0 10px 0; }
        table.grid th { background: #eef2ff; color: #312e81; text-align: left; font-size: 9px; padding: 5px 7px; border: 1px solid #c7d2fe; }
        table.grid td { font-size: 9.5px; padding: 5px 7px; border: 1px solid #e5e7eb; vertical-align: top; }
        table.grid tr.total td { font-weight: bold; background: #f9fafb; }
        .checklist li { list-style: none; margin-left: -14px; }
        .checklist li:before { content: "☐ "; }
        .endmark { margin-top: 16px; text-align: center; color: #6b7280; font-size: 9px; }
        .page-break { page-break-before: always; }
        .footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 20px; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .footer .pageno:after { content: counter(page) " / " counter(pages); }
        .kv span.key { font-weight: bold; }
        .doc-footer { color: #6b7280; font-size: 9px; font-style: italic; border-top: 1px solid #e5e7eb; padding-top: 5px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="footer">
        <span>@yield('doc_title')</span>
        <span style="float: right;" class="pageno"></span>
    </div>

    <main>
        @yield('content')
    </main>
</body>
</html>
