# Guía de audio en OBS Studio para voz en off de videotutoriales

*Micrófono Fifine USB, auriculares por puerto de 3.5 mm y filtros de limpieza de voz*


## 1. Qué pasaba y por qué

En Windows la voz sonaba bien porque el sistema aplica efectos de mejora (reducción de ruido y control de volumen). OBS **no usa esos efectos**: recibe la señal del dispositivo tal cual. Además, en OBS estaba seleccionado el dispositivo **External Mic (Realtek)**, que es la entrada de 3.5 mm de los auriculares, y no el **Fifine USB**. Por eso la voz salía baja y un poco aguda.

La solución tiene tres partes: elegir bien los dispositivos, configurar el monitoreo para escucharte y reemplazar el procesado de Windows con **filtros de OBS aplicados al micrófono**.


## 2. Configuración de audio en OBS


### 2.1 Ajustes → Audio

| Opción | Valor | Motivo |
|---|---|---|
| Sample Rate | 48 kHz | Es la frecuencia estándar de video. Debe coincidir con la de Windows. |
| Channels | Mono | Una voz en off no necesita estéreo. |
| Desktop Audio | Disabled | Evita que se cuelen sonidos de Windows en la grabación. |
| Desktop Audio 2 | Disabled | Igual que el anterior. |
| Mic/Auxiliary Audio | Fifine USB | Cambiar desde External Mic (Realtek) al micrófono Fifine. Aparece como Microphone (Fifine...) o USB PnP Audio Device. |
| Mic/Auxiliary Audio 2, 3 y 4 | Disabled | No se usan. |
| Monitoring Device (en Advanced) | Headphones (Realtek(R) Audio) | Es la salida donde conectas los auriculares de manos libres. No dejes Default. |


### 2.2 Dos dispositivos, dos funciones

| Dispositivo | Para qué sirve | Dónde se configura |
|---|---|---|
| Fifine USB | ENTRADA: captura tu voz. | Ajustes → Audio → Mic/Auxiliary Audio |
| Realtek (puerto 3.5 mm con auriculares) | SALIDA: solo para escucharte y escuchar el audio mientras grabas. | Ajustes → Audio → Advanced → Monitoring Device |

> **Importante:** No uses el Realtek como micrófono. El puerto de 3.5 mm de los manos libres queda solo para escuchar.


## 3. Configuración en Windows

Para que Windows no procese la señal por duplicado:

1. Configuración de sonido → **Grabar** (o Sound → Recording) → clic derecho en el Fifine → **Propiedades**.
2. Pestaña **Opciones avanzadas**: formato **48000 Hz, 16 bits**. Un desajuste de frecuencia entre Windows y OBS suele causar voz aguda.
3. Desmarca **Permitir que las aplicaciones tomen el control exclusivo**.
4. Pestaña **Niveles**: volumen del micrófono en **100**. Si aparece Mic Boost o Amplificación, súbelo solo un poco.
5. Pestaña **Mejoras**: **desactiva todas**. Quien procesa la voz será OBS, no Windows.
6. No actives además funciones como Voice Focus de Windows. Para esta configuración, el procesado lo hace OBS.


## 4. Escucharte con los auriculares (monitoreo)

La opción de monitoreo **no está dentro de Ajustes**, sino en la ventana principal de OBS.

1. En la barra superior abre **Edit → Advanced Audio Properties**. Otra forma: clic derecho sobre el medidor de Mic/Aux en el panel Audio Mixer.
2. En la fila de tu micrófono (Mic/Aux), columna **Audio Monitoring**, elige la opción según el momento:

| Opción | Resultado | Cuándo usarla |
|---|---|---|
| Monitor Off | No te escuchas. | Por defecto, no sirve para este caso. |
| Monitor Only (mute output) | Te escuchas, pero NO se graba tu voz. | Solo para probar el sonido. |
| Monitor and Output | Te escuchas y se graba. | Para grabar los videotutoriales. |

- **Mono**: déjalo marcado (voz en off).
- **Tracks**: asegúrate de que la **pista 1** esté marcada. Marcar de la 1 a la 6 también funciona.
- **Volume**: en tu caso aparece en 3.0 dB. Déjalo así al empezar y ajusta después de escucharte.
- **Window Capture / Window Capture 2**: si no quieres sonido de esas ventanas, ponles Monitor Off, desmarca sus pistas o silénciales en el mezclador.

