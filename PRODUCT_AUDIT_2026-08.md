# Chess Coach - Auditoría de producto, entrenamiento, engagement y Nova

**Fecha:** 22 de agosto de 2026  
**Baseline inspeccionado:** v1.5.2 release candidate  
**Stack:** PHP, MariaDB, JavaScript vanilla y Stockfish 18 en hosting compartido  
**Fuentes:** repositorio completo y export de producción `dbs15835200.sql`  
**Alcance:** auditoría de solo lectura. No se implementaron cambios de producto.

**Addendum de decisiones:** [PRODUCT_AUDIT_DECISIONS_2026-08.md](PRODUCT_AUDIT_DECISIONS_2026-08.md)

---

## 1. Resumen ejecutivo

Chess Coach ya contiene una base técnica y funcional muy superior a la de un simple visor de Stockfish:

- analiza partidas propias;
- interpreta evaluaciones desde la perspectiva del jugador;
- detecta patrones mediante Smart Tags;
- construye ADN, forma reciente y focos de entrenamiento;
- genera ejercicios Flash y escenarios multijugada;
- registra intentos, pistas, tiempos y calidad;
- prepara entrenamientos mediante Nova;
- ofrece Review, Openings Lab y Analysis Board.

Sin embargo, el principal cuello de botella ya no es técnico ni de volumen. Es **la calidad de la decisión pedagógica**.

El sistema detecta miles de posiciones potencialmente entrenables, pero todavía no distingue suficientemente bien entre:

1. una anomalía detectada durante el análisis;
2. una posición válida para practicar;
3. un ejercicio útil para este jugador ahora;
4. una experiencia que consolida una habilidad transferible.

La conclusión principal es:

> Chess Coach personaliza actualmente por procedencia y por etiquetas, pero aún no garantiza que cada ejercicio tenga valor pedagógico, dificultad apropiada y un lugar claro dentro de una progresión.

La siguiente evolución debería priorizar la calidad y selección del entrenamiento antes que añadir más inventario, más métricas o más gamificación.

---

## 2. Metodología y límites

### 2.1 Fuentes inspeccionadas

- Código PHP, JavaScript, CSS y SQL del repositorio.
- Documentación del proyecto y roadmap.
- Modelos y servicios de análisis, Training, Scenarios, Player DNA, progreso y Nova.
- Export completo de la base de datos de producción.
- Datos reales del único usuario actual.

### 2.2 Qué se midió

- Inventario de partidas, análisis, tags y ejercicios.
- Procedencia, clasificación, dificultad y uso de ejercicios.
- Duplicados exactos y ejercicios asociados a análisis antiguos.
- Cambios de `bestmove` tras enriquecimiento con Stockfish.
- Intentos, pistas, tiempos y resoluciones.
- Sesiones internas, planes, escenarios y mensajes de Nova.
- ADN, recomendaciones, progreso y revisiones completadas.

### 2.3 Limitaciones

- Solo existe un usuario. Los resultados describen muy bien este producto en uso real, pero no prueban comportamiento poblacional.
- Parte del histórico fue generado por versiones anteriores del sistema. Algunas tasas mezclan modelos antiguos y modernos.
- `training_attempts` registra intentos individuales, por lo que su porcentaje de filas resueltas no equivale a tasa final de ejercicios resueltos.
- Solo hay una muestra reducida de entrenamientos estructurados y escenarios ejecutados.
- No existe un corpus humano etiquetado que permita medir automáticamente la calidad pedagógica de un ejercicio.

Las conclusiones se clasifican implícitamente como:

- **Evidencia:** observable directamente en código o base de datos.
- **Inferencia:** interpretación razonable de varias señales.
- **Recomendación:** propuesta de producto que requiere aprobación.

---

## 3. Estado actual del producto

### 3.1 Experiencia ofrecida hoy

El journey principal es:

```text
partida externa
-> importación
-> análisis Stockfish
-> clasificación y Smart Tags
-> Review
-> ADN / forma reciente / foco
-> generación de ejercicios
-> entrenamiento Flash o Scenario
-> intentos, pistas y feedback de Nova
-> progreso y repetición
```

### 3.2 Fortalezas

- Posiciones procedentes de partidas reales del jugador.
- Pipeline Stockfish robusto y trazable.
- Métricas normalizadas desde la perspectiva del jugador.
- Review y Analysis Board conectan análisis y exploración.
- Captura de intentos, pistas, duración y calidad.
- Scenarios permite secuencias multijugada con respuesta óptima del rival.
- ADN utiliza varias dimensiones y niveles de confianza.
- Nova ya tiene estados visuales y mensajes deterministas basados en datos.
- UX mobile-first significativamente avanzada.

