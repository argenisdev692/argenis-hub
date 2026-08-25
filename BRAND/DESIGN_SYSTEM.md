Jerarquía e Integración de Paleta

Cyber Tech (Violeta / Cyan / Magenta):  
Aplícalo en el Hero Section, módulos con IA (Post Content, ATS Matcher) y elementos interactivos de desarrollo (.dev).  
- **Cyan + Violeta**: Energía principal, Hero Glow, tipografía con píxeles del logo y acciones interactivas.  
- **Magenta**: Acento secundario de IA (highlights, notificaciones especiales, estados hover/active de módulos inteligentes). Úsalo con moderación para no competir con el cyan/violeta.  
Refleja la energía del Hero Glow y la identidad tecnológica del producto.

Premium Gold (#D4AF37):  
Resérvalo exclusivamente para acciones financieras y conversión directa (Invoices cobradas, botón CTA principal, estatus VIP de clientes o tratos cerrados).  
Usar el dorado como acento puntual evita que compita con los neones cian y violeta, manteniendo su valor percibido de premium y conversión.

Bases Nocturnas (#050714 y #08081F):  
El fondo hiper-oscuro permite que tanto los resplandores del Hero como los bordes finos #1E2A4A de las tarjetas resalten con nitidez.  
Estas bases son la estructura sobre la que viven los glows y los acentos de marca.

Estados de Sistema (fuera de la jerarquía de marca):  
- `--status-success` (#10b981) → feedback positivo  
- `--status-danger` (#f43f5e) → feedback de error/alerta  
Nunca mezclarlos con gold ni con cyan/violeta para mantener claridad inmediata.

---

Recomendación de Maquetación para el Hero Glow

Para recrear la estela curva luminosa sin recargar la web con imágenes pesadas:

```html
<!-- Hero Container -->
<div class="relative bg-hub-bg min-h-screen overflow-hidden flex flex-col justify-center items-center">
  
  <!-- Dynamic Atmospheric Glows -->
  <div class="absolute -bottom-20 left-1/4 w-[500px] h-[300px] bg-brand-magenta/20 blur-[120px] rounded-full pointer-events-none"></div>
  <div class="absolute -bottom-20 right-1/4 w-[500px] h-[300px] bg-brand-cyan/20 blur-[120px] rounded-full pointer-events-none"></div>
  
  <!-- Optional subtle purple glow for depth (centered) -->
  <div class="absolute top-1/3 left-1/2 -translate-x-1/2 w-[600px] h-[400px] bg-brand-purple/15 blur-[140px] rounded-full pointer-events-none"></div>

  <!-- Hero Content -->
  <div class="relative z-10 text-center px-4">
    <h1 class="text-5xl md:text-6xl font-bold text-txt-main">
      Argenis<span class="text-transparent bg-clip-text bg-brand-gradient">.dev</span>
    </h1>
    <p class="text-txt-muted mt-4 max-w-xl mx-auto">
      Gestión inteligente de vacantes ATS, finanzas y contenido con IA.
    </p>
  </div>
</div>