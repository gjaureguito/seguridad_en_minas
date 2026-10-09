-- =====================================================================
-- Catálogos base (idempotente). Se puede ampliar desde la base.
-- =====================================================================

-- Categorías del sistema original (se conservan los códigos)
INSERT INTO categories (code, label) VALUES
 ('INCIDENTE_CON_LESION','Incidente con lesión'),
 ('INCIDENTE_SIN_LESION','Incidente sin lesión'),
 ('CUASI_INCIDENTE','Cuasi incidente'),
 ('CONDICION_INSEGURA','Condición insegura'),
 ('ACTO_INSEGURO','Acto inseguro'),
 ('PELIGRO_AMBIENTAL','Peligro ambiental'),
 ('FALLA_EQUIPO','Falla de equipo'),
 ('EVENTO_GEOTECNICO','Evento geotécnico'),
 ('EMERGENCIA_MEDICA','Emergencia médica'),
 ('SIMULACRO','Simulacro'),
 ('INSPECCION','Inspección'),
 ('MEJORA','Oportunidad de mejora')
ON CONFLICT (code) DO NOTHING;

INSERT INTO severities (code, label, orden) VALUES
 ('BAJA','Baja',1),('MEDIA','Media',2),('ALTA','Alta',3),('CRITICA','Crítica',4),('POTENCIAL','Potencial',5)
ON CONFLICT (code) DO NOTHING;

-- ---------------------------------------------------------------------
-- FORMA DEL ACCIDENTE — grupos OIT (1962), equivalentes a los títulos
-- 100..900 de la Tabla de Forma del RENAL (Res. SRT 3326/14).
-- ---------------------------------------------------------------------
INSERT INTO catalogs (kind, code, label, sort) VALUES
 ('FORMA','1','Caída de personas',1),
 ('FORMA','1.1','Caída de personas con desnivel (altura)',2),
 ('FORMA','1.2','Caída de personas al mismo nivel',3),
 ('FORMA','2','Caída de objetos (incluye caída de rocas / planchones)',4),
 ('FORMA','2.1','Derrumbe / desprendimiento de roca o talud',5),
 ('FORMA','2.2','Caída de objetos en manipulación o izaje',6),
 ('FORMA','3','Pisadas sobre, choques contra o golpes por objetos',7),
 ('FORMA','3.1','Golpe o atropello por vehículo / equipo móvil',8),
 ('FORMA','4','Atrapamiento por un objeto o entre objetos',9),
 ('FORMA','5','Esfuerzos excesivos o falsos movimientos',10),
 ('FORMA','6','Exposición o contacto con temperaturas extremas',11),
 ('FORMA','7','Exposición o contacto con corriente eléctrica',12),
 ('FORMA','8','Exposición o contacto con sustancias nocivas o radiaciones',13),
 ('FORMA','8.1','Explosión / proyección por voladura',14),
 ('FORMA','9','Otras formas de accidente',15),
 ('FORMA','9.1','Accidente de tránsito (vía pública / in itinere)',16)
ON CONFLICT (kind, code) DO NOTHING;

-- AGENTE MATERIAL — grupos OIT
INSERT INTO catalogs (kind, code, label, sort) VALUES
 ('AGENTE','1','Máquinas',1),
 ('AGENTE','1.1','Máquinas de perforación / jumbo / perforadora',2),
 ('AGENTE','1.2','Chancadoras, molinos, cintas transportadoras',3),
 ('AGENTE','2','Medios de transporte y de manutención',4),
 ('AGENTE','2.1','Camión minero / equipo de acarreo',5),
 ('AGENTE','2.2','Camioneta / vehículo liviano',6),
 ('AGENTE','2.3','Grúa, puente grúa, aparejos de izaje',7),
 ('AGENTE','2.4','Cargador, pala, retroexcavadora, scoop',8),
 ('AGENTE','3','Otros aparatos (recipientes a presión, hornos, instalaciones eléctricas, herramientas)',9),
 ('AGENTE','3.1','Instalaciones eléctricas',10),
 ('AGENTE','3.2','Herramientas manuales',11),
 ('AGENTE','4','Materiales, sustancias y radiaciones',12),
 ('AGENTE','4.1','Explosivos',13),
 ('AGENTE','4.2','Sustancias químicas (cianuro, ácidos, reactivos)',14),
 ('AGENTE','4.3','Polvo / sílice',15),
 ('AGENTE','5','Ambiente de trabajo (terreno, labores, techo/caja, frente)',16),
 ('AGENTE','5.1','Roca / talud / techo de labor',17),
 ('AGENTE','6','Otros agentes no clasificados',18)