### 3.3 Debilidades y contradicciones

- Se genera mucho más contenido del que puede consumirse.
- La gravedad del error se usa como aproximación de dificultad.
- Un error se transforma con demasiada facilidad en ejercicio.
- Los conceptos pedagógicos son demasiado amplios o implícitos.
- El sistema puede mantener ejercicios obsoletos tras reanalizar una partida.
- ADN, recomendación principal y Nova pueden elegir focos distintos.
- El progreso agregado no explica qué habilidad se ha aprendido.
- Nova comunica datos, pero todavía no demuestra memoria o evolución personal.
- La experiencia recompensa completar, pero explica poco qué se ha consolidado.

### 3.4 Deuda conceptual

La principal deuda no es una tabla o una clase. Es la ausencia de una entidad conceptual entre análisis y ejercicio:

> Algo detectado en una partida no debería convertirse directamente en entrenamiento sin evaluar relevancia, recurrencia, dificultad, novedad y valor pedagógico.

---

## 4. Radiografía de producción

| Dato | Resultado |
|---|---:|
| Partidas | 174 |
| Registros de análisis | 231 |
| Jugadas analizadas | 13.396 |
| Tags de partida | 565 |
| Tags de jugada | 2.185 |
| Ejercicios activos | 9.122 |
| Ejercicios utilizados alguna vez | 118 |
| Intentos históricos | 378 |
| Resoluciones modernas | 65 |
| Escenarios | 297 |
| Ejecuciones de escenarios | 5 |
| Sesiones internas | 19 |
| Sesiones estructuradas modernas | 3 |
| Mensajes persistidos de Nova | 43 |
| Objetivos de plan | 149 |
| Revisiones registradas | 66 |
| Revisiones completadas | 42 |
| Snapshots de ADN | 44 |

### 4.1 Resultado de partidas

- Victorias: 79.
- Derrotas: 89.
- Tablas: 6.

### 4.2 Uso real del inventario

- 9.004 ejercicios nunca se han intentado.
- Solo 118 ejercicios distintos se han utilizado.
- El **98,7 %** del inventario permanece sin usar.

Esto demuestra que generar más ejercicios no es actualmente una necesidad de producto.

---

## 5. Auditoría profunda de Training

### 5.1 Cómo se genera hoy un ejercicio

El flujo reconstruido es:

```text
game_move_analysis
-> clasificación / pérdida de evaluación / tags
-> comprobación de elegibilidad
-> asignación de tipo, dificultad, prioridad y contenido
-> training_exercises
-> selección por filtros, foco, prioridad y estado
-> intento y validación contra UCI / Stockfish
```

Para jugadas propias se utilizan clasificación, pérdida y tags. Para jugadas del rival el criterio es considerablemente más permisivo.

### 5.2 Procedencia

| Procedencia | Ejercicios | Porcentaje |
|---|---:|---:|
| Rival | 5.898 | 64,7 % |
| Usuario | 3.224 | 35,3 % |

El inventario está dominado por posiciones del adversario.

### 5.3 Tipos

| Tipo | Cantidad |
|---|---:|
| `other` / Aprende de la jugada rival | 5.898 |
| Encuentra la mejor jugada | 2.077 |
| Encuentra el recurso | 304 |
| Defiende la posición | 225 |
| Evita el error | 187 |
| Detecta la amenaza | 146 |
| Encuentra el mate | 143 |
| Convierte la ventaja | 142 |

Los dos títulos más genéricos representan 7.975 registros. Describen una acción, pero no una habilidad transferible.

### 5.4 Señales de bajo valor

- 2.152 ejercicios del rival reproducen exactamente la jugada principal del motor.
- 2.489 jugadas del rival pierden como máximo 10 centipawns.
- 1.439 ejercicios propios están clasificados como `ok`, tienen pérdida inferior a 70 y carecen de tags.
- 7.337 ejercicios no tienen ninguna etiqueta asociada.
- Hay mates anunciados de hasta 16 movimientos, difíciles de justificar como Flash.

Una jugada correcta del rival puede ser un buen ejemplo, pero no debería generarse automáticamente sin concepto, novedad o relación con una debilidad concreta.

### 5.5 Duplicados

- 478 grupos contienen la misma posición normalizada.
- 2.691 filas pertenecen a esos grupos.
- Existen aproximadamente 2.213 filas redundantes adicionales.
- 389 grupos duplicados comparten también la misma solución.

Ejemplo real: la posición inicial aparece en ejercicios con IDs 1, 52, 85, 156, 198 y 237, entre otros.

