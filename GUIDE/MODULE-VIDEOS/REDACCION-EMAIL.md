**Asunto:** Vídeo 28 (Artifacts) regrabado · Aplicación web de edición y producción de píldoras · Propuesta de nuevas píldoras

Buenos días, equipo de Imagina Formación:

Espero que estén muy bien. Les escribo para ponerles al día sobre cinco puntos: la regrabación del vídeo 28, la aplicación web de edición y producción de vídeos que he desarrollado a partir de sus observaciones, el estado del audio, una propuesta de nuevas píldoras y las condiciones para las regrabaciones.

**1. Regrabación del vídeo 28 – «Qué son los Artifacts y cuándo usarlos»**

He regrabado el vídeo teniendo en cuenta las observaciones del equipo de Contenido:

- **Ritmo:** locución más fluida y con menos minipausas entre frases.
- **Cortes:** revisión de los empalmes para que ningún corte interrumpa palabras.
- **Prompts:** explico la intención de cada prompt en lugar de leerlo de forma literal.
- **Ejercicios prácticos:** cuatro casos aplicables al trabajo diario: una comparativa de operadores logísticos, una plantilla de correo de seguimiento, un checklist de calidad de informes y un plan de implantación por fases.
- **Pasos en pantalla:** los cambios de escena y la apertura de menús se muestran completos, sin cortes bruscos.

Aplicaré estos mismos criterios en los próximos vídeos.

**2. Módulo de edición y producción de las píldoras de vídeo**

Entre junio y septiembre he desarrollado una aplicación web propia para gestionar y automatizar distintas tareas de mi trabajo. Aprovechando ese proyecto, y para aplicar sus observaciones de forma sistemática en todos los vídeos, le he añadido un módulo de edición y producción de vídeo dedicado a las píldoras. La aplicación está construida con Laravel 13 / PHP 8.5 (arquitectura hexagonal modular), Inertia v3 + Vue + TypeScript y un pipeline de FFmpeg. Usa Whisper para la transcripción y Gemini para el análisis editorial (también puede trabajar con Claude u OpenAI). La seguridad sigue las recomendaciones de OWASP.

Lo que hace, relacionado directamente con sus comentarios:

- **Elimina los silencios largos** y las marcas de pausa que dejo al repetir una toma.
- **Detecta muletillas, tartamudeos, repeticiones, tomas repetidas y frases mal dichas** a partir de la transcripción palabra a palabra. Así los cortes no dejan palabras a medias.
- **Mide el ritmo de locución y las minipausas** con los tiempos exactos de cada palabra.
- **Detecta cuándo la IA está generando una respuesta en pantalla.** En lugar de cortar ese tramo, lo acelera y conserva el inicio y el final. Así el alumno ve el paso completo, sin saltos bruscos.
- **Atenúa las respiraciones** para que la locución suene más limpia.
- **Compara la locución con el guion:** señala contenido fuera de guion, partes no cubiertas, explicaciones que podrían resumirse y momentos en los que leo un prompt en voz alta.
- **Reviso y apruebo manualmente** cada corte que propone la IA antes de aplicarlo.

Además, he complementado la aplicación con una investigación sobre buenas prácticas de producción de vídeo educativo.

**3. Audio y micrófono**

Respondiendo a su pregunta: sí, grabo con un micrófono dedicado, un FIFINE orientado a voz en off y pódcast. Aun así, la nitidez no es la ideal, por lo que de momento sigo haciendo la masterización del audio de forma manual. Para corregir la reverberación y las palabras que suenan cortadas, estoy valorando un micrófono de estudio y un mejor acondicionamiento del espacio de grabación. Si tienen alguna recomendación de equipo que les haya funcionado con otros formadores, se lo agradecería.

**4. Propuesta de nuevas píldoras**

*Desarrollo de software y vibe coding*
- Cursor AI para vibe coding
- Claude Code para desarrollo de software
- Codex para vibe coding
- OpenCode para desarrollo web
- Devin AI (Windsurf) para vibe coding
- Grok Build para vibe coding

*Productividad para usuarios*
- ChatGPT para usuarios
- ChatGPT para tareas administrativas
- Claude para tareas administrativas

*Automatización*
- n8n

**5. Condiciones para las regrabaciones**

Los vídeos de *Claude para usuarios* ya fueron evaluados, aprobados y abonados en su momento. Aun así, quiero regrabarlos a partir de los comentarios de los alumnos y de las observaciones del equipo de Contenido, porque me interesa que el curso tenga la mejor calidad posible.

Como se trata de trabajo adicional sobre entregas ya aceptadas, me gustaría acordar con ustedes cómo compensarlo. Además, para regrabar necesito una suscripción de Claude activa, que actualmente no mantengo. Les propongo dos opciones:

- **Opción A:** reconocer las horas de regrabación de los [N] vídeos restantes a la tarifa habitual.
- **Opción B:** realizar las regrabaciones sin coste adicional, siempre que acordemos por escrito la producción de al menos [M] de las nuevas píldoras propuestas en el punto 4, a la tarifa habitual. En ambos casos, les pediría que Imagina Formación facilite el acceso a Claude durante la producción, ya sea con una licencia de equipo o reembolsando la suscripción.

Estoy abierto a cualquier otra fórmula que les encaje mejor. Mientras tanto, sigo adelante con la regrabación del vídeo 28 como muestra del nuevo estándar.

Quedo atento a cuáles consideran prioritarias para empezar a preparar los guiones.

Muchas gracias por el seguimiento y por sus observaciones, que me están ayudando mucho a mejorar la calidad de los vídeos.

*Redacción revisada con apoyo de herramientas de IA.*

Un saludo,
Argenis Gonzalez
argenis.dev
