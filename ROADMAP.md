# Chess Coach Roadmap

**Estado:** vigente desde v1.6.0
**Release candidate:** v1.6.0
**Roadmap sustituido:** [ROADMAP_PRE_V1.6.md](docs/roadmap/archive/ROADMAP_PRE_V1.6.md)

## Fuentes de producto

- [Auditoría de producto](PRODUCT_AUDIT_2026-08.md): qué descubrimos.
- [Decisiones aprobadas](PRODUCT_AUDIT_DECISIONS_2026-08.md): qué decidimos.
- [Specification v1.6.0](docs/specs/1.6.0-training-quality-coach-foundation.md): qué debe entregar la foundation.
- [Diseño técnico v1.6.0](docs/technical/1.6.0-training-quality-coach-design.md): cómo encaja en la arquitectura actual.
- [Trazabilidad v1.6.0](docs/technical/1.6.0-requirement-traceability.md): relación entre requisitos, implementación y pruebas.
- [Informe de release candidate](docs/technical/1.6.0-release-report.md): alcance real, rollout, riesgos y deuda.

## Visión

Chess Coach debe ser un entrenador personal basado en partidas reales. Stockfish aporta verdad ajedrecística; la capa pedagógica decide qué merece entrenarse, cuándo conviene repetirlo y cómo demostrar que el jugador está aprendiendo.

Principios:

1. Calidad del entrenamiento por encima de cantidad de contenido.
2. Enseñar ideas transferibles, no memorizar bestmoves aisladas.
3. Personalización sustentada por evidencia estructurada.
4. Progreso expresado como dominio y autonomía, no como Elo ficticio.
5. Nova demuestra inteligencia mediante decisiones consistentes y hechos verificables.
6. Mobile-first y compatible con hosting compartido.

---

# Release actual

## v1.6.0 - Training Quality & Coach Foundation

### Objetivo

Evitar que cualquier detección del motor se convierta automáticamente en entrenamiento y construir un circuito pedagógico auditable que seleccione práctica útil, recuerde resultados y haga visible el aprendizaje.

### Pregunta que responde

> ¿Estamos entrenando lo correcto y puede el jugador percibir que está aprendiendo?

### Must Have

- Taxonomía v1 de diez conceptos.
- `TrainingOpportunity` como separación entre detección y contenido publicable.
- Vigencia de análisis, revalidación e histórico auditable.
- Identidad canónica, múltiples fuentes y deduplicación.
- Filtros duros con motivos de rechazo persistidos.
- `Pedagogical Score v1` versionado.
- `Training Selection v2` en shadow mode por defecto.
- Dificultad independiente de severidad.
- Routing pedagógico Flash/Scenario.
- Mastery v1 y Recent Performance separados.
- Repetición diferida determinista.
- Un único `Coach Decision` para Home, Training y Nova.
- Composer corto, estándar y largo con mezcla de nuevo, repaso y consolidación.
- Razones de selección y evidencia estructuradas.
- Cierre compacto con autonomía, mastery y próxima revisión.
- Racha, objetivos y progreso sin XP, monedas ni farming.
- Memoria mínima y copy factual de Nova.
- Backfill por lotes, rollout controlado y métricas mínimas.

### Very Nice to Have

Prioridad superior a Should Have, sin bloquear la foundation:

- Nova reactiva con CSS, sprite local y JavaScript vanilla.
- Estados `idle`, `thinking`, `correct`, `error`, `hint`, `explaining` y `session_complete`.
- Núcleo y expresión coherentes con eventos reales.
- Timings discretos y soporte `prefers-reduced-motion`.

### Should Have

- Vista interna de auditoría de oportunidades y comparaciones shadow.
- Ajuste intraentrenamiento limitado por resultados consecutivos.
- Primera experiencia de valor basada en comparación de autonomía anterior.
- Estado reciente visible junto a Mastery donde ayude a decidir.

### Fuera de scope

- LLM o APIs externas de IA.
- Conversación libre con Nova.
- XP, niveles, monedas, rankings, ligas o economía de pistas.
- Achievements generales.
- Taxonomía extensa o embeddings.
- Reescritura u optimización general de Stockfish.
- Claims fuertes de transferencia sin muestra suficiente.

### Dependencias