También se repiten posiciones tempranas después de `1.d4`, con soluciones y alternativas equivalentes.

### 5.6 Reanálisis y vigencia

- 1.440 ejercicios están asociados a un análisis que ya no es el último de su partida.
- Stockfish cambió la solución principal en 1.178 ejercicios enriquecidos.
- 958 conservaron alguna alternativa aceptada.
- 220 discrepancias no disponen de alternativa.
- En 65 ejercicios propios, la jugada realizada por el usuario terminó aceptándose tras el enriquecimiento.

Esto representa un riesgo directo de credibilidad. Un ejercicio debe estar vinculado a una versión de análisis vigente o pasar por una revalidación explícita.

### 5.7 Error no equivale a ejercicio

Un error real puede ser mal material de entrenamiento si:

- es trivial;
- requiere cálculo desproporcionado;
- depende de una peculiaridad irrepetible;
- admite muchas jugadas razonables;
- la ventaja ya es abrumadora;
- no existe una idea explicable;
- es redundante con posiciones anteriores;
- no está relacionado con una debilidad recurrente;
- el análisis más reciente ya no lo considera error.

### 5.8 Criterio recomendado

Antes de crear un ejercicio deberían evaluarse:

1. Validez ajedrecística actual.
2. Concepto enseñable.
3. Relación con una debilidad o fortaleza relevante.
4. Recurrencia del patrón.
5. Claridad de la decisión.
6. Número y calidad de alternativas aceptables.
7. Profundidad necesaria.
8. Dificultad estimada para el jugador.
9. Novedad frente al inventario existente.
10. Historial de entrenamiento del concepto.

---

## 6. Modelo de oportunidades de entrenamiento

Se recomienda introducir conceptualmente una capa equivalente a `TrainingOpportunity`.

No tiene que llamarse así ni convertirse inmediatamente en una tabla. Su función es separar:

```text
detectamos algo
!=
merece entrenarse
!=
debe entrenarse ahora
```

### 6.1 Información necesaria

- Posición y análisis vigente.
- Origen: jugada propia, rival, Review, apertura o escenario.
- Concepto principal y secundarios.
- Severidad del error.
- Recurrencia en partidas.
- Confianza estadística.
- Dificultad estimada.
- Longitud esperada.
- Cantidad de respuestas aceptables.
- Valor pedagógico.
- Adecuación para Flash o Scenario.
- Novedad y similitud con otras posiciones.
- Historial previo del jugador.
- Razón legible por la que se seleccionó.

### 6.2 Beneficio

Esta separación permitiría depurar, puntuar y revalidar oportunidades sin destruir el histórico de análisis ni inflar el inventario final.

---

## 7. Dificultad adaptativa

### 7.1 Estado actual

| Etiqueta | Inventario | Runs recientes | Acierto |
|---|---:|---:|---:|
| Fácil | 6.913 | 0 | Sin datos |
| Media | 840 | 1 | Muestra insuficiente |
| Difícil | 1.166 | 44 | 95,5 % |
| Crítica | 203 | 18 | 83,3 % |

El entrenamiento utilizado está casi completamente concentrado en `hard` y `critical`, aunque el inventario es mayoritariamente `easy`.

En los runs recientes:

- `hard`: 2,39 intentos medios, 45,5 % con pista, calidad media 79,1.
- `critical`: 2,28 intentos medios, 38,9 % con pista, calidad media 74,4.

### 7.2 Conclusión

La dificultad representa sobre todo la gravedad del error original. No mide bien lo difícil que es resolver la posición.

### 7.3 Modelo recomendado

Combinar:

- profundidad de la línea;
- número de candidate moves razonables;
- diferencia entre primera y alternativas;
- tipo de táctica o concepto;
- cantidad de piezas relevantes;
- tiempo hasta el primer movimiento;
- intentos;
- pistas;
- historial del jugador en posiciones similares;
- rendimiento observado de otros usuarios en el futuro.

La dificultad inicial debería ser una estimación y recalibrarse con evidencia de uso.

---

## 8. Escenarios multijugada

### 8.1 Inventario

| Tipo | Cantidad |
|---|---:|
| Conversión | 137 |
| Defensa | 130 |
| Mate | 30 |

### 8.2 Distribución de dificultad

- Críticos: 189.
- Difíciles: 50.
- Medios: 46.
- Fáciles: 12.

### 8.3 Longitud

- Objetivo de 2 jugadas del jugador: 58.
- Objetivo de 3 jugadas: 239.
- Máximo total entre 3 y 6 plies, dominado por escenarios de 6.

