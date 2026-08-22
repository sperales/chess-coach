# Chess Coach - Addendum de decisiones de producto

**Fecha:** 22 de agosto de 2026  
**Documento base:** [PRODUCT_AUDIT_2026-08.md](PRODUCT_AUDIT_2026-08.md)  
**Baseline:** v1.5.2 release candidate  
**Objetivo:** convertir la auditoría aceptada en una primera hipótesis de producto concreta e implementable.  
**Alcance:** decisión de producto y arquitectura funcional. No incluye implementación.

---

## 0. Decisiones ejecutivas

Este addendum recomienda:

1. Adoptar una taxonomía v1 de **10 conceptos pedagógicos**.
2. Separar análisis y entrenamiento mediante un modelo explícito de `TrainingOpportunity`.
3. Aplicar **filtros duros** antes de puntuar una oportunidad.
4. Publicar únicamente oportunidades con `pedagogical_score >= 65/100`.
5. Elegir Flash o Scenario por la cantidad de decisiones necesarias para demostrar la idea, no por la gravedad del error.
6. Componer entrenamientos con una mezcla adaptativa de foco, repaso, novedad y partida reciente.
7. Representar mastery como estados: `Iniciando`, `Aprendiendo`, `Consolidando` y `Estable`.
8. Mantener mastery separado de performance reciente.
9. No introducir XP, niveles, monedas, ranking ni economía de pistas en esta fase.
10. Usar como gamificación v1: racha, objetivos, mastery, progreso del entrenamiento y cierre significativo.
11. Implementar Nova reactiva con **CSS + sprite sheet**, sin Lottie ni Canvas.
12. Crear un único `Coach Decision` consumido por Home, Training y Nova.
13. Convertir la siguiente release importante en **Training Quality & Coach Foundation**, antes de un AI Coach basado en LLM.

---

# 1. Comparativa con productos de referencia

La comparación no busca replicar productos completos. Se centra en mecanismos que resuelven problemas concretos de hábito, selección, progresión y feedback.

## 1.1 Chess.com

### Mecanismos relevantes

- Puzzles con rating adaptativo y dificultad relativa al usuario.
- Modos diferentes para intenciones diferentes: Rated, Custom, Rush, Battle y Daily.
- Estadísticas posteriores: rating, tiempo objetivo, pass rate, intentos y temas.
- Posibilidad de entrenar únicamente puzzles fallados.
- Learn Path ordenado por niveles y con retos interactivos.
- Streak general que admite jugar, estudiar, resolver puzzles o revisar partidas.
- Streak del puzzle diario con periodo de gracia.
- Puzzle Points opcional con tiers, niveles y bonus.

