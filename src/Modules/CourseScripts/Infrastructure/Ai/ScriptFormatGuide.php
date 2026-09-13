<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

/**
 * The author's reference format, distilled from GUIDE/MODULE-VIDEOS
 * (Guion_Video_39/43/46 and Propuestas_Logistica_Heliantia — research §11–§12).
 *
 * Shared by the writer and reviewer agents as part of their constant
 * instructions, so it sits in the cached prefix of every call.
 */
final class ScriptFormatGuide
{
    public const string SCRIPT = <<<'GUIDE'
        REFERENCE FORMAT OF A RECORDING SCRIPT ("GUIÓN")

        A script is what a presenter records a short screen-recorded video from.
        It has, in this order:
        1. Technical information: duration, block, recording format (e.g.
           "Grabación de pantalla con narración"), position in the block.
        2. Learning objectives: 3–6 concrete, observable objectives.
        3. Continuity: 2–4 sentences naming previous videos by number and what
           they taught, how this video builds on them, and — when there is one —
           what the next video will take from this one. The first video of a
           block opens that block.
        4. Numbered sections, each with its minutes: "1. INTRODUCCIÓN (1 minuto)".
           Sections may have numbered sub-sections ("2.1 Preparar la agenda").
           Top-level minutes add up to the video duration. A typical 9-minute
           video has about 6 sections: introduction, 3–4 demonstrations or
           explanations, a practical closing.
        5. Inside sections, clearly separated parts:
           - Narration: the exact words the presenter says, in quotes, natural
             spoken language, second person, no filler.
           - PROMPT: the literal text the presenter types into the tool on screen.
             Complete and paste-ready. Only when the course teaches a tool.
           - Expected result on screen: what the audience sees after the prompt.
           - ACCIONES EN PANTALLA: short imperative steps for the presenter.
           - MOSTRAR EN PANTALLA: material shown (and optionally read aloud)
             before a demonstration — context, notes, data. When it lives in the
             practice pack, name the practice file.
           - TABLA EN PANTALLA: a table shown on screen, with columns and rows.
           - Presenter notes: timing cues ("mantener 8 segundos en pantalla").
           Demonstrations are numbered across the script: DEMO 1, DEMO 2…
           A "comparison" section shows an incorrect case and its corrected version.
        6. Closing parts: RESUMEN (5–8 bullet takeaways), PRÓXIMO VÍDEO (the next
           video by number and title, only if one exists), NOTAS TÉCNICAS PARA LA
           GRABACIÓN (preparación previa — including every practice file to have
           ready —, durante la grabación, conectores/herramientas necesarias or an
           explicit statement that none are needed, continuidad, organizaciones
           ficticias usadas) and VERIFICACIÓN FINAL (a checklist of what must
           visibly happen on screen, one item per demo plus format checks).

        Quality bar: specific over generic. Real features, real steps, real
        mistakes people make — grounded first in the author's notes, then the
        brief, then the research provided. Never invent product features. Keep
        the course's fictional organisations and characters consistent.
        GUIDE;

    public const string PRACTICE = <<<'GUIDE'
        REFERENCE FORMAT OF A PRACTICE PACK ("DOCUMENTO DE PRÁCTICA")

        Only when a demonstration needs prepared material: documents to upload,
        notes to paste, data to analyse, an incorrect/correct case. A purely
        conceptual video gets none, and says why.

        A practice pack has:
        - Header: "DOCUMENTO DE PRÁCTICA · VÍDEO NN — title".
        - Files: the file name of every artifact, descriptive, with underscores
          (e.g. Propuesta_Logistica_ProveedorA_2026).
        - Setup instruction: exactly what to do with the files before recording
          (e.g. "Subir ambos documentos a Google Drive como archivos separados").
        - Usage: which DEMO (and section) uses each file.
        - NOTA PARA EL INSTRUCTOR: why the material is built the way it is — the
          deliberate differences, gaps or imperfections it contains and the
          result they are meant to provoke in the demo. Example: two supplier
          proposals that differ on price, coverage and penalties so the
          comparison table is informative, both deliberately imperfect so the
          recommendation has nuance.
        - The full content of every artifact: a complete, realistic document of
          its genre. A proposal has a title block (issuer, addressee, reference,
          date), presentation, pricing table with a total row, coverage,
          penalties table, cancellation terms, additional conditions and a
          contact + legal footer. An email has headers and a body. Meeting notes
          are deliberately messy when the demo structures them.

        Rules: figures add up exactly; artifacts that are compared share the same
        structure and differ only where designed; every organisation and person
        is invented (never real companies, people, emails, phones or URLs — build
        email domains from the invented company name); reuse the course's
        fictional organisations when they fit.
        GUIDE;
}