### 8.4 Hallazgos

- Hay 18 escenarios de conversión que empiezan con ventaja igual o superior a `+8.00`.
- Existe un ejemplo que empieza en `+14.31`.
- Hay 36 escenarios defensivos cuya evaluación inicial no es negativa.
- La razón de selección es genérica: “Posición real seleccionada por su impacto y valor de entrenamiento”.
- Los 297 candidatos registrados terminaron convertidos en escenarios.
- Solo existen 5 ejecuciones, por lo que todavía no hay muestra suficiente para calibrarlos.

### 8.5 Evaluación

Scenarios es una de las arquitecturas con mayor potencial porque obliga a sostener una idea frente a la mejor respuesta rival. Sin embargo, necesita objetivos pedagógicos más precisos que “mantener ventaja” o “seguir defendiendo”.

Ejemplos mejores:

- simplificar sin perder la ventaja;
- neutralizar contrajuego;
- activar el rey;
- convertir un peón pasado;
- eliminar una pieza defensora;
- encontrar una defensa única;
- mantener coordinación durante tres decisiones.

---

## 9. Repetición y consolidación

### 9.1 Estado actual

El sistema registra resultado, pistas, intentos y repetición. Hay programación de repasos, pero no existe todavía una noción robusta de dominio por concepto.

### 9.2 Propuesta mínima

Usar estados legibles:

```text
Nuevo
-> Practicando
-> Consolidando
-> Estable
-> Revisión ocasional
```

Un ejercicio o concepto avanza según:

- resoluciones correctas en fechas distintas;
- menor necesidad de pistas;
- reducción de intentos y tiempo;
- éxito en dificultad creciente;
- ausencia o reducción del patrón en partidas nuevas.

Los fallos deberían aumentar prioridad temporalmente. Los aciertos no deberían eliminar para siempre una posición, pero sí reducir radicalmente su frecuencia.

---

## 10. Personalización real

La personalización actual es real, pero todavía superficial:

```text
foco X
-> buscar tags o tipos relacionados con X
```

La evolución deseada es:

```text
concepto + contexto + comportamiento + tendencia + evidencia
-> decisión de entrenamiento
```

Ejemplos:

- falla conversión cuando debe simplificar;
- detecta tácticas ofensivas, pero no amenazas rivales;
- encuentra la primera jugada, pero falla la continuación;
- resuelve con pista de pieza, pero no de forma autónoma;
- un patrón antes frecuente está descendiendo y necesita menos exposición.

El sistema ya guarda buena parte de los datos necesarios: evaluaciones, tags, intentos, pistas, tiempos, dificultad, partidas y snapshots. Faltan una taxonomía pedagógica consistente y relaciones explícitas entre oportunidad, selección y resultado.

---

## 11. Medir aprendizaje real

### 11.1 Rendimiento en Training

Se puede medir inmediatamente:

- porcentaje resuelto;
- intentos;
- pistas;
- tiempo;
- calidad;
- dificultad;
- recuperación de ejercicios fallados;
- retención tras varios días.

### 11.2 Transferencia a partidas

Es la señal más valiosa:

- recurrencia del mismo patrón;
- conversión de ventajas;
- pérdidas de evaluación por fase;
- mates permitidos u omitidos;
- errores defensivos;
- ACPL y accuracy contextualizados;
- resultado en posiciones donde apareció un concepto entrenado.

### 11.3 Precaución estadística

No deberían mostrarse conclusiones fuertes con poca muestra. Usar:

- etiqueta `Señal inicial`;
- número de oportunidades observadas;
- ventana comparada;
- confianza baja, media o alta;
- lenguaje como “hay indicios” en lugar de “has mejorado” cuando no exista evidencia suficiente.

---

## 12. Engagement audit

### 12.1 Por qué puede no apetecer entrenar

Las causas más probables no son falta de XP:

- relevancia del ejercicio poco evidente;
- conceptos demasiado genéricos;
- dificultad no calibrada;
- entrenamientos que parecen una sucesión de posiciones;
- falta de cierre y recompensa intelectual;
- poca conexión visible con partidas reales;
- foco inconsistente entre módulos;
- Nova describe, pero raramente recuerda o compara;
- inventario enorme sin sensación de itinerario;
- no queda claro qué falta para consolidar una habilidad.

### 12.2 Evidencia de objetivos

Histórico de `training_plan_goals`:

| Tipo | Completados | Total | Tasa histórica |
|---|---:|---:|---:|
| Ejercicios generales | 9 | 41 | 22 % |
| Revisar partida | 8 | 54 | 14,8 % |
| Ejercicios de foco | 0 | 35 | 0 % |
| Días de entrenamiento | 1 | 6 | 16,7 % |
| Revisar varias partidas | 4 | 6 | 66,7 % |
| Ejercicios de apertura | 3 | 6 | 50 % |

Estas tasas mezclan versiones y posibles bugs históricos, pero `focus_exercises` es una señal especialmente preocupante: el objetivo que debería expresar la personalización no produjo completaciones registradas.

---

## 13. Gamificación recomendada

### 13.1 Qué mantener

- Racha discreta basada en actividad real.
- Objetivo diario pequeño y alcanzable.
- Objetivo semanal ligado a variedad y consolidación.
- Barra de progreso del entrenamiento actual.
- Hitos excepcionales y no repetitivos.
- Resumen al completar entrenamiento.
- Evolución por conceptos.

### 13.2 Qué no introducir todavía

- Monedas.
- Cofres.
- Ranking.
- Tienda de pistas.
- XP puramente acumulativa.
- Niveles que parezcan Elo.
- Premios por repetir ejercicios fáciles.

### 13.3 Mastery

No usar porcentajes precisos sin calibración. Es preferible:

```text
Iniciando
Aprendiendo
Consolidando
Estable
```

Cada estado debe mostrar muestra y confianza.

### 13.4 Hint economy

Cobrar pistas con puntos podría desincentivar una herramienta legítima de aprendizaje. Una alternativa superior es medir autonomía:

- resolver sin ayuda aumenta autonomía;
- pedir una pista reduce la calidad, pero no invalida el aprendizaje;
- usar menos ayuda que antes es un progreso explícito;
- una pista correcta en el momento adecuado puede ser recomendada por Nova.

---

## 14. Nova audit

### 14.1 Papel actual

Nova selecciona, presenta, da pistas y ofrece feedback mediante reglas deterministas. No es una IA conversacional, pero tampoco es una simple decoración.

### 14.2 Datos observados

- 43 mensajes persistidos.
- Solo 22 textos únicos.
- 19 mensajes de feedback.
- 9 de selección.
- 7 pistas.
- 4 cierres.
- 3 introducciones.
- 1 explicación.

### 14.3 Problemas

- Mensajes repetitivos y demasiado largos.
- Frases opacas como “Patrón pendiente detectado en tus ejercicios”.
- Errores de redacción como “casilla de el centro”.
- Afirmaciones técnicas repetidas que no enseñan el concepto.
- Poca comparación con comportamiento anterior.
- No existe una memoria de Coach claramente modelada.

### 14.4 Incoherencia de foco

En el snapshot más reciente aparecen simultáneamente:

- Perfil ADN: foco en `Visión táctica`.
- Recomendación primaria: `Error en final`.
- Entrenamientos estructurados: `Imprecisión`.

Cada cálculo puede ser defendible aisladamente, pero la experiencia transmite tres prioridades distintas.

### 14.5 Evolución recomendada

#### Fase 1 - Coach decision explicable

Una única decisión activa que contenga:

- foco;
- evidencia;
- confianza;
- razón;
- objetivo del entrenamiento;
- fecha de reevaluación.

#### Fase 2 - Memoria estructurada

Guardar hechos verificables:

- concepto entrenado;
- resultado anterior;
- pistas utilizadas;
- evolución reciente;
- repetición programada;
- relación con partidas posteriores.

#### Fase 3 - Nova reactiva

- estado `thinking` breve antes de feedback;
- reacción coherente con acierto, error o pista;
- animaciones ligeras mediante CSS, sprites o WebP;
- reconocimiento de mejoras reales;
- mensajes menos frecuentes y más significativos.

### 14.6 Personalidad

Nova debe ser:

- breve;
- precisa;
- adulta;
- constructiva;
- basada en evidencia;
- no excesivamente entusiasta;
- no repetitiva;
- centrada en ideas transferibles.

Ejemplo genérico a evitar:

> Hoy vamos a trabajar imprecisión.

Ejemplo creíble:

> En tus últimas partidas han bajado las imprecisiones, pero sigues permitiendo amenazas en finales. Hoy repasaremos dos posiciones relacionadas y una nueva.

---

## 15. UX audit

### 15.1 Home

Debe responder en este orden:

1. Qué debo hacer ahora.
2. Por qué me lo recomienda Nova.
3. Qué progreso reciente merece atención.
4. Qué debería revisar después.

La recomendación debe tener evidencia real y evitar competir con múltiples focos.

### 15.2 Review

Fortaleza: conecta jugada, evaluación, explicación y exploración.

