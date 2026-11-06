# Voice-over audio cleanup guide — video tutorials

Processing chain for screen-recorded tutorial narration, from raw clip to a
clean track ready for **VideoEdits** (silence removal + Whisper speech cleanup).

Sources checked September 2026: Audacity forum, MuseHub (Audacity noise
reduction guide, OpenVINO AI Tools), Hollyland, FFmpeg Micro (Aug 2026), PRX /
platform loudness targets.

---

## 1. Why the current chain leaves harsh sounds

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Chirps, bubbling, metallic or "underwater" voice | **Noise Reduction** too strong, or frequency smoothing too low | Reduction **6–12 dB** (never above 15 dB in one pass), smoothing **3–6** |
| Noise that pumps in and out, clipped word endings | **Noise Gate** closing too fast or fully (−100 dB) | Skip the gate, or use a slow release (see §4) |
| Hiss and harsh "s" come back at the end | **Compressor** and **Amplify** after noise reduction raise the residual noise and artifacts | Reorder the chain, drop Amplify |
| Room echo (reverb) that never goes away | Audacity has **no good built-in de-reverb** | Use an AI cleaner (audiocleaner.ai, Adobe Podcast) for echo |

---

## 2. Before recording

- Leave **2 seconds of silence** at the start of every clip. It is the noise
  profile.
- Leave about **half a second of room** before the first word and after the
  last one, so no cut lands on speech.
- Check the mic / OBS / Windows noise suppression: a gate there removes word
  endings before they are ever recorded, and no software can bring them back.

---

## 3. Recommended chain (Audacity 3.7.x / 4.0)

Apply in this order.

### 1. High-pass filter

- **80 Hz**, 12–24 dB per octave.
- Removes rumble (desk, traffic, AC). Voice carries nothing useful below it.

### 2. Noise Reduction

- `Effect ▸ Noise Reduction ▸ Get Noise Profile` on 1–2 s of **pure** silence
  (no breath, no chair creak — it would be learned as noise).
- Select the whole track and apply:

| Setting | Value |
| --- | --- |
| Noise reduction (dB) | **6–12** |
| Sensitivity | **4–6** (lower it if words sound choppy) |
| Frequency smoothing (bands) | **3–6** (raise it if you hear chirps) |

- Always **Preview** first. A little natural hiss sounds more professional than
  silence with a robotic voice.
- AI alternative: **OpenVINO Noise Suppression** (free, MuseHub). Only works on
  **Audacity 3.7.4–3.7.9** — not compatible with Audacity 4.0 yet.

### 3. Click Removal — only if needed

- Use it only when you actually hear mouth clicks. Otherwise skip it: it adds
  artifacts of its own.

### 4. EQ (Filter Curve) — small moves only

| Band | Move | Why |
| --- | --- | --- |
| 200–400 Hz | **−2 to −3 dB** | Mud and room boxiness |
| 3–5 kHz | **+1 to +2 dB** | Clarity / presence |

- If "s" sounds become harsh, do not boost more — use a de-esser plugin.

### 5. Compressor

| Setting | Value |
| --- | --- |
| Threshold | **−18 to −12 dB** |
| Noise floor | **Above your background noise** (e.g. −40 dB) |
| Ratio | **2:1 – 3:1** |
| Attack | **0.1–0.2 s** |
| Release | **1.0 s** |
| Make-up gain | On |

- The built-in compressor is slow for speech. Free alternatives that work in
  Audacity: **TDR Kotelnikov**, **Bertom Vocal Compressor**.

### 6. Loudness Normalization

- **Perceived loudness −16 LUFS** (spoken word; also the value in the script
  editing notes). YouTube normalises to −14 LUFS; either is fine.

### 7. Limiter

- Soft limit, **−1 dB**, no make-up gain.

---

## 4. What to remove from the chain

- **Amplify** and peak **Normalize** — Loudness Normalization already does both,
  and Amplify after the compressor only raises the noise.
- **Noise Gate** — VideoEdits already removes the silences, and a gate is what
  clips word endings. If you keep it, place it **after Noise Reduction and
  before the Compressor** with:

| Setting | Value |
| --- | --- |
| Level reduction | **−12 dB** (never −100) |
| Attack | **10 ms** |
| Hold | **50–100 ms** |
| Release | **250–500 ms** |

---

## 5. Recommended workflow

1. **audiocleaner.ai** (or Adobe Podcast) → noise + echo.
2. **Audacity**, only: high-pass 80 Hz → compressor → −16 LUFS → limiter −1 dB.
   Save it as a **Macro** (`Tools ▸ Macros`) and apply it to every clip in one
   click.
3. Put the clean audio back into the video, without re-encoding the picture:

   ```bash
   ffmpeg -i clip.mp4 -i clean.wav -map 0:v -map 1:a -c:v copy -c:a aac clip-clean.mp4
   ```

4. Upload the clean clips to **VideoEdits**.

Rules:

- Never use effects that change duration (tempo, truncate silence, trim) — the
  audio would drift out of sync with the video.
- Clean **before** uploading: cleaner audio helps both silence detection and
  Whisper.
- Heavy noise removal can make **Whisper** transcribe worse (artifacts the
  model was not trained on). If speech cleanup starts missing words, clean more
  gently.

---

## 6. VideoEdits silence threshold

The silence threshold must sit **above** your room's background noise.
Measure one clip:

```bash
ffmpeg -i clip.mp4 -af volumedetect -vn -f null -
```

Set `noise_floor_db` in `config/video-edit.php` to **`mean_volume − 12`**.
Current default: −40 dB. If you prefer not to measure, use −35 dB.
