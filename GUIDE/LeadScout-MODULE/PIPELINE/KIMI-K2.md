Fase 0 (días 1-3, sin construir nada):
  Google Maps API / direcciones de directorios de agencias (Clutch,
  Sortlist, associaciones portuguesas) → spreadsheet
  + búsqueda manual de 50 agencias UK/IE/PT con 2-20 empleados

Fase 1 (semana 1, un solo comando de Artisan):
  php artisan leads:score
  → tira de una tabla de la BD
  → llama a Tavily (HTTP client de Laravel)
  → si relevante: Firecrawl (HTTP client de Laravel)
  → scoring con un LLM (prism-php)
  → guarda en PostgreSQL
  → genera el mensaje personalizado en un campo

Fase 2 (solo cuando Fase 1 te haya generado facturación real):
  Dashboard en Livewire + colas + cron de follow-ups