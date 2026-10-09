# Marco normativo y métodos aplicados

Resumen de qué norma o método respalda cada parte del sistema. No reemplaza la consulta del texto oficial ni el criterio del responsable de Higiene y Seguridad.

## Normas

| Norma | Qué toma el sistema |
|---|---|
| **Ley 19.587** (Higiene y Seguridad en el Trabajo) | Marco general de prevención. |
| **Ley 24.557** (Riesgos del Trabajo), art. 6 | Tipos de contingencia: accidente de trabajo (por el hecho o en ocasión del trabajo), accidente *in itinere* y enfermedad profesional. |
| **Dec. 249/07** (Higiene y Seguridad en la actividad minera) | Art. 13 n): el Servicio de HyS lleva el registro de siniestralidad. Art. 13 o): investiga los accidentes y enfermedades profesionales. Art. 17 e): análisis conjunto con Medicina del Trabajo. Art. 20: todas las acciones quedan registradas. Art. 26 g): el Comité participa en la investigación. Riesgos críticos citados: explosivos (arts. 89–95), espacios confinados (art. 80), energía peligrosa y bloqueo (art. 105). |
| **Res. SRT 525/15** (denuncia de AT y EP) | El empleador denuncia a la ART **dentro de las 48 h**, con o sin baja, y entrega una copia al trabajador. El sistema calcula el vencimiento y mide el cumplimiento. |
| **Res. SRT 3326/14** (RENAL) | Tablas de forma del accidente, agente material, naturaleza de la lesión y zona del cuerpo. El sistema usa los **grupos OIT (1962)**, que coinciden con los títulos de esas tablas. Si necesitás los códigos exactos del RENAL a nivel de subgrupo, se agregan en la tabla `catalogs`. |

El Dec. 249/07 no fija un plazo para comunicar los accidentes a la autoridad minera. El campo "autoridad notificada" sirve para registrar lo que pida la autoridad provincial.

## Índices (SRT / OIT)

| Índice | Fórmula | Unidad |
|---|---|---|
| Frecuencia (IF) | casos con baja × 1.000.000 / HHT | casos por millón de horas |
| Gravedad (IG) | días perdidos × 1.000 / HHT | días por mil horas |
| Incidencia (II) | trabajadores siniestrados × 1.000 / dotación promedio | por mil trabajadores (anual) |
| Pérdida (IP) | días perdidos × 1.000 / dotación promedio | |
| Duración media de bajas (DMB) | días perdidos / casos con baja | días |
| Incidencia de fallecidos (IM) | fallecidos × 1.000.000 / dotación | por millón de trabajadores |
| TRIFR | lesiones registrables × 1.000.000 / HHT | |

- **Caso con baja (LTI):** accidente de trabajo con consecuencia "con baja", "incapacidad permanente" o "fatal".
- **Lesión registrable (TRI):** LTI más tratamiento médico más trabajo restringido. Los primeros auxilios no cuentan.
- **Días perdidos:** días corridos de baja, sin contar el día del accidente ni el día de reintegro.
- **In itinere:** se informa aparte y no entra en IF ni IG, porque no depende de las condiciones de la faena (criterio de la SRT).
- **II del período vs. anualizado:** si el período no tiene 12 meses, el sistema muestra también el II llevado a un año.

## Control estadístico

**Gráfico de control u (Poisson).** Los accidentes son eventos raros y la exposición cambia cada mes, por eso se usa una carta *u* y no una carta *c*:

```
n_i = HHT_i / 1.000.000
u_i = casos_i / n_i
ū   = Σ casos / Σ n
LCS_i = ū + 3·√(ū / n_i)
LCI_i = máx(0, ū − 3·√(ū / n_i))
```

Señales de causa asignable: un mes fuera de los límites, o 8 meses seguidos del mismo lado de la media (regla de Western Electric).

**Pirámide de Bird (1 : 10 : 30 : 600).** Por cada lesión grave: 10 leves, 30 daños a la propiedad y 600 incidentes sin lesión ni daño. Si la base observada queda muy por debajo, lo más probable es que haya subregistro de incidentes.

**Pareto (80/20).** Ordena forma, agente, naturaleza, zona, riesgo crítico o categoría, y marca los "pocos vitales" que suman el 80 % de los casos.

**Matriz de riesgo 5×5.** La severidad potencial es probabilidad × consecuencia del peor resultado creíble: 1–4 bajo, 5–9 moderado, 10–14 alto, 15–25 intolerable. Un evento de 15 o más se cuenta como **alto potencial**, aunque no haya habido lesión.

## Investigación

- **Modelo de causalidad de Bird / ILCI:** falta de control → causas básicas (factores personales y del trabajo) → causas inmediatas (actos y condiciones subestándar) → incidente → pérdida.
- **Métodos que se pueden registrar:** árbol de causas (INRS), 5 porqués, ICAM y TapRooT.
- **Acciones:** se clasifican según la jerarquía de controles (eliminación, sustitución, ingeniería, administrativo, EPP). Cada una lleva responsable y fecha, y se cierra con la verificación de su eficacia.