- Análisis Stockfish vigente y completo.
- Smart Tags y clasificaciones como señales de entrada.
- Training v2, Flash, Scenario, runs, hints y sessions existentes.
- Player DNA, progreso reciente, objetivos y racha existentes.

### Señales de éxito

- Los hard rejects y duplicados dejan de contaminar el inventario elegible.
- Cada oportunidad seleccionable tiene concepto, evidencia, score y razón.
- Shadow mode compara legacy/v2 sin alterar la experiencia.
- Los entrenamientos no rellenan cuotas con contenido mediocre.
- Mastery exige evidencia distribuida en el tiempo.
- Home, Training y Nova consumen la misma decisión.
- El cierre distingue hábito, dificultad y aprendizaje sin afirmar progreso falso.

---

# Evolución siguiente

## Transfer & Adaptation

### Objetivo

Medir con cautela si lo entrenado aparece después en partidas reales y adaptar el plan con esa evidencia.

### Pregunta que responde

> ¿Lo aprendido se traslada a mis partidas reales y cómo debe adaptarse el entrenamiento?

### Scope

- Vincular conceptos entrenados con oportunidades posteriores en partidas.
- Comparar recurrencia y severidad antes/después con confidence y sample size.
- Registrar transferencia positiva, recaídas y ausencia de muestra.
- Usar transferencia como input de Coach Decision.
- Recalibrar Mastery, dificultad, repetición y Composer con datos reales.
- Evolucionar algoritmos v1 mediante resultados shadow y uso real.

### Fuera de scope

- Atribuir causalidad con muestras pequeñas.
- Prometer mejora de Elo.
- Modelos estadísticos complejos sin datos suficientes.

### Dependencias

- Oportunidades canónicas y taxonomía estables.
- Historial de entrenamiento y nuevas partidas analizadas.
- Versionado de algoritmos y evidencia.

### Señales de éxito

- El sistema distingue mejora, recaída y falta de muestra.
- Coach Decision puede justificar un cambio de foco con datos posteriores.
- Los mensajes de Nova no exceden la confidence disponible.

## AI Coach / Nova Intelligence

### Objetivo

Permitir coaching natural y contextual sobre una inteligencia estructurada fiable.

### Pregunta que responde

> ¿Puede Nova utilizar el conocimiento de Chess Coach para explicar, responder y acompañar sin inventar?

### Scope

- LLM para explicación y razonamiento sobre hechos estructurados.
- Preguntas contextuales sobre partidas, Review y Training.
- Adaptación del lenguaje al nivel del jugador.
- Conversación apoyada en Coach Decision, Mastery, Recent Performance, historial y transferencia.
- Trazabilidad de los hechos usados en cada respuesta.

### Fuera de scope

- Sustituir Stockfish como fuente ajedrecística.
- Memoria textual ilimitada.
- Recomendaciones sin evidencia recuperable.

### Dependencias

- Foundation y Transfer & Adaptation validadas.
- Política de coste, privacidad, límites y fallback sin IA.

### Señales de éxito

- Las explicaciones son más claras sin reducir precisión.
- Nova responde con contexto verificable y reconoce incertidumbre.
- La aplicación sigue siendo funcional si la API no está disponible.

---

# Backlog futuro reevaluado

- Achievements pedagógicos excepcionales, no acumulativos.
- Protección o grace de racha sin castigo excesivo.
- Mastery v2 y dificultad adaptativa avanzada.
- Spaced repetition más sofisticado cuando haya datos.
- Taxonomía v2 con subconceptos calibrados.
- Similaridad avanzada solo si la deduplicación determinista resulta insuficiente.
- Repertorio y Openings Lab avanzados.
- Transferencia longitudinal avanzada.
- Nova visual más rica y sonidos opcionales.
- Automatización y sincronización con Chess.com.
- Integraciones DGT, ChessBase y preparación de torneos.
- Multiusuario solo cuando el producto deje de ser personal.

No se consideran backlog activo: XP, monedas, rankings, ligas, tienda, hint economy o gamificación basada en farming.

---

# Regla de actualización

Cada release futura debe documentar objetivo, pregunta, scope, fuera de scope, dependencias y señales de éxito. No se marcará como entregada ninguna capacidad que no tenga implementación y validación verificables.