En Salida → Grabación (Output → Recording) verifica también que la **Pista de audio 1** esté marcada.


## 5. Filtros en OBS: aquí está la limpieza de la voz


### 5.1 Dónde se aplican

> **Importante:** Los filtros deben estar en el micrófono (Fifine / Mic/Aux), NO en la captura de ventana. Si en la ventana de filtros ves 'Filters for Window Capture', estás en la fuente equivocada.

Ruta correcta: **Audio Mixer → Fifine (Mic/Aux) → engranaje → Filters → botón +**.


### 5.2 Cadena recomendada (punto de partida)

Agrega estos cuatro filtros en este orden. El Limiter debe ser siempre el último:

```text
1. Noise Suppression
      Method:      RNNoise

2. Compressor
      Ratio:       3:1
      Threshold:   -18 dB
      Attack:        6 ms
      Release:     100 ms
      Output Gain:  +3 dB

3. Expander
      Ratio:       4:1
      Threshold:   -40 dB
      Attack:       10 ms
      Release:     100 ms
      Detection:   RMS
      Output Gain:   0 dB

4. Limiter
      Threshold:    -3 dB
      Release:      60 ms
```


### 5.3 Qué hace cada filtro

| Filtro | Función |
|---|---|
| Noise Suppression (RNNoise) | Quita el ruido de ambiente constante como ventilador o zumbidos suaves. RNNoise es el método de mayor calidad incluido en OBS, aunque usa algo más de CPU. |
| Compressor | Hace el volumen más parejo: baja las partes fuertes y deja más presentes las bajas. Esto reproduce el volumen estable que oías en Windows. |
| Expander | Cuando callas, baja mucho el nivel del micrófono, así que teclado, mouse y ventilador casi no se oyen entre frases. Suena más natural que un Noise Gate. |
| Limiter | Protección final contra picos. Evita que la voz sature si gritas o das un golpe al micrófono. |


### 5.4 Alternativa: Noise Gate en lugar del Expander

Si prefieres un Noise Gate, colócalo en la misma posición que el Expander (después de Noise Suppression) y usa estos valores:

| Parámetro | Valor |
|---|---|
| Close Threshold | -32 dB |
| Open Threshold | -26 dB |
| Attack | 25 ms |
| Hold | 200 ms |
| Release | 150 ms |

El gate corta el micrófono cuando no hablas. Ajústalo hablando normal: si corta el inicio de las palabras, **baja el Open Threshold unos dB**; si aún se cuelan clics, súbelo. No uses Expander y Noise Gate a la vez.


### 5.5 Filtros opcionales

- **Gain**: si la voz sigue baja, agrégalo al principio de la cadena. Con el Fifine USB suele hacer falta poco o nada. Empieza en 0 dB y sube de 3 en 3 dB hasta que la voz llegue a la zona deseada.
- **Ecualizador de 3 bandas**: si la voz aún suena aguda, baja **High** entre 3 y 6 dB.
- Si el Fifine tiene perilla de ganancia, ajústala primero en el micrófono antes de subir la ganancia en OBS: da mejor calidad.


## 6. Niveles y posición del micrófono


### 6.1 Niveles objetivo en el medidor de OBS

| Nivel | Zona | Interpretación |
|---|---|---|
| -18 a -12 dB | Verde | Perfecto para voz en off. |
| -12 a -6 dB | Amarillo | Fuerte, pero correcto. Picos máximos hasta -6 dB. |
| 0 dB | Rojo | Demasiado alto: satura. Baja la ganancia. |

No intentes que la voz llegue constantemente a 0 dB.


### 6.2 Posición del micrófono

- Boca a **8–10 cm** del micrófono.
- Habla ligeramente hacia un lado del micrófono, no directo al centro, para reducir golpes de aire en las letras P y B.
- Aleja el micrófono del teclado y del mouse. Un brazo o soporte antivibración reduce el golpeteo que viaja por el escritorio.


## 7. Quitar el ruido de clics del mouse y del teclado