Mejora principal: convertir momentos relevantes en oportunidades de entrenamiento explicables, no solo ofrecer la mejor línea.

### 15.3 Training

Fortaleza: solver mobile-first, pistas progresivas y feedback.

Mejora principal: mostrar el propósito de cada bloque y el progreso hacia consolidación, no solo el número de ejercicio.

### 15.4 Scenarios

Fortaleza: formato más cercano a jugar que un puzzle de una jugada.

Mejora principal: objetivos conceptuales concretos, dificultad adaptativa y cierre que explique qué decisión sostuvo la idea.

### 15.5 Analysis Board

Debe permanecer como herramienta seria de exploración. No necesita gamificación ni presencia constante de Nova.

### 15.6 Cierre de entrenamiento

Propuesta compacta:

- qué se entrenó;
- qué se resolvió autónomamente;
- qué sigue necesitando apoyo;
- qué se repetirá y cuándo;
- progreso del objetivo;
- una observación específica de Nova.

---

## 16. North Star Experience

La experiencia objetivo debería ser:

1. El usuario abre Chess Coach.
2. Nova presenta un entrenamiento de 6-10 minutos y explica su evidencia.
3. El bloque combina una posición nueva, una repetición y, si procede, un escenario.
4. Cada ejercicio enseña un concepto identificable.
5. La dificultad se adapta al rendimiento previo.
6. Nova reacciona solo cuando tiene algo útil que comunicar.
7. El cierre explica qué se consolidó y qué volverá a aparecer.
8. Una partida posterior proporciona evidencia de transferencia.
9. Nova reconoce la mejora con datos reales.

Ejemplo:

> Ayer permitiste dos amenazas defensivas parecidas. Hoy te he preparado una posición nueva y otra que necesitó pista. En la segunda ya no la has necesitado. Volveremos a comprobar el patrón dentro de unos días.

El motivo para volver mañana no debe ser conservar una moneda virtual. Debe ser sentir que existe un plan, que el sistema recuerda y que el esfuerzo está produciendo una habilidad observable.

---

## 17. Momentos de valor o “wow”

1. “Este patrón apareció tres veces en tus partidas; hoy trabajarás dos ejemplos relacionados.”
2. “La última vez necesitaste identificar la pieza. Hoy lo resolviste sin ayuda.”
3. “Después de entrenar conversión, mantuviste la ventaja en cuatro de tus últimas seis oportunidades.”
4. “Esta posición proviene de la partida de ayer y se parece a una que ya consolidaste.”
5. “Tu foco ha cambiado porque el problema anterior ha descendido y ahora existe otra prioridad con más evidencia.”

Todos deben estar sustentados por datos estructurados.

---

## 18. Qué no gamificar

- Evaluaciones Stockfish.
- Accuracy y ACPL.
- Clasificación de jugadas.
- Errores graves.
- ADN y niveles de confianza.
- Analysis Board.
- Explicaciones pedagógicas.
- Comparaciones de mejora con poca muestra.

Estas áreas deben conservar un tono preciso y profesional.

---

## 19. Datos disponibles con valor infrautilizado

- Evaluaciones antes y después.
- Mejor jugada y alternativas.
- Tags de partida y movimiento.
- Fase de partida.
- Resultados y color.
- Intentos y errores por ejercicio.
- Tiempo empleado.
- Nivel y tipo de pista.
- Calidad de resolución.
- Repeticiones.
- Escenarios y eventos por movimiento.
- Objetivos diarios y semanales.
- Snapshots de ADN.
- Progreso agregado.
- Revisiones completadas.
- Historial de foco.

Estos datos permiten mejorar mucho el producto sin introducir todavía un LLM ni servicios externos.

---

## 20. Datos o conceptos que faltan

- Concepto pedagógico normalizado.
- Razón concreta de selección del ejercicio.
- Valor pedagógico estimado.
- Recurrencia del patrón.
- Similitud con otras posiciones.
- Vigencia respecto al último análisis.
- Dificultad observada y no solo estimada.
- Tiempo hasta la primera jugada.
- Estado de consolidación por concepto.
- Evidencia de transferencia a partidas.
- Decisión única del Coach.
- Memoria estructurada de Nova.

No implica que todos necesiten nuevas columnas. Primero debe diseñarse el modelo conceptual.

---

## 21. Recomendaciones priorizadas

### P0 - Calidad y credibilidad

#### Depurar y versionar el inventario

- **Problema:** duplicados, análisis antiguos y soluciones cambiantes.
- **Solución:** vigencia explícita, deduplicación por posición/concepto y revalidación.
- **Impacto:** muy alto.
- **Esfuerzo:** medio-alto.
- **Riesgo:** migración y compatibilidad con histórico.
- **Éxito:** caída radical de inventario redundante sin reducir cobertura útil.

