He realizado dos correcciones en el frontend relacionadas con el modal y he actualizado el README, pero no he podido probar el modal en un navegador.

Las comprobaciones de TypeScript (type-check), ESLint y Prettier han pasado correctamente. Por favor, intenta probarlo en `/invoices`.

## Micrófono / puerta de ruido (noise gate)

La API de Whisper de OpenAI no limpia el audio. Únicamente lo transcribe y devuelve las marcas de tiempo de las palabras. Para eliminar la reverberación y el ruido, hay que utilizar Audacity, audiocleaner.ai, Adobe Podcast Enhance o herramientas similares.

* Orden recomendado: limpiar el audio de los clips originales antes de subirlos a VideoEdits. Un audio más limpio mejora tanto la detección de silencios como el funcionamiento de Whisper.

* Utiliza únicamente la reducción de ruido y la eliminación de reverberación (de-reverb). Evita cualquier opción de «puerta de ruido» (noise gate), «eliminar silencios» (strip silence) o «truncar silencios» (truncate silence), ya que eso volvería a provocar que se cortasen los finales de las palabras.

* No utilices efectos que cambien la duración del audio, como modificar el tempo o recortarlo, porque el audio podría desincronizarse respecto al vídeo.

* Para volver a introducir el audio limpio en el vídeo, utiliza este comando de ffmpeg:

Bash

```
ffmpeg -i clip.mp4 -i clean.wav -map 0:v -map 1:a -c:v copy -c:a aac clip-clean.mp4
```