### 7.1 Mejor hacerlo en OBS mientras grabas

El Expander (o el Noise Gate) de la cadena anterior es la herramienta principal. Reduce el sonido del teclado y del mouse cuando están por debajo del nivel de tu voz. El umbral depende del ruido real de tu habitación: ajústalo según lo que escuches en una prueba.


### 7.2 Reducir el ruido en el origen

- Micrófono cerca de la boca y con menos ganancia: la voz domina y los clics suenan más lejos.
- Mouse silencioso o tapete grueso.
- Teclado más suave o ubicado lejos del micrófono.


### 7.3 Audacity solo para retoques

- **Reducción de ruido**: sirve para ruido constante como ventilador o siseo, no para clics sueltos.
- Un clic en una pausa: selecciónalo y pulsa **Ctrl+L** (silencio) o bórralo.
- Un clic sobre la voz: el efecto **Click Removal** ayuda un poco, pero no es perfecto.


### 7.4 El método más limpio para tutoriales

Graba primero la pantalla (con clics y teclado, sin voz) y después graba la **voz en off por separado**, viendo el video. Así no hay nada que quitar y además hablas más claro y sin tropiezos.

Si prefieres no hacerlo manual, herramientas con IA como **Adobe Podcast Enhance** limpian ruido y voz de una sola vez.


### 7.5 Limitación importante

> **Importante:** Si escribes mientras hablas (clic, clic, clic en simultáneo con la voz), ningún Expander ni Noise Gate puede eliminar esos clics sin afectar también la voz, porque ocurren a la vez. Si haces: hablas, silencio, escribes, silencio, la mejora es enorme. Si aun así entra el teclado mientras hablas, se puede avanzar con ecualización o con procesado de NVIDIA, si tu PC tiene una GPU compatible.


## 8. Prueba de 20 segundos

Con los filtros en el Fifine, graba una prueba **sin cambiar nada más**:

1. 5 segundos en silencio.
2. 5 segundos hablando.
3. 5 segundos escribiendo en el teclado mientras hablas.
4. 5 segundos hablando normalmente.

Revisa la grabación con los auriculares y mira el medidor del Mixer mientras hablas.


## 9. Ajustes rápidos según lo que oigas

| Síntoma | Qué ajustar |
|---|---|
| Voz baja | Sube la ganancia del Fifine o agrega Gain en OBS. Sube Output Gain del Compressor en 1–2 dB. |
| Voz aguda | Verifica 48000 Hz en Windows y OBS, y que uses el Fifine y no el Realtek. Baja High en el Ecualizador de 3 bandas. |
| Voz saturada o en rojo | Baja la ganancia. Revisa que el Limiter esté en -3 dB. |
| Se cortan los inicios de palabras | Baja el umbral del Expander (por ejemplo de -40 a -45 dB) o el Open Threshold del Noise Gate. |
| Se cuelan teclado y mouse entre frases | Sube el umbral del Expander (por ejemplo de -40 a -35 dB) y acerca el micrófono a la boca. |
| Ruido de ventilador | Mantén RNNoise y aleja el micrófono de la fuente de ruido. |
| No me escucho en los auriculares | Revisa Monitoring Device (Headphones Realtek) y Audio Monitoring en Monitor and Output. |
| No se graba mi voz | Revisa que Audio Monitoring no esté en Monitor Only y que la pista 1 esté marcada. |


## 10. Lista de verificación final

- [ ] Mic/Auxiliary Audio = Fifine USB.
- [ ] Desktop Audio = Disabled.
- [ ] Sample Rate 48 kHz, Channels Mono, también en Windows a 48000 Hz.
- [ ] Monitoring Device = Headphones (Realtek).
- [ ] Audio Monitoring = Monitor and Output (para grabar).
- [ ] Pista 1 marcada en el micrófono y en Output → Recording.
- [ ] Mejoras de Windows desactivadas y modo exclusivo desactivado.
- [ ] Filtros aplicados al Fifine: Noise Suppression, Compressor, Expander, Limiter.
- [ ] Voz promedio entre -18 y -12 dB, picos máximos de -6 dB.
- [ ] Prueba de 20 segundos revisada con auriculares.