#### Separar detección y entrenamiento

- **Problema:** demasiadas posiciones se convierten automáticamente en ejercicio.
- **Solución:** Training Opportunity con scoring pedagógico.
- **Impacto:** muy alto.
- **Esfuerzo:** alto.
- **Dependencia:** taxonomía de conceptos.
- **Éxito:** mayor uso, menor descarte y mejor satisfacción por ejercicio.

#### Recalibrar dificultad

- **Problema:** severidad no equivale a dificultad.
- **Solución:** dificultad inicial más ajuste por comportamiento.
- **Impacto:** alto.
- **Esfuerzo:** medio.
- **Éxito:** distribución de éxito y pistas estable por nivel.

### P1 - Aprendizaje y engagement

#### Curriculum adaptativo mínimo

- **Problema:** entrenamiento como lista, no como progresión.
- **Solución:** nuevo, práctica, consolidación y repaso.
- **Impacto:** muy alto.
- **Esfuerzo:** alto.
- **Éxito:** mejora en retención y recuperación de ejercicios fallados.

#### Coach Decision único

- **Problema:** focos contradictorios.
- **Solución:** una decisión activa, explicable y compartida por Home, Training y Nova.
- **Impacto:** alto.
- **Esfuerzo:** medio.
- **Éxito:** coherencia entre recomendación, entrenamiento y mensajes.

#### Cierre de entrenamiento

- **Problema:** recompensa y progreso poco visibles.
- **Solución:** resumen breve de aprendizaje y siguiente paso.
- **Impacto:** alto.
- **Esfuerzo:** medio-bajo.
- **Éxito:** mayor tasa de finalización y retorno.

### P2 - Evolución

#### Mastery por conceptos

- **Problema:** progreso agregado poco explicativo.
- **Solución:** estados con muestra y confianza.
- **Impacto:** alto.
- **Esfuerzo:** medio-alto.
- **Éxito:** usuario comprende qué mejora y qué sigue débil.

#### Transferencia a partidas

- **Problema:** entrenar no demuestra mejora real.
- **Solución:** comparar oportunidades posteriores del mismo concepto.
- **Impacto:** muy alto a largo plazo.
- **Esfuerzo:** alto.
- **Riesgo:** conclusiones falsas con poca muestra.

#### Memoria estructurada de Nova

- **Problema:** mensajes correctos pero genéricos.
- **Solución:** hechos verificables sobre entrenamiento y evolución.
- **Impacto:** alto.
- **Esfuerzo:** medio.

### P3 - Pulido

- Animaciones discretas de Nova.
- Sonidos opcionales.
- Hitos excepcionales.
- Mayor variedad visual de cierre.

---

## 22. Quick wins

1. Sustituir “Patrón pendiente detectado” por evidencia concreta.
2. Unificar el foco mostrado en Home, ADN y Training.
3. No seleccionar ejercicios de análisis obsoletos.
4. Reducir radicalmente ejercicios del rival ya jugados correctamente.
5. Mostrar por qué se seleccionó cada ejercicio.
6. Añadir cierre breve al entrenamiento.
7. Corregir textos repetitivos y errores gramaticales de Nova.
8. Mostrar “repetición”, “nuevo” o “consolidación” en cada ejercicio.
9. Evitar títulos genéricos cuando exista un concepto identificable.
10. Medir tiempo hasta la primera jugada en futuras resoluciones.

---

## 23. Roadmap recomendado

### Release A - Training Quality Foundation

- Modelo de oportunidad de entrenamiento.
- Taxonomía inicial de conceptos.
- Vigencia y deduplicación.
- Filtro pedagógico.
- Recalibración inicial de dificultad.
- Auditoría/backfill controlado del inventario.

### Release B - Adaptive Curriculum

- Selección por novedad, foco, dificultad y repetición.
- Estados de aprendizaje.
- Mezcla de Flash y Scenario.
- Cierre de entrenamiento.
- Razones de selección visibles.

### Release C - Coach Intelligence

- Coach Decision único.
- Memoria estructurada.
- Mensajes sustentados por evidencia.
- Nova reactiva con timings y estados coherentes.

### Release D - Progress and Transfer

- Mastery por conceptos.
- Señales de transferencia a partidas.
- Comparaciones con muestra y confianza.
- Objetivos semanales orientados a consolidación.

No se recomienda abordar todas las fases en una sola versión.

---

## 24. Métricas de éxito

### Engagement