Fuentes oficiales: [Puzzles](https://support.chess.com/en/articles/8608686-how-do-puzzles-work-on-chess-com), [Lessons](https://support.chess.com/en/articles/8609703-how-do-lessons-work-on-chess-com), [Streaks](https://www.chess.com/news/view/announcing-streaks), [Puzzle Points](https://support.chess.com/en/articles/9681952-what-are-puzzle-points-on-chess-com).

### Qué merece la pena estudiar

**Separar modos por intención.** Rated mide habilidad; Custom permite practicar un tema; Rush ofrece intensidad; Daily crea hábito.

Problema que resuelve: un único formato no puede cubrir aprendizaje, evaluación, repetición y entretenimiento al mismo tiempo.

Aplicación en Chess Coach: Flash, Scenario, Review y Analysis Board deben conservar objetivos distintos. No deben compartir una única métrica ni mezclarse sin explicar por qué.

### Qué no copiar

- Puzzle Points, tiers y prestigio como eje principal.
- Leaderboards o competición social.
- Bonificaciones por velocidad en ejercicios reflexivos.
- Una proliferación de modos antes de consolidar la calidad del entrenamiento.

Chess Coach debe explicar mejor que Chess.com por qué una posición concreta aparece hoy y cómo se relaciona con partidas propias.

## 1.2 Lichess

### Mecanismos relevantes

- Puzzle rating adaptativo.
- Puzzle Dashboard con fortalezas, áreas de mejora e historial.
- Entrenamiento por temas.
- Repetición de puzzles fallados.
- Puzzle Streak con dificultad creciente, sin reloj y un fallo como cierre.
- Puzzle Storm como experiencia separada, rápida y no rated.
- Opción de continuar una posición contra Stockfish después del puzzle.

Fuentes oficiales: [Puzzle Dashboard y Streak](https://lichess.org/page/changelog-2021), [Puzzle Storm](https://lichess.org/page/storm), [Puzzle accessibility and hints](https://lichess.org/page/blind-mode-changelog).

### Qué merece la pena estudiar

**Dashboard de fortalezas y debilidades conectado directamente a práctica temática.**

Problema que resuelve: las estadísticas dejan de ser contemplativas y se convierten en una entrada al entrenamiento.

Aplicación en Chess Coach: cada concepto de mastery debe permitir iniciar un entrenamiento relevante, pero sin revelar de antemano el motivo táctico exacto si eso resuelve parte del ejercicio.

### Qué no copiar

- Una taxonomía enorme de motivos desde el primer día.
- Puzzles esencialmente aleatorios dentro de un tema.
- Métricas de puzzle separadas de la transferencia a partidas.
- Enterrar el progreso en páginas secundarias difíciles de descubrir.

Chess Coach debe usar menos categorías, pero conectarlas con errores, recurrencia y evolución real.

## 1.3 Chessable

### Mecanismos relevantes

- MoveTrainer con repetición espaciada.
- Cada movimiento de una variante tiene su propio nivel y calendario.
- Un acierto amplía progresivamente el intervalo.
- Un fallo reduce el intervalo y devuelve el elemento a práctica próxima.
- Los niveles de revisión van aproximadamente de 4 horas a 6 meses.

Fuente oficial: [MoveTrainer spaced repetition](https://support.chess.com/en/articles/10319322-how-does-the-spaced-repetition-scheduling-work).

### Qué merece la pena estudiar

**El estado pertenece a la unidad concreta que se aprende, no al curso entero.**

Problema que resuelve: evita considerar dominada una materia porque se completó una vez.

Aplicación en Chess Coach: cada concepto y, secundariamente, cada oportunidad canónica deben tener historial de consolidación y próxima revisión.

### Qué no copiar

- Orientar todo el producto a memorización exacta.
- Reiniciar de forma excesivamente agresiva el progreso por un único fallo.
- Exigir reproducir una línea única cuando existen alternativas igualmente válidas.

Chess Coach debe consolidar ideas y decisiones, no memorizar siempre secuencias.

## 1.4 Aimchess

### Mecanismos relevantes

- Análisis agregado de partidas propias.
- Comparación de habilidades en seis grandes dimensiones.
- Weekly Personalized Study Plan.
- Entrenadores especializados: conversión, aperturas, prevención de blunders, visualización y entrenamiento 360.
- Puzzles personalizados extraídos de errores propios.
- Una propuesta explícita de valor frente al motor: convertir movimientos aislados en orientación generalizable.

Fuente oficial: [Aimchess](https://aimchess.com/).

### Qué merece la pena estudiar

**Es el referente conceptual más cercano a Chess Coach:** partidas propias -> diagnóstico agregado -> plan -> actividad especializada.

Problema que resuelve: un motor informa de una posición; el producto intenta construir un programa.

Aplicación en Chess Coach: un `Coach Decision` único y entrenadores/formato elegidos por el tipo de aprendizaje necesario.

### Qué no copiar

- Asumir que toda posición perdida es buen ejercicio.
- Mantener opaca la razón exacta por la que se selecciona una actividad.
- Limitar la personalización a “tu debilidad es X, aquí hay ejercicios X”.
- Presentar comparaciones o claims de mejora sin muestra visible.

Chess Coach puede diferenciarse explicando recurrencia, confianza, mastery y transferencia.

## 1.5 Duolingo

### Mecanismos relevantes

- Learning Path que reduce la decisión de “qué hago ahora”.
- Feedback inmediato y celebraciones breves.
- Separación entre racha y objetivo diario.
- Una única lección mantiene la racha; el objetivo mide esfuerzo adicional.
- Repetición integrada dentro del camino.
- Personajes con reacciones y personalidad consistente.
- Score de curso separado de XP de actividad.

Duolingo reportó que separar racha y objetivo produjo mejoras de retención y actividad. Fuentes oficiales: [Separación entre streak y daily goal](https://blog.duolingo.com/improving-the-streak/), [Learning Path](https://blog.duolingo.com/new-duolingo-home-screen-design/), [Método y feedback](https://duolingo-papers.s3.amazonaws.com/reports/Duolingo_whitepaper_duolingo_method_2023.pdf).

### Qué merece la pena estudiar

**Reducir el coste de volver mañana.** El usuario no debe decidir entre miles de ejercicios ni completar un objetivo exigente para conservar el hábito.

Problema que resuelve: abandono por fricción o perfeccionismo.

Aplicación en Chess Coach: una actividad válida cuenta como día entrenado; el objetivo diario sigue mostrando si se completó el plan.

### Qué no copiar

- XP como centro de la experiencia.
- Gemas, corazones, ligas y farming.
- Castigar el error impidiendo continuar.
- Animaciones constantes o lenguaje infantil.
- Optimizar sesiones para mantener una racha sin aprendizaje.

## 1.6 Brilliant

### Mecanismos relevantes

- Learn by doing mediante problemas interactivos.
- Learning Paths con orden recomendado y checkpoints.
- Feedback inmediato que guía sin entregar necesariamente la respuesta.
- Sesiones breves y progresivas.
- Streak mantenida con 3 problemas o una lección.
- Streak Charges automáticos para no castigar en exceso una ausencia.
- Tutor Koji contextual dentro de la actividad.
- Distinción explícita entre actividad, accuracy, ayuda solicitada y skills practicadas.

Fuentes oficiales: [Learning Paths](https://brilliant.org/help/features/what-are-learning-paths/), [Streak](https://brilliant.org/help/features/what-is-a-streak/), [Streak Charge](https://brilliant.org/help/features/what-is-a-streak-charge/), [Learning by doing](https://brilliant.org/help/using-brilliant/), [Interpretación responsable del progreso](https://brilliant.org/help/features/parent-progress-dashboards-on-brilliant/).

### Qué merece la pena estudiar

**Feedback que convierte el error en aprendizaje y checkpoints dentro de una progresión.**

Problema que resuelve: completar actividades sin construir comprensión.

Aplicación en Chess Coach: Nova debe formular una pregunta o señalar un principio, y el cierre debe reconocer ayuda productiva además de aciertos autónomos.

### Qué no copiar

- XP y ligas semanales.
- Keys o límites artificiales de acceso.
- Tratar toda práctica como progreso equivalente.
- Un camino rígido que impida responder a partidas nuevas del usuario.

## Qué debería aprender Chess Coach de ellos

1. De Aimchess: conectar partidas propias con un plan especializado.
2. De Chessable: revisar en intervalos crecientes y registrar aprendizaje por unidad.
3. De Brilliant: enseñar mediante feedback y práctica guiada, no solo validar.
4. De Duolingo: separar hábito, objetivo y progreso.
5. De Lichess: convertir fortalezas/debilidades en acceso directo a práctica.
6. De Chess.com: mantener formatos distintos para intenciones distintas.

## Qué debería hacer Chess Coach de forma diferente

1. Explicar por qué aparece cada bloque de entrenamiento.
2. Usar posiciones propias sin asumir que todas son pedagógicas.
3. Medir ideas transferibles y su aparición posterior en partidas.
4. Tratar las ayudas como aprendizaje productivo, no como moneda o castigo.
5. Mantener una experiencia adulta sin rankings, farming o recompensas vacías.
6. Hacer que Nova demuestre memoria mediante hechos verificables.

---

# 2. Taxonomía pedagógica v1

## Decisión

> Esta sería la taxonomía v1 que implementaría: 10 conceptos principales, con subcategorías opcionales solo para explicación y analítica.

Los Smart Tags actuales como `blunder_own`, `inaccuracy_own` o `mistake_own` no son conceptos pedagógicos. Son señales de severidad. Del mismo modo, `comeback` o `strong_finish` son resultados/patrones, no materias de entrenamiento.

## 2.1 Táctica y combinaciones

**Definición:** secuencias concretas que ganan material, producen una amenaza forzada o cambian decisivamente la evaluación mediante relaciones entre piezas.

**Incluye:** clavadas, dobles ataques, descubiertas, desviación, eliminación del defensor, sobrecarga, atracción y sacrificios calculables.

**No incluye:** desarrollar una pieza a una casilla mejor sin ganancia concreta; detectar que el rey está inseguro sin combinación disponible.

**Señales disponibles:** gran diferencia entre primera y segunda jugada, pérdida táctica de material, PV forzada, tags de error propio, cambios bruscos de evaluación.

**Flash:** sí, cuando una decisión demuestra el motivo.

**Scenario:** sí, cuando la combinación requiere 2-3 decisiones propias o cambia de motivo durante la secuencia.

**Subcategorías futuras:** ataque doble, clavada, eliminación del defensor, desviación.

## 2.2 Amenazas y defensa

**Definición:** reconocer la intención inmediata del rival y encontrar una respuesta que neutraliza, reduce o transforma la amenaza.

**Incluye:** evitar pérdida de material, parar una amenaza táctica, encontrar única defensa, crear contrajuego defensivo.

**No incluye:** defender una posición inferior durante muchas jugadas sin amenaza identificable; cualquier jugada tranquila en posición igualada.

**Señales disponibles:** `allowed_mate`, errores defensivos, respuesta única, caída tras ignorar amenaza, ejercicio `spot_threat`, scenario `defense`.

**Flash:** sí, para detección y respuesta inmediata.

**Scenario:** especialmente adecuado cuando hay que sostener la defensa frente a varias respuestas críticas.

**Subcategorías futuras:** profilaxis, defensa única, contrajuego.

## 2.3 Seguridad del rey y mate

**Definición:** evaluar exposición del rey, construir o evitar redes de mate y gestionar casillas de escape y defensores.

**Incluye:** mate omitido, mate permitido, apertura de líneas contra el rey, retirada de defensor, creación de luft.

**No incluye:** cualquier táctica que casualmente da jaque pero cuyo objetivo es solo ganar material.

**Señales disponibles:** `missed_mate`, `allowed_mate`, scores de mate, cambios de seguridad del rey, PV forzada.

**Flash:** mates o defensas cuyo primer movimiento concentra la idea, normalmente hasta mate en 3 decisiones del jugador.

**Scenario:** ataque o defensa de mate que exige continuar correctamente durante 2-3 decisiones.

**Subcategorías futuras:** red de mate, rey expuesto, defensa de mate.

## 2.4 Cálculo y candidatos

**Definición:** comparar varias continuaciones relevantes, visualizar respuestas y elegir una línea por sus consecuencias.

**Incluye:** posiciones con 2-4 candidatos plausibles, jugada intermedia, orden de jugadas, cálculo de una secuencia no puramente temática.

**No incluye:** cualquier error de Stockfish; posiciones con una respuesta visual inmediata y obvia.

**Señales disponibles:** diferencia moderada entre candidatos, PV de varias plies, tiempo, intentos, alternativas, historial de hints.

**Flash:** solo si se pide elegir entre candidatos y la decisión termina tras una comparación corta.

**Scenario:** formato preferente cuando el aprendizaje es mantener precisión a lo largo de varias decisiones.

**Subcategorías futuras:** candidate moves, orden de jugadas, visualización.

## 2.5 Conversión de ventajas

**Definición:** transformar una ventaja existente en una posición más fácil de ganar sin conceder contrajuego significativo.

**Incluye:** consolidar, mejorar la peor pieza, crear un peón pasado, cambiar hacia final favorable, impedir actividad rival.

**No incluye:** encontrar una táctica ganadora desde posición igualada; mover cualquier pieza manteniendo una ventaja de +14.

**Señales disponibles:** `lost_winning_position`, `converted_advantage`, caída desde evaluación ganadora, scenario `conversion`, historial de ventajas desperdiciadas.

**Flash:** cuando existe una decisión estratégica concreta y demostrable, como cambiar damas o impedir contrajuego.

**Scenario:** formato preferente para demostrar que la ventaja se conserva durante varias respuestas rivales.

**Subcategorías futuras:** consolidación, contrajuego, peón pasado.

## 2.6 Simplificación e intercambios

**Definición:** decidir qué cambios de piezas o transición de fase favorecen el objetivo de la posición.

**Incluye:** cambiar damas con rey expuesto rival si conviene, entrar en final ganado, evitar un cambio que elimina la iniciativa, devolver material para simplificar.

**No incluye:** cualquier captura forzada o cambio automático de piezas.

**Señales disponibles:** reducción material en PV, cambio de fase, evaluación antes/después de intercambios, relación con conversión y finales.

**Flash:** sí, para la decisión “cambiar o mantener”.

**Scenario:** sí, cuando la simplificación debe continuarse correctamente hasta una estructura objetivo.

**Subcategorías futuras:** cambio de damas, transición a final, devolución de material.

## 2.7 Actividad, coordinación e iniciativa

**Definición:** mejorar la función de las piezas, coordinar fuerzas y crear amenazas que obligan al rival a reaccionar.

**Incluye:** activar torre, mejorar la peor pieza, ocupar columna abierta, ganar tiempos, conservar iniciativa.

**No incluye:** desarrollar por principio una pieza en la apertura sin relación concreta con la posición.

**Señales disponibles:** movilidad antes/después, piezas sin desarrollar, ganancia/pérdida de iniciativa en PV, tags positivos, diferencia de actividad.

**Flash:** cuando una jugada concreta activa una pieza o mantiene iniciativa.

**Scenario:** preferente cuando hay que construir un plan o coordinar varias piezas.

**Subcategorías futuras:** peor pieza, columna abierta, iniciativa.

## 2.8 Apertura y desarrollo

**Definición:** completar desarrollo, luchar por el centro, asegurar el rey y evitar problemas recurrentes dentro de la fase inicial.

**Incluye:** retraso de desarrollo, rey en el centro, repetición de un error ECO, ruptura central prematura, pérdida de tiempos.

**No incluye:** cualquier diferencia con la primera línea de Stockfish en los primeros movimientos; memorizar una línea sin entender la idea.

**Señales disponibles:** `opening_issue`, ECO, ply <= 16, piezas desarrolladas, enroque, recurrencia por apertura.

**Flash:** para una decisión conceptual concreta en una posición recurrente.

**Scenario:** para practicar una línea corta o respuesta a desviación, no para reproducir repertorios largos en v1.

**Subcategorías futuras:** desarrollo, centro, seguridad del rey, desviación de repertorio.

## 2.9 Estructura de peones y decisiones posicionales

**Definición:** decisiones cuya consecuencia principal es estructural y duradera.

**Incluye:** ruptura de peones, peón aislado, mayoría, casilla débil, cadena, peón pasado, fijar debilidad.

**No incluye:** captura de peón puramente táctica o movimiento de peón sin consecuencia estructural relevante.

**Señales disponibles:** cambios estructurales entre FEN, peones pasados/aislados/doblados, PV estable, fase y tags contextuales futuros.

**Flash:** solo para rupturas o decisiones estructurales muy claras.

**Scenario:** preferente para planes que requieren varias decisiones.

**Subcategorías futuras:** ruptura, peón pasado, debilidad, mayoría.

## 2.10 Finales y técnica

**Definición:** aplicar principios y técnica cuando queda material reducido y el rey participa activamente.

**Incluye:** oposición, actividad del rey, finales de torres, promoción, cortar al rey, defensa de final inferior.

**No incluye:** cualquier error ocurrido tarde en la partida; una táctica de mate con mucho material solo porque ocurre en el ply 80.

**Señales disponibles:** `endgame_mistake`, material restante, actividad del rey, tablebase cuando sea aplicable, fase final, errores recurrentes tardíos.

**Flash:** para una técnica o decisión única.

**Scenario:** formato preferente para conversión o defensa técnica.

**Subcategorías futuras:** peones, torres, piezas menores, técnica defensiva.

## 2.11 Reglas transversales

- `Táctica`, `Amenazas` y `Cálculo` pueden coexistir, pero una oportunidad tendrá un concepto principal.
- `Severidad`, `fase`, `resultado` y `fuente` serán atributos, no conceptos.
- Máximo inicial: un concepto principal y dos secundarios.
- Si no se puede explicar el concepto con una frase concreta, la oportunidad no se publica.
- La clasificación debe almacenar confidence y evidence, no solo una etiqueta.

---

# 3. Modelo operativo de Training Opportunity

## 3.1 Propósito

Una `TrainingOpportunity` representa una posición válida, vigente y potencialmente pedagógica. Todavía no es necesariamente un ejercicio publicado ni una actividad seleccionada para hoy.

## 3.2 Filtros duros

Una posición queda invalidada si cumple cualquiera de estas condiciones:

1. El análisis no es el vigente para la partida.
2. FEN, lado al mover, jugada o línea no son legales/reconstruibles.
3. La evaluación de Stockfish está incompleta o no cumple mínimos de confianza.
4. No existe concepto principal identificable con confidence >= 0,60.
5. Es un duplicado exacto de una oportunidad canónica con igual concepto y objetivo.
6. Existen más de 4 alternativas prácticamente equivalentes y no puede definirse un objetivo evaluable.
7. La solución exige más de 3 decisiones propias para Flash/Scenario v1 o una profundidad desproporcionada sin valor conceptual.
8. Es mate en más de 6 movimientos del jugador, salvo contenido específico futuro de cálculo avanzado.
9. Es una posición temprana ordinaria sin error conceptual, recurrencia ni desviación relevante.
10. La jugada real está aceptada por el análisis vigente y no existe otro propósito pedagógico explícito.
11. La oportunidad del rival no aporta un ejemplo vinculado a una debilidad, fortaleza o concepto relevante para el usuario.
12. La posición tiene ventaja extrema y el único objetivo es genérico, como “mantén la ventaja”.

Los descartes deben conservar razón y versión del evaluador para poder recalibrar sin perder trazabilidad.

## 3.3 Scoring v1

Después de filtros duros:

```text
pedagogical_score =
    relevance             0..25
  + concept_confidence    0..15
  + decision_clarity      0..15
  + pedagogical_value     0..15
  + recurrence            0..10
  + adaptive_fit          0..10
  + novelty               0..5
  + format_suitability    0..5
  - ambiguity_penalty     0..20
  - redundancy_penalty    0..25
  - complexity_penalty    0..15
  - overexposure_penalty  0..15
```

El resultado se limita a `0..100`.

### Relevance: 0..25

- 0-5: ejemplo del rival sin relación fuerte.
- 6-12: posición propia aislada o concepto secundario.
- 13-19: error propio reciente o relacionado con Coach Decision.
- 20-25: error propio reciente, recurrente y alineado con foco/mastery débil.

La severidad aporta como máximo 5 puntos dentro de relevance. Un blunder no domina por sí solo el score.

### Concept confidence: 0..15

- 0-5: concepto inferido débilmente.
- 6-10: varias señales coherentes.
- 11-15: patrón claro, línea y datos explican la misma idea.

### Decision clarity: 0..15

- 0-5: varias decisiones válidas con objetivos distintos.
- 6-10: 2-3 alternativas aceptables, pero una idea principal clara.
- 11-15: decisión claramente evaluable y respuesta rival coherente.

### Pedagogical value: 0..15

- Transferibilidad del principio.
- Posibilidad de explicar por qué.
- Posibilidad de dar pistas progresivas sin revelar la solución.
- Capacidad de comprobar el objetivo.

### Recurrence: 0..10

- 0: caso aislado.
- 3: dos señales relacionadas.
- 6: tres apariciones en ventana relevante.
- 8: cuatro o más.
- 10: recurrente y todavía presente tras entrenamiento.

### Adaptive fit: 0..10

Compara dificultad estimada con rendimiento del jugador. Puntúa máximo cuando la probabilidad esperada de éxito autónomo está aproximadamente entre 55 % y 80 %.

### Novelty: 0..5

Máximo si el patrón es relevante pero la geometría/estructura es nueva. Cero si es casi idéntico a contenido reciente.

### Format suitability: 0..5

Mide si Flash o Scenario puede demostrar el objetivo dentro de sus límites actuales.

### Penalizaciones

- Ambigüedad: alternativas numerosas o explicación dependiente de preferencias sutiles.
- Redundancia: similitud alta con una oportunidad mejor.
- Complejidad: línea demasiado profunda o objetivo poco visible.
- Sobreexposición: concepto o posición practicados demasiado recientemente.

## 3.4 Umbrales

- `>= 65`: publicable y elegible para selección.
- `50-64`: reserva; puede entrar si falta contenido del concepto o para evaluación manual.
- `< 50`: no publicar.
- `hard reject`: no puntuar; queda invalidada.

## 3.5 Prioridad de selección

El `pedagogical_score` mide calidad intrínseca para este usuario. La prioridad de hoy se calcula aparte:

```text
selection_priority =
    pedagogical_score
  + due_for_review
  + coach_focus_alignment
  + recent_game_bonus
  + recovery_bonus
  - fatigue_penalty
  - same_session_similarity
```

Así una gran oportunidad no tiene que aparecer inmediatamente si acaba de practicarse.

## 3.6 Ejemplos de producción

### Error propio grave y recurrente

Caso: omisión grave propia, patrón de amenaza repetido, análisis vigente, defensa clara y mastery débil.

Tratamiento: supera filtros; relevance 22-25, recurrence 6-10, clarity alta. Publicable. Flash si una defensa concentra la idea; Scenario si debe sostenerse durante varias respuestas.

### Jugada correcta del rival

Caso: el rival jugó la primera línea con CPL 0-10.

Tratamiento: por defecto no crear. Solo conservar si demuestra un concepto que el usuario falla, no está duplicada y puede explicarse como ejemplo. Relevance limitada a 8 salvo relación fuerte con Coach Decision. Baja prioridad.

### Posición duplicada

Caso: misma FEN normalizada, lado al mover, concepto y solución en varias partidas.

Tratamiento: una oportunidad canónica con múltiples `sources`. Conservar recurrencia y partidas de origen, no múltiples ejercicios.

### Mate en 16

Tratamiento: hard reject en Training v1. Puede permanecer en Analysis Board o futuro cálculo avanzado. No es Flash ni Scenario actual.

### Conversión desde +14

Tratamiento: descartar si el objetivo es solo “conserva la ventaja”. Conservar únicamente si existe una decisión conceptual clara, como simplificación única o prevención de ahogado/contrajuego, y la línea valida ese objetivo.

### Defensa con evaluación inicial no negativa

Tratamiento: la evaluación no invalida por sí sola. Conservar si existe amenaza concreta o defensa única. Descartar si es una posición igualada sin peligro identificable y el objetivo es genérico.

---

# 4. Elección entre Flash y Scenario

## 4.1 Flash

Una oportunidad se convierte en Flash cuando:

- la idea se demuestra en una decisión principal del usuario;
- la respuesta automática sirve para confirmar, no para abrir un nuevo problema;
- el objetivo puede evaluarse inmediatamente;
- existe una jugada o pequeño conjunto de alternativas aceptables;
- las pistas progresivas pueden dirigir atención -> pieza -> idea;
- no es necesario demostrar un plan durante varias posiciones.

Ejemplos:

- detectar una clavada;
- evitar mate con una defensa concreta;
- cambiar damas para entrar en final ganado;
- encontrar una ruptura de peones inmediata;
- activar una torre en columna abierta cuando esa es la decisión clave.

## 4.2 Scenario

Una oportunidad se convierte en Scenario cuando:

- el objetivo requiere 2-3 decisiones significativas del usuario;
- existe al menos una respuesta crítica del rival;
- acertar la primera jugada no demuestra aprendizaje suficiente;
- hay que mantener ventaja, defensa, iniciativa o plan;
- la idea cambia de fase o exige adaptación;
- la aceptación puede basarse en objetivo y calidad, no solo línea exacta.

Ejemplos:

- simplificar y convertir un final durante tres decisiones;
- defender una amenaza, neutralizar contrajuego y reorganizar una pieza;
- mantener un ataque frente a la mejor defensa;
- activar el rey y crear un peón pasado;
- ejecutar una secuencia de mate de varias decisiones.

## 4.3 Casos a descartar

- Si una decisión es demasiado ambigua para Flash y demasiado abierta para validar como Scenario, no debe entrenarse todavía.
- Si Stockfish necesita profundidad alta pero no puede formularse un objetivo humano, se mantiene en Review/Analysis.
- Si solo se exige recordar una línea larga, no encaja en Training v1.

## 4.4 Regla resumida

```text
Una decisión demuestra la idea -> Flash
Varias decisiones sostienen la idea -> Scenario
La idea no puede demostrarse con claridad -> No publicar
```

---

# 5. Composición inicial del entrenamiento diario

En UI se recomienda hablar de `entrenamiento de hoy` o `plan de hoy`. `Session` permanece como concepto interno.

## 5.1 Política general

- 60-70 % del plan se relaciona con el primary focus.
- 20-30 % consolida conceptos secundarios o mastery pendiente.
- 10-20 % aporta variedad o mantenimiento.
- 40 % aproximadamente será contenido nuevo.
- 60 % aproximadamente será repaso, recuperación o consolidación.
- Máximo dos actividades casi idénticas en un mismo plan.
- Si existe un fallo reciente relevante, incluir uno; no inundar toda la sesión con fallos.
- Si existe una partida analizada en las últimas 48 horas, incluir como máximo una oportunidad suya de alta calidad.

## 5.2 Entrenamiento corto - unos 5 minutos

**3 actividades.**

- 2 Flash.
- 1 Scenario corto si existe uno adecuado; si no, 1 Flash adicional.
- 1 actividad nueva del foco.
- 1 repaso vencido o fallo anterior.
- 1 consolidación o posición reciente.
- 2 de 3 relacionadas con primary focus.

Uso: día con poco tiempo, recuperación de hábito o usuario con fatiga alta.

## 5.3 Entrenamiento estándar - 8 a 10 minutos

**5 actividades.**

- 3 Flash.
- 2 Scenarios, o 1 Scenario y 1 Flash si la dificultad es alta.
- 2 nuevas.
- 2 repasos/consolidaciones.
- 1 procedente de partida reciente o recuperación de fallo.
- 3 relacionadas con primary focus.
- 1 con secondary focus.
- 1 de mantenimiento/variedad.

Esta es la composición por defecto.

## 5.4 Entrenamiento largo opcional - unos 15 minutos

**8 actividades.**

- 5 Flash.
- 3 Scenarios, reducidos a 2 si son complejos.
- 3 nuevas.
- 3 repasos/consolidaciones.
- 1-2 recuperaciones de fallos.
- 1 actividad de partida reciente.
- 5 relacionadas con primary focus.
- 2 con secondary focus.
- 1 de mantenimiento.

## 5.5 Adaptación

- Dos fallos consecutivos con alta dificultad: bajar un nivel o sustituir siguiente Scenario por Flash guiado.
- Tres aciertos autónomos rápidos: introducir una actividad ligeramente más difícil.
- Pista nivel 2-3: programar repaso cercano aunque el resultado final sea correcto.
- Concepto `Estable` con performance reciente normal: solo mantenimiento ocasional.
- Concepto `Estable` con errores recientes: introducir repaso sin borrar mastery.

---

# 6. Modelo inicial de mastery

Mastery pertenece al **concepto**, no al volumen total de actividad.

## 6.1 Iniciando

### Significado

- Menos de 3 oportunidades válidas, o práctica en una única fecha.
- No hay muestra suficiente para estimar retención.
- Puede existir un problema detectado en partidas, pero no evidencia de aprendizaje.

### UI

`Iniciando · muestra inicial`

### Avance

Completar oportunidades válidas en al menos dos fechas y demostrar algún acierto, aunque use pistas.

## 6.2 Aprendiendo

### Significado

- Al menos 3 oportunidades en 2 fechas distintas.
- Existen aciertos, pero la resolución autónoma es inferior a 60 % o todavía depende frecuentemente de pistas/intentos.
- La dificultad puede ser básica o mixta.

### UI

`Aprendiendo · todavía necesita apoyo`

### Avance

- Mejorar autonomía.
- Resolver contenido variado del concepto.
- Superar al menos una revisión diferida.

## 6.3 Consolidando

### Significado

- Al menos 6 oportunidades en 3 fechas.
- Ventana mínima de 7 días.
- Resolución autónoma >= 65 % en muestra reciente ajustada por dificultad.
- Al menos 2 aciertos diferidos después de 3 o más días.
- No depende exclusivamente de ejercicios fáciles o duplicados.

### UI

`Consolidando · responde bien en contextos variados`

### Avance

Demostrar retención, variedad y estabilidad durante varias semanas.

## 6.4 Estable

### Significado

- Al menos 10 oportunidades válidas.
- Al menos 5 fechas distintas.
- Ventana mínima de 21 días.
- Resolución autónoma >= 75 % ajustada por dificultad.
- Al menos 2 revisiones superadas después de 7 días.
- Sin dependencia recurrente de pista nivel 2-3.
- Transferencia positiva en partidas cuando exista muestra; si no existe, mostrar `Estable en entrenamiento`.

### UI

`Estable · mantenido en varias semanas`

Estable no significa dominado para siempre. Significa que el sistema puede reducir frecuencia.

## 6.5 Avance y retroceso

### Hace avanzar

- Acertar en fechas diferentes.
- Resolver sin pista.
- Reducir intentos respecto a exposiciones anteriores.
- Resolver dificultad adecuada o creciente.
- Superar revisión diferida.
- Mostrar transferencia en partida real.

### Hace retroceder

- Fallar varias revisiones diferidas, no un único ejercicio.
- Volver a depender de pistas de nivel alto de forma recurrente.
- Reaparición repetida y grave del patrón en partidas.

### Inactividad

- 30 días sin práctica: no bajar estado, marcar `revisión pendiente`.
- 60 días: reducir confidence.
- 90 días: mantener historial, pero exigir una comprobación antes de seguir presentándolo como estable.

## 6.6 Ejemplos

- Tres Flash fáciles resueltos el mismo día: `Iniciando`.
- Cinco ejercicios en tres días, varios con pista: `Aprendiendo`.
- Ocho posiciones variadas, dos repasos a una semana y 70 % autónomo: `Consolidando`.
- Doce posiciones durante un mes, repasos diferidos y 80 % autónomo: `Estable`.

---

# 7. Mastery frente a performance reciente

## 7.1 Mastery

Responde:

> ¿Qué evidencia acumulada existe de que el jugador ha aprendido este concepto?

Es lento, longitudinal y resistente a un mal día.

## 7.2 Performance reciente

Responde:

> ¿Cómo le está yendo con este concepto últimamente?

Usa últimas 5 oportunidades relevantes o últimos 30 días y puede cambiar rápidamente.

Estados recomendados:

- `En forma`.
- `Normal`.
- `Atención`.
- `Repaso prioritario`.

## 7.3 Convivencia

Ejemplo:

```text
Conversión de ventajas
Mastery: Estable
Forma reciente: Atención · 2 errores en partidas recientes
Acción: repaso temporal
```

Dos errores recientes aumentan prioridad, pero no destruyen meses de evidencia. Solo una tendencia persistente provoca descenso de mastery.

---

# 8. Hipótesis priorizadas sobre falta de engagement

## H1 - Calidad y relevancia insuficiente

**Probabilidad:** Muy alta  
**Impacto:** Muy alto  
**Evidencia:** 98,7 % del inventario no usado; 64,7 % procede del rival; duplicados, contenido obsoleto y títulos genéricos.  
**Cómo comprobarla:** introducir selección v2 para una parte de entrenamientos y comparar completitud, abandono, feedback y repetición voluntaria.  

## H2 - Falta de progresión y consolidación visibles

**Probabilidad:** Muy alta  
**Impacto:** Muy alto  
**Evidencia:** existen score, objetivos y ADN, pero no un camino claro por conceptos ni una definición de “aprendido”.  
**Cómo comprobarla:** mostrar mastery y cierre durante varias semanas; medir retorno y revisiones diferidas.

## H3 - Falta de contexto: por qué esto y por qué ahora

**Probabilidad:** Alta  
**Impacto:** Alto  
**Evidencia:** razones genéricas como “Patrón pendiente detectado”; tres focos potencialmente contradictorios.  
**Cómo comprobarla:** Coach Decision único y razones concretas; medir inicio desde recomendación y finalización.

## H4 - Dificultad mal calibrada

**Probabilidad:** Alta  
**Impacto:** Alto  
**Evidencia:** cero runs fáciles; casi todo lo entrenado es hard/critical, pero con 83-96 % de resolución final y 40-45 % de pistas.  
**Cómo comprobarla:** dificultad observada y adaptación intraentrenamiento; medir autonomía por banda.

## H5 - Cierre y recompensa inmediata débiles

**Probabilidad:** Alta  
**Impacto:** Medio-alto  
**Evidencia:** el producto registra resultados, pero no sintetiza qué se consolidó ni qué ocurrirá después.  
**Cómo comprobarla:** cierre compacto; medir completitud, siguiente inicio y valoración cualitativa.

## H6 - Nova todavía no es suficientemente creíble

**Probabilidad:** Media-alta  
**Impacto:** Medio-alto  
**Evidencia:** 43 mensajes con 22 textos; repetición, errores de copy y ausencia de memoria estructurada.  
**Cómo comprobarla:** mensajes basados en comparaciones reales y estados reactivos; evaluar utilidad percibida.

## H7 - Duración y fricción

**Probabilidad:** Media  
**Impacto:** Medio  
**Evidencia:** UX mobile ha mejorado, pero el usuario todavía puede no saber cuánto falta o entrar en contenido demasiado exigente.  
**Cómo comprobarla:** ofrecer duraciones explícitas y medir abandono por posición.

## H8 - Variedad insuficiente

**Probabilidad:** Media  
**Impacto:** Medio  
**Evidencia:** títulos y formatos se repiten, aunque Scenarios ya aporta una base distinta.  
**Cómo comprobarla:** composición mixta controlada, no más tipos indiscriminados.

## H9 - Gamificación insuficiente

**Probabilidad:** Baja-media  
**Impacto:** Medio  
**Evidencia:** ya existen racha, objetivos, score y Nova; el problema persiste.  
**Cómo comprobarla:** primero mejorar calidad, contexto y cierre. No añadir XP como tratamiento inicial.

## Las tres cosas que arreglaría primero

1. Calidad y relevancia de la selección.
2. Progresión/mastery y repetición con sentido.
3. Coach Decision, contexto y cierre del entrenamiento.

---

# 9. Gamification v1 mínima

| Mecanismo | Decisión | Motivo |
|---|---|---|
| Racha / núcleo de Nova | Implementar/refinar ahora | Refuerza hábito con una señal visual ya integrada. Una actividad válida mantiene la racha. |
| Objetivo diario | Implementar/refinar ahora | Da una meta alcanzable distinta de la racha. |
| Objetivo semanal | Implementar/refinar ahora | Permite variedad y consolidación, no solo cantidad diaria. |
| Mastery | Implementar ahora | Hace visible aprendizaje antes de que cambie el Elo. |
| Progreso del entrenamiento | Implementar ahora | Reduce incertidumbre y recompensa cierre. |
| Feedback/cierre de Nova | Implementar ahora | Convierte datos en significado inmediato. |
| Achievements | Más adelante | Solo hitos raros y pedagógicos; no necesarios para validar el loop. |
| Protección de racha | Más adelante | Puede introducirse como gracia automática, nunca como compra. |
| XP | No implementar | Mide actividad y se confunde fácilmente con habilidad. |
| Niveles | No implementar | Riesgo de parecer Elo o progreso ficticio. |
| Hint economy | No implementar | Penaliza ayuda legítima y puede generar frustración. |
| Puntos/monedas | No implementar como recompensa | El índice actual puede mantenerse analítico, pero no convertirse en economía. |
| Ranking/leagues | No implementar | Incentiva farming y no aporta valor con un producto personal. |

## Qué produce sensación de progreso sin XP

- Mastery cambia de estado.
- Autonomía mejora: menos pistas o intentos.
- Repasos diferidos se superan.
- Nova reconoce una comparación real.
- El objetivo diario y semanal avanzan.
- Una partida nueva confirma transferencia.

---

# 10. Cierre del entrenamiento

## 10.1 Información necesaria

Un único panel compacto:

1. Título: `Entrenamiento completado`.
2. Concepto principal entrenado.
3. Resultado: actividades completadas y autonomía.
4. Cambio de mastery o evidencia añadida.
5. Próxima revisión.
6. Objetivo diario y racha en una línea secundaria.
7. Mensaje específico de Nova.

No mostrar ACPL, inventario total, todas las estadísticas ni puntos genéricos.

## 10.2 Ejemplo normal

```text
Entrenamiento completado

Amenazas y defensa
4 de 5 actividades · 3 sin ayuda

Mastery: Aprendiendo -> Consolidando
Volveremos a comprobar este patrón en 4 días.

Objetivo diario completado · Racha: 6 días

Nova:
“La última vez necesitaste identificar la pieza defensora.
Hoy resolviste dos posiciones sin pista.”
```

## 10.3 Sesión excelente

- Reconocer autonomía y dificultad apropiada.
- Evitar exageración.

Ejemplo Nova:

> Has resuelto las cinco posiciones sin ayuda, incluida la revisión de hace una semana. Este concepto pasa a Consolidando.

## 10.4 Sesión normal

- Reconocer progreso parcial.
- Explicar siguiente revisión.

Ejemplo Nova:

> Las amenazas directas han salido bien. En las defensas con varias respuestas todavía necesitas apoyo; volveremos a ellas en cuatro días.

## 10.5 Sesión difícil

- Recompensar persistencia y aprendizaje, no fingir éxito.
- Bajar dificultad o dividir concepto en la siguiente composición.

Ejemplo Nova:

> Hoy este bloque ha sido demasiado exigente. Ya sabemos que la dificultad estaba por encima de tu nivel actual. La próxima vez empezaremos con dos ejemplos más guiados.

Una sesión difícil puede completar actividad y racha, pero no necesariamente el objetivo de mastery.

---

# 11. Recomendación técnica para Nova reactiva

## 11.1 Comparativa

| Tecnología | Complejidad | Peso/rendimiento | Control de estados | Mantenimiento | Recomendación |
|---|---|---|---|---|---|
| CSS + sprite sheet | Baja | Muy bueno | Alto | Bajo | Sí |
| Animated WebP | Baja | Bueno | Bajo; animación cerrada | Medio | Solo para una celebración futura |
| SVG animado | Media | Excelente para vectores | Alto | Alto con Nova raster | No para personaje completo |
| Lottie | Media-alta | Bueno, añade runtime | Muy alto | Medio-alto | No en stack actual |
| Canvas | Alta | Bueno | Muy alto | Alto y peor accesibilidad | No |
| GIF | Baja | Peor peso/calidad | Muy bajo | Bajo | No |

## 11.2 Decisión

> Implementaría Nova v1 reactiva con CSS + sprite sheet, estados controlados por clases y JavaScript vanilla.

Arquitectura:

- Un componente Nova reutilizable.
- Atributo `data-nova-state`.
- Sprite/frame por estado.
- Transiciones CSS de opacidad y escala.
- Pulso del núcleo mediante CSS.
- Animaciones breves, no loops constantes salvo idle casi imperceptible.
- `prefers-reduced-motion` desactiva movimiento y mantiene cambio visual.
- Texto y significado no dependen de la animación.
- Assets locales y cacheados por service worker.

---

# 12. Estados reactivos de Nova

| Estado | Evento | Duración | Expresión | Núcleo | Animación |
|---|---|---:|---|---|---|
| `idle` | Espera o lectura | Persistente | Neutral/atenta | Pulso muy lento | Movimiento mínimo opcional |
| `thinking` | Validación o generación en curso | 200 ms hasta respuesta | Concentrada | Azul con pulso | Sí, breve y repetible |
| `correct` | Respuesta correcta | 900-1.400 ms | Positiva contenida | Verde, expansión única | Sí |
| `error` | Respuesta incorrecta | 700-1.000 ms | Seria/constructiva | Rojo, pulso único | Sí |
| `hint` | Usuario pide ayuda | 500-800 ms | Atenta/señalando | Naranja | Cambio visual basta |
| `explaining` | Se añade explicación | Mientras se lee | Didáctica | Azul estable | Sin loop necesario |
| `session_complete` | Cierre | 1.200-1.800 ms | Satisfacción | Verde con doble pulso | Sí, una vez |

Después de estados temporales, Nova vuelve a `idle` o `explaining` según contexto.

No se recomienda un estado por cada clasificación ajedrecística.

---

# 13. Timing de Nova

## Movimiento normal

```text
usuario mueve
-> 80-150 ms para confirmar interacción visual
-> thinking si la validación no terminó
-> resultado real
```

No añadir más de 150 ms artificiales si el resultado ya está disponible.

## Petición de pista

```text
click
-> 100-180 ms thinking/hint
-> transición de 250-350 ms
-> pista
```

## Respuesta correcta

```text
validación
-> 120-200 ms
-> correct durante 900-1.400 ms
-> feedback y controles siguientes
```

El feedback puede aparecer durante la animación; no bloquear navegación.

## Error

```text
validación
-> 120-180 ms
-> error durante 700-1.000 ms
-> pista/contexto disponible
```

## Inicio del entrenamiento

- Entrada de Nova: 350-500 ms.
- Mensaje visible inmediatamente.
- No bloquear botón de empezar más de 300 ms.

## Cierre

- Transición al panel: 350-500 ms.
- Estado `session_complete`: 1.200-1.800 ms una sola vez.

## Procesamiento real

Si Stockfish o servidor tardan:

- mostrar `thinking` después de 180-250 ms;
- mantenerlo hasta respuesta;
- no simular que Nova piensa después de tener el resultado;
- ofrecer estado de error técnico distinto de error ajedrecístico.

---

# 14. Memoria estructurada de Nova

## 14.1 Hechos que conservar

### Memoria de concepto

- concept_id.
- mastery anterior y actual.
- performance reciente.
- autonomy reciente.
- fecha de última práctica.
- próxima revisión.

### Memoria de comparación

- ejercicio/oportunidad canónica.
- intentos y pistas anteriores.
- resultado actual.
- delta verificable.

### Memoria de dificultad

- dificultad esperada.
- dificultad observada.
- señal de demasiado fácil/difícil.

### Memoria de partida

- concepto detectado.
- recurrencia.
- severidad.
- relación con oportunidad entrenada.
- evidencia posterior de transferencia.

### Memoria de decisión del Coach

- foco principal/secundario.
- evidencia.
- confidence.
- fecha y condición de reevaluación.

### Memoria de hábito

- último día activo.
- objetivo completado.
- racha.
- retorno tras ausencia.

## 14.2 Duración y relevancia

- Intentos y eventos: histórico permanente.
- Comparaciones de ejercicio: relevantes hasta que exista una exposición posterior; conservar historial.
- Performance reciente: ventana móvil de 30 días o 5 oportunidades.
- Coach Decision: vigente hasta reevaluación; después histórico.
- Próxima revisión: vigente hasta completarse o recalcularse.
- Hitos: permanentes, pero solo comunicar una vez salvo comparación futura.
- Mensajes generados: no constituyen memoria; la memoria son los hechos que los sustentan.

## 14.3 Mensajes posibles

Hecho:

```text
Conversión
anterior: 2 pistas, 4 intentos
hoy: 0 pistas, 1 intento
```

Mensaje:

> La última vez necesitaste dos pistas; hoy resolviste la misma idea sin ayuda.

Hecho:

```text
Amenazas
3 errores en 10 partidas antes del entrenamiento
0 errores en 4 oportunidades posteriores
confidence: inicial
```

Mensaje:

> Hay una señal positiva: todavía no has repetido este error en cuatro oportunidades. Necesitamos más partidas para confirmarlo.

---

# 15. Coach Decision único

## 15.1 Salida

```text
CoachDecision
- primary_focus
- secondary_focus|null
- evidence[]
- confidence
- reason
- session_objective
- created_at
- reassess_after_date
- reassess_after_games
- reassess_after_training_days
- algorithm_version
```

Home, Training, Nova y objetivos consumen la misma decisión. ADN no presenta otra recomendación competitiva; aporta evidencia longitudinal.

## 15.2 Inputs

- ADN: debilidad estructural y muestra larga.
- Forma reciente: urgencia temporal.
- Mastery: cuánto se ha aprendido.
- Recurrencia: frecuencia y persistencia.
- Confidence/sample size.
- Historial de entrenamiento.
- Partidas recientes.
- Calidad/disponibilidad de Training Opportunities.

## 15.3 Política de prioridad v1

```text
coach_priority =
    recent_severity       0..30
  + recurrence            0..25
  + mastery_gap           0..20
  + transfer_failure      0..15
  + trainability          0..10
  - low_confidence        0..20
  - recent_overexposure   0..15
  - stable_without_issue  0..20
```

### Reglas

1. La forma reciente decide urgencia; ADN aporta contexto de largo plazo.
2. Un concepto con baja confidence no puede ser primary focus salvo evento grave repetido.
3. Un concepto `Estable` no es primary salvo deterioro reciente confirmado.
4. Un concepto sin oportunidades de calidad no se selecciona hasta generar/revisar contenido.
5. El primary focus permanece al menos 3 días de entrenamiento, salvo nueva señal grave.
6. Reevaluar tras 3 días entrenados, 10 partidas nuevas o 7 días naturales, lo que ocurra primero.
7. Secondary focus solo aparece si tiene score cercano y aporta variedad.

## 15.4 Resolución del ejemplo

```text
ADN: Táctica débil a largo plazo
Forma reciente: Errores en finales
Mastery: Conversión casi consolidada
```

Decisión:

- Primary focus: Finales, si los errores son recientes, recurrentes y tienen oportunidades válidas.
- Secondary focus: Táctica como mantenimiento estructural.
- Conversión: repaso diferido ocasional; no foco principal.
- Evidencia visible: “3 errores de final en las últimas 8 partidas; táctica sigue siendo una debilidad histórica, pero no ha empeorado esta semana.”

---

# 16. Journey actual frente a journey objetivo

## 16.1 Home -> Training -> cierre -> siguiente día

| Paso | Comportamiento actual | Problema | Comportamiento objetivo |
|---|---|---|---|
| Home | Nova propone entrenamiento usando foco/reglas existentes | La evidencia es genérica y puede contradecir ADN | Mostrar Coach Decision único y una razón concreta |
| Inicio | Se entra en entrenamiento recomendado | Composición no expresa nuevo/repaso/consolidación | Presentar duración, concepto y mezcla sin sobreexplicar |
| Primer Flash | Se resuelve posición y se valida | No siempre se entiende qué idea se aprende | Mostrar objetivo conceptual y razón después de responder |
| Pista | Pista progresiva reduce quality | Ayuda medida, pero no conectada a mastery | Registrar ayuda productiva y ajustar próxima exposición |
| Siguiente actividad | Avance por lista preparada | Adaptación limitada | Ajustar dificultad/formato si hay señales claras |
| Scenario | Se juega contra mejor respuesta | Objetivo demasiado amplio en algunos casos | Validar un objetivo pedagógico concreto durante 2-3 decisiones |
| Última actividad | Finaliza internamente | Recompensa/cierre débil | Mostrar cierre compacto, mastery y próxima revisión |
| Día siguiente | Nueva propuesta | Continuidad poco evidente | Nova recuerda un hecho verificable y mezcla repaso/nuevo |

## 16.2 Review -> aprendizaje -> Training

| Paso | Actual | Problema | Objetivo |
|---|---|---|---|
| Review | Clasifica y explica jugadas | Error puede confundirse con ejercicio útil | Evaluar Training Opportunity en segundo plano |
| Momento crítico | Se muestra mejor jugada/variante | Explica posición, no siempre concepto | Mostrar concepto y valor pedagógico cuando exista |
| CTA | Puede llevar a entrenamiento/revisión | Relación con plan no siempre clara | “Añadir al plan” o “Nova lo incluirá” con razón |
| Training | Ejercicio derivado de partida | Puede estar duplicado/obsoleto | Usar oportunidad canónica vigente y asociar la partida origen |

---

# 17. Tres momentos de valor prioritarios

## 17.1 Patrón recurrente convertido en plan

**Mensaje:**

> Has permitido esta misma clase de amenaza en tres partidas recientes. Hoy empezaremos con dos posiciones relacionadas.

**Datos necesarios:** concepto, recurrencia, partidas, análisis vigente y Coach Decision.  
**Disponibilidad:** parcial; existen tags, partidas y análisis. Falta taxonomía/oportunidad canónica.  
**Complejidad:** M.  
**Impacto:** Muy alto.  
**Ubicación:** Home e inicio del entrenamiento.

## 17.2 Mejora de autonomía en una repetición

**Mensaje:**

> La última vez necesitaste dos pistas. Hoy resolviste esta idea sin ayuda.

**Datos necesarios:** intentos, pistas, oportunidad canónica, fechas.  
**Disponibilidad:** casi completa.  
**Complejidad:** S-M.  
**Impacto:** Alto e inmediato.  
**Ubicación:** feedback y cierre.

## 17.3 Transferencia a partida real

**Mensaje:**

> Después de entrenar conversión, mantuviste la ventaja en cuatro de tus últimas seis oportunidades.

**Datos necesarios:** concepto normalizado, ventanas antes/después, oportunidades de partida y confidence.  
**Disponibilidad:** parcial; existen evaluaciones y partidas, falta enlace por concepto.  
**Complejidad:** L.  
**Impacto:** Máximo a medio plazo.  
**Ubicación:** Home, ADN y cierre semanal.

Prioridad de implementación: 2 -> 1 -> 3. El segundo es el más rápido; el tercero demuestra mejor el valor diferencial.

---

# 18. Métricas de la primera iteración

## 18.1 Engagement

### 1. Días activos de entrenamiento por semana

**Definición:** días con al menos una actividad válida intentada.  
**Importancia:** hábito real, separado de objetivo completado.  
**Datos actuales:** intentos/runs por fecha.  
**Mejora esperada:** aumento sostenido sin caída de aprendizaje.

### 2. Tasa de finalización del entrenamiento

**Definición:** planes iniciados que llegan al cierre.  
**Importancia:** detecta fricción, duración y calidad.  
**Datos actuales:** sessions/items, corrigiendo lifecycle legado.  
**Mejora esperada:** +15-20 puntos porcentuales frente al baseline de selección v2.

### 3. Retorno a 48 horas

**Definición:** porcentaje de entrenamientos completados seguidos por actividad en los dos días siguientes.  
**Importancia:** mide deseo de volver sin exigir diario perfecto.  
**Datos actuales:** fechas de actividad.  
**Mejora esperada:** tendencia ascendente durante cuatro semanas.

### 4. Abandono por actividad

**Definición:** actividades abiertas que no se resuelven, fallan ni saltan correctamente.  
**Importancia:** localiza contenido o UX problemáticos.  
**Datos actuales:** solve runs y scenario runs; requiere normalización.  
**Mejora esperada:** descenso, especialmente en primeros dos elementos.

## 18.2 Aprendizaje

### 1. Tasa de resolución autónoma ajustada

**Definición:** resoluciones sin pista, segmentadas por concepto y dificultad observada.  
**Importancia:** mide autonomía, no solo éxito final.  
**Datos actuales:** hints, attempts, runs.  
**Mejora esperada:** aumento dentro del mismo concepto/dificultad.

### 2. Retención diferida

**Definición:** oportunidades resueltas después de al menos 3/7 días sin pista alta.  
**Importancia:** evidencia consolidación.  
**Datos actuales:** historial temporal; falta oportunidad canónica.  
**Mejora esperada:** >65 % en `Consolidando` tras calibración.

### 3. Recuperación tras fallo

**Definición:** ejercicios fallados que se resuelven en exposición posterior con menos ayuda.  
**Importancia:** convierte error en aprendizaje observable.  
**Datos actuales:** runs e hints.  
**Mejora esperada:** mayor tasa y menor ayuda en segundo/tercer intento diferido.

### 4. Recurrencia en partidas posteriores

**Definición:** frecuencia/severidad del concepto en oportunidades reales después de entrenarlo.  
**Importancia:** transferencia.  
**Datos actuales:** análisis y tags; requiere taxonomía y confidence.  
**Mejora esperada:** descenso con muestra suficiente, sin claims prematuros.

---

# 19. Propuesta de la siguiente release importante

## Nombre recomendado

**v1.6.0 - Training Quality & Coach Foundation**

El AI Coach basado en LLM debería posponerse. Un LLM sobre selección débil solo explicaría con más fluidez decisiones pedagógicas todavía inconsistentes.

## Must have

### Dominio y datos

- Taxonomía v1 de 10 conceptos.
- Training Opportunity con estado, score, razones y versión.
- Relación canónica entre oportunidad y múltiples fuentes.
- Vigencia respecto al análisis actual.
- Deduplicación exacta y similar básica.
- Dificultad estimada separada de severidad.
- Estado de mastery por concepto.
- Coach Decision único.

### Training selection

- Filtros duros y scoring v1.
- Política Flash vs Scenario.
- Composer corto/estándar/largo.
- Mezcla nuevo, repaso, foco, fallo y partida reciente.
- Repaso diferido inicial.
- Razón de selección persistida.

### UX

- Mostrar por qué se entrenará el foco.
- Indicar `Nuevo`, `Repaso` o `Consolidación` discretamente.
- Cierre compacto del entrenamiento.
- Mastery por concepto con muestra/confidence.

### Nova

- Mensajes basados en Coach Decision.
- Reconocimiento de mejora de autonomía.
- Copy v1 consistente.
- Una memoria estructurada mínima para comparaciones.

### Seguridad de rollout

- Selection v2 detrás de configuración/feature flag.
- Ejecución shadow que puntúe inventario sin cambiar todavía lo mostrado.
- Backfill por lotes e idempotente.
- Mantener histórico anterior como inactivo/auditable, no borrarlo.

## Should have

- Nova reactiva mediante sprite/CSS.
- Adaptación simple dentro del entrenamiento.
- Señal `performance reciente` junto a mastery.
- Primer “wow moment” de patrón recurrente.
- Panel interno de auditoría de oportunidades rechazadas/aceptadas.

## Not now

- LLM o API externa.
- Conversación libre con Nova.
- Transferencia con claims fuertes.
- XP, niveles, monedas o ranking.
- Economía de pistas.
- Taxonomía granular de 40 motivos.
- Repertorio largo con spaced repetition por movimiento.
- Similaridad avanzada mediante embeddings.
- Animaciones Lottie/Canvas.

---

# 20. Orden de implementación recomendado

```text
1. Taxonomía y mappings
-> 2. TrainingOpportunity + fuentes + vigencia
-> 3. Scorer y filtros en shadow mode
-> 4. Auditoría/backfill y deduplicación
-> 5. Dificultad observada + formato Flash/Scenario
-> 6. Mastery y revisión diferida
-> 7. Coach Decision único
-> 8. Composer del entrenamiento
-> 9. Cierre, mensajes y memoria de Nova
-> 10. Nova reactiva y métricas
-> 11. Activación gradual de selection v2
```

No construir Composer antes de tener oportunidades y mastery: obligaría a rehacer la selección.

No construir mensajes inteligentes antes de Coach Decision y memoria: produciría nuevas plantillas sin credibilidad.

---

# 21. Esfuerzo y riesgo

| Iniciativa | Esfuerzo | Riesgo | Observaciones |
|---|---|---|---|
| Taxonomía y mappings iniciales | M | Medio | Requiere validar detección con muestra real |
| Modelo TrainingOpportunity | L | Alto | Nuevo límite de dominio central |
| Filtros y scorer v1 | L | Alto | Experimental; necesita shadow mode |
| Backfill y deduplicación | XL | Alto | 9.122 ejercicios, análisis históricos y alternativas |
| Vigencia/reanálisis | M | Alto | Puede invalidar contenido activo |
| Dificultad observada | M | Medio | Poca muestra inicial por concepto |
| Routing Flash/Scenario | M | Medio | Riesgo de reducir demasiado Scenarios válidos |
| Mastery v1 | M | Medio | Umbrales necesitan calibración |
| Repetición diferida | M | Medio | Evitar exceso de repasos |
| Coach Decision | M | Medio | Debe sustituir tres prioridades existentes |
| Composer de entrenamiento | M | Medio | Dependiente de inventario de calidad |
| Cierre de entrenamiento | M | Bajo | UX y copy; alto impacto |
| Memoria estructurada mínima | M | Medio | Evitar duplicar eventos existentes |
| Nova CSS + sprites | S-M | Bajo | Respetar reduced motion y PWA cache |
| Métricas iniciales | S-M | Bajo | Reutiliza datos existentes |

## Riesgos especiales

- Una reducción agresiva puede dejar conceptos sin suficiente inventario.
- El backfill puede consumir tiempo de Stockfish si intenta reanalizar todo.
- Las alternativas históricas deben conservarse para no invalidar soluciones legítimas.
- Mastery no debe calcularse con intentos antiguos no comparables sin versionar.
- El rollout debe permitir comparar selection v1 y v2.
- Las migraciones deben ser MariaDB-compatible, idempotentes y ejecutables en hosting compartido.

---

# 22. Decisiones reales para Product Owner

## D1. ¿Reducir drásticamente el inventario activo?

**Opciones:** conservar 9.122 activos / depurar y canonizar.  
**Recomendación Codex:** sí, depurar aunque el inventario útil baje a 1.000-2.000 o menos.  
**Trade-off:** menor cifra visible, mucha más confianza y uso real. Las fuentes históricas se conservan, no se borran.

## D2. ¿Aprobar la taxonomía de 10 conceptos como v1?

**Opciones:** 10 conceptos / taxonomía más granular / mantener tags actuales.  
**Recomendación Codex:** aprobar los 10 conceptos y permitir subcategorías no obligatorias.  
**Trade-off:** algunas posiciones serán difíciles de clasificar al principio, pero el sistema será comprensible y calibrable.

## D3. ¿Sustituir la selección actual de inmediato?

**Opciones:** reemplazo directo / shadow mode y activación gradual.  
**Recomendación Codex:** shadow mode, comparar resultados y activar mediante configuración.  
**Trade-off:** entrega más lenta, riesgo de regresión mucho menor.

## D4. ¿Usar mastery en lugar de XP como progreso principal?

**Opciones:** mastery / XP / ambos.  
**Recomendación Codex:** mastery; mantener score actual como métrica secundaria, no recompensa.  
**Trade-off:** menos estímulo acumulativo inmediato, mucha mayor credibilidad pedagógica.

## D5. ¿Qué mantiene la racha?

**Opciones:** completar objetivo / una actividad válida / cualquier apertura de Training.  
**Recomendación Codex:** una actividad realmente intentada y finalizada; objetivo diario permanece separado.  
**Trade-off:** la racha es fácil de mantener, pero no confunde hábito con cumplimiento del plan.

## D6. ¿Incluir Nova reactiva en la release?

**Opciones:** must have / should have / posponer.  
**Recomendación Codex:** should have; implementar después de Coach Decision y cierre.  
**Trade-off:** aporta percepción y recompensa, pero no debe retrasar la calidad de selección.

## D7. ¿Posponer AI Coach/LLM?

**Opciones:** mantener v1.6 como AI Coach / convertirla en Training Quality Foundation.  
**Recomendación Codex:** posponer LLM y usar v1.6 para la base pedagógica.  
**Trade-off:** menos espectacular a corto plazo; evita automatizar explicaciones sobre decisiones débiles.

---

# 23. Respuestas finales a las preguntas centrales

## ¿Qué posiciones merece la pena entrenar?

Las vigentes, legales, conceptualmente claras, relevantes, no redundantes, de dificultad apropiada y con `pedagogical_score >= 65`.

## ¿Qué concepto entrena cada posición?

Uno de los 10 conceptos v1 como principal, con máximo dos secundarios y evidencia/confidence.

## ¿Por qué aparece ahora?

Por Coach Decision, recurrencia, repaso vencido, recuperación de fallo, partida reciente o necesidad de consolidación. La razón se persiste.

## ¿Flash o Scenario?

Flash si una decisión demuestra la idea. Scenario si varias decisiones deben sostenerla.

## ¿Qué significa mejorar?

Resolver con mayor autonomía, en fechas distintas, con dificultad adecuada y retención diferida; idealmente reducir el patrón en partidas.

## ¿Cómo se percibe esta semana?

Mastery, performance reciente, cierre, objetivo semanal y comparaciones reales de ayuda/retención.

## ¿Qué hace que quiera volver mañana?

Un plan breve que recuerda qué costó hoy, programa una revisión razonable y muestra avance significativo.

## ¿Cómo demuestra Nova que conoce al usuario?

Compara hechos: concepto, pistas, intentos, fechas, mastery y partidas posteriores.

## ¿Qué gamificación aporta valor?

Racha, objetivos, mastery, progreso del plan y feedback. XP, monedas, ranking y economía de pistas sobran en v1.

## ¿Qué construir exactamente?

Training Quality Foundation: taxonomía, oportunidades, selección, mastery, Coach Decision, Composer, cierre y memoria mínima de Nova, con rollout gradual.