ON CONFLICT (kind, code) DO NOTHING;

-- NATURALEZA DE LA LESIÓN — OIT
INSERT INTO catalogs (kind, code, label, sort) VALUES
 ('NATURALEZA','10','Fracturas',1),
 ('NATURALEZA','20','Luxaciones',2),
 ('NATURALEZA','25','Torceduras y esguinces',3),
 ('NATURALEZA','30','Conmociones y traumatismos internos',4),
 ('NATURALEZA','40','Amputaciones y enucleaciones',5),
 ('NATURALEZA','41','Otras heridas',6),
 ('NATURALEZA','50','Traumatismos superficiales',7),
 ('NATURALEZA','60','Contusiones y aplastamientos',8),
 ('NATURALEZA','70','Quemaduras',9),
 ('NATURALEZA','80','Envenenamientos e intoxicaciones agudas',10),
 ('NATURALEZA','81','Efectos del tiempo / exposición al frío o calor',11),
 ('NATURALEZA','82','Asfixia',12),
 ('NATURALEZA','83','Efectos de la electricidad',13),
 ('NATURALEZA','84','Efectos de las radiaciones',14),
 ('NATURALEZA','85','Lesiones múltiples de naturaleza diferente',15),
 ('NATURALEZA','90','Otros traumatismos o mal definidos',16)
ON CONFLICT (kind, code) DO NOTHING;

-- ZONA DEL CUERPO — OIT
INSERT INTO catalogs (kind, code, label, sort) VALUES
 ('ZONA','1','Cabeza',1),
 ('ZONA','1.1','Ojos',2),
 ('ZONA','1.2','Oídos',3),
 ('ZONA','1.3','Cara',4),
 ('ZONA','2','Cuello',5),
 ('ZONA','3','Tronco (espalda, columna, tórax, abdomen, pelvis)',6),
 ('ZONA','4','Miembro superior',7),
 ('ZONA','4.1','Mano',8),
 ('ZONA','4.2','Dedos de la mano',9),
 ('ZONA','5','Miembro inferior',10),
 ('ZONA','5.1','Pie / tobillo',11),
 ('ZONA','6','Ubicaciones múltiples',12),
 ('ZONA','7','Lesiones generales (sistémicas)',13),
 ('ZONA','8','Ubicación no precisada',14)
ON CONFLICT (kind, code) DO NOTHING;

-- RIESGOS CRÍTICOS de minería (enfoque ICMM de controles críticos)
INSERT INTO catalogs (kind, code, label, sort) VALUES
 ('RIESGO_CRITICO','CAIDA_ROCAS','Caída de rocas / inestabilidad de taludes',1),
 ('RIESGO_CRITICO','INTERACCION_EQUIPOS','Interacción equipo–persona / equipo–equipo',2),
 ('RIESGO_CRITICO','VEHICULOS_LIVIANOS','Conducción de vehículos livianos',3),
 ('RIESGO_CRITICO','EXPLOSIVOS','Explosivos y voladura (Dec. 249/07 arts. 89–95)',4),
 ('RIESGO_CRITICO','ENERGIA_PELIGROSA','Energía peligrosa / bloqueo (Dec. 249/07 art. 105)',5),
 ('RIESGO_CRITICO','TRABAJO_ALTURA','Trabajo en altura',6),
 ('RIESGO_CRITICO','IZAJE','Izaje de cargas',7),
 ('RIESGO_CRITICO','ESPACIO_CONFINADO','Espacios confinados (Dec. 249/07 art. 80)',8),
 ('RIESGO_CRITICO','SUSTANCIAS_PELIGROSAS','Sustancias peligrosas',9),
 ('RIESGO_CRITICO','INCENDIO','Incendio',10),
 ('RIESGO_CRITICO','ATMOSFERA','Atmósfera / ventilación / polvo',11),
 ('RIESGO_CRITICO','GUARDAS','Partes móviles / guardas de máquinas',12),
 ('RIESGO_CRITICO','OTRO','Otro',13)
ON CONFLICT (kind, code) DO NOTHING;