- Entrenamientos iniciados por semana.
- Tasa de finalización.
- Días activos de entrenamiento.
- Retorno al día siguiente y a siete días.
- Tiempo desde Home hasta comenzar entrenamiento.

### Aprendizaje

- Intentos por dificultad y concepto.
- Pistas por dificultad y concepto.
- Tiempo hasta primera jugada.
- Recuperación de ejercicios previamente fallados.
- Retención después de varios días.
- Progresión de estados de mastery.

### Transferencia

- Reaparición del mismo patrón en partidas nuevas.
- Cambio en severidad o frecuencia.
- Conversión de ventajas posteriores.
- Defensa de amenazas similares.
- Resultado contextualizado por concepto.

### Calidad del sistema

- Porcentaje de oportunidades aceptadas como ejercicio.
- Duplicados evitados.
- Ejercicios invalidados tras reanálisis.
- Ratio de inventario utilizado.
- Discrepancias de solución.
- Ejercicios abandonados o considerados ambiguos.

No debe optimizarse engagement sacrificando aprendizaje real.

---

## 25. Respuesta a las preguntas centrales

### ¿Por qué debería mejorar más que haciendo puzzles genéricos o mirando Stockfish?

La respuesta potencial es:

- porque utiliza posiciones propias;
- detecta patrones recurrentes;
- adapta dificultad y repetición;
- entrena decisiones de varias jugadas;
- comprueba transferencia a partidas futuras;
- construye continuidad mediante un Coach que recuerda.

La respuesta actual todavía es incompleta. La procedencia es personal, pero el criterio pedagógico y el curriculum necesitan madurar.

### ¿Qué motivo tiene el usuario para volver mañana?

Debería volver porque existe un plan breve y continuado, porque el sistema recuerda lo que costó hoy y porque puede percibir consolidación antes de que cambie su Elo.

Actualmente hay racha, objetivos y Nova, pero todavía falta demostrar esa continuidad con suficiente claridad.

---

## 26. Decisión recomendada para Product Owner

No priorizar en la siguiente gran iteración:

- más volumen de ejercicios;
- más dashboards;
- una economía de puntos;
- un LLM conversacional;
- animaciones complejas;
- más tipos de objetivo.

Priorizar:

1. Calidad de oportunidades.
2. Vigencia y deduplicación.
3. Conceptos transferibles.
4. Dificultad adaptativa.
5. Progresión y consolidación.
6. Decisión coherente de Nova.
7. Evidencia de aprendizaje.

La oportunidad estratégica es convertir Chess Coach de:

> “un sistema que genera ejercicios desde mis partidas”

en:

> “un entrenador que entiende qué patrón debo trabajar, elige una práctica adecuada, recuerda cómo me fue y comprueba si después juego mejor”.

---

## 27. Preguntas que el Product Owner debe resolver

1. ¿Cuál será la primera taxonomía limitada de conceptos?
2. ¿Debe priorizarse calidad aunque el inventario visible se reduzca drásticamente?
3. ¿Qué proporción debe existir entre ejercicios propios, rival, repetición y contenido nuevo?
4. ¿Qué criterios mínimos invalidan una oportunidad?
5. ¿Cuál es el objetivo de duración de un entrenamiento diario?
6. ¿Cuándo considera el producto que un concepto está consolidado?
7. ¿Qué evidencia mínima permite afirmar que existe transferencia?
8. ¿Qué foco tiene prioridad cuando ADN, tags y forma reciente discrepan?
9. ¿Qué mensajes de Nova requieren memoria y cuáles pueden seguir siendo plantillas?
10. ¿Qué métrica será la North Star: finalización, retorno, consolidación o transferencia?

---

## 28. Baseline recomendado para comparar futuras versiones

- Inventario activo: 9.122 ejercicios.
- Inventario utilizado: 118 ejercicios.
- Inventario nunca usado: 98,7 %.
- Procedencia rival: 64,7 %.
- Ejercicios sin tags: 80,4 %.
- Filas en grupos duplicados: 2.691.
- Ejercicios de análisis antiguo: 1.440.
- Soluciones principales cambiadas: 1.178.
- Runs recientes `hard`: 95,5 % resueltos, 45,5 % con pista.
- Runs recientes `critical`: 83,3 % resueltos, 38,9 % con pista.
- Revisiones completadas: 42 de 66 registradas.
- Objetivos históricos de foco completados: 0 de 35.
- Mensajes de Nova: 43, con 22 textos únicos.

Estas métricas permiten comprobar si una futura versión reduce ruido, mejora selección y produce más aprendizaje con menos inventario.
