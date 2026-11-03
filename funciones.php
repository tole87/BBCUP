<?php

// ************** OBTENER DATOS TABLA USUARIOS *********************************************************
function obtenerUsuarios() {
    try {        
        include('conexion.php');
        $query = "SELECT * FROM usuarios";
        //Preparamos y ejecutamos la consulta
        $stmt = $baseDatos->prepare($query);
        $stmt->execute();             
      
        $usuarios = [];
        // Guardamos las tiendas en un array
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        } catch (PDOException $e) {
            die("Error al obtener las tiendas: " . $e->getMessage());
         }

    return $usuarios;
}

// ************** OBTENER DATOS TABLA EQUIPO *********************************************************
function obtenerRazas() {
    try {        
        include('conexion.php');
        $query = "SELECT * FROM razas";
        //Preparamos y ejecutamos la consulta
        $stmt = $baseDatos->prepare($query);
        $stmt->execute();             
      
        $razas = [];
        // Guardamos las tiendas en un array
        $razas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        } catch (PDOException $e) {
            die("Error al obtener las tiendas: " . $e->getMessage());
         }

    return $razas;
}

// ************** OBTENER DATOS EQUIPO *********************************************************
function obtenerEquipoUnico($id) {
    try {        
        include('conexion.php');

        // Modificamos la tabla de "equipos" a "razas" para leer el catálogo estático
        $query = "SELECT * FROM razas WHERE id = :id";
        $stmt = $baseDatos->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();             
      
        $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        die("Error al obtener los datos: " . $e->getMessage());
    }

    return $equipo;
}
// ************** REGISTRAR NUEVO USUARIO EN TABLA USUARIOS *********************************************************
function registrarUsuario($email,$pass) {
    include('conexion.php');
    $usuarios = obtenerUsuarios();

    foreach ($usuarios as $u) {
        if ($u['email'] === $email) {
            return "No se puede registrar el usurio ya existe";
        }
    }

    try {            
        $query = "INSERT INTO usuarios (email, pass) VALUES (:email, :pass)";
        //Preparamos y ejecutamos la consulta
        $stmt = $baseDatos->prepare($query);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':pass', $pass, PDO::PARAM_STR);
        $stmt->execute();           

        } catch (PDOException $e) {
            die("Error al obtener las tiendas: " . $e->getMessage());
         }

   return "Usuario registrado correctamente";
}

// ************** COMPROBAR SI EXISTE USUARIO EN TABLA USUARIOS *********************************************************

function existeUsuario($email,$pass){
    include('conexion.php');
    $usuarios = obtenerUsuarios();
    $match = true;      
    foreach ($usuarios as $u) {
        if ($u['email'] === $email && $u['pass'] === $pass) {
            header("Location: index.php");
            die();
            
        }elseif($u['email'] === $email && $u['pass'] !== $pass){
            return "<div class='message error'>La contraseña es erronea</div>";
            
        }
    }

    if (!$match) {
        return "<div class='message error'>El email no está registrado aun.</div>";
    }

}
// ************** OBTENER POSICIONES DE JUGADOR POR RAZA *********************************************************
function obtenerPosicionesPorRaza($razaId) {
    try {        
        include('conexion.php');
        $query = "SELECT * FROM posiciones_raza WHERE raza_id = :raza_id";
        $stmt = $baseDatos->prepare($query);
        $stmt->bindParam(':raza_id', $razaId, PDO::PARAM_INT);
        $stmt->execute();             
        $posiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener las posiciones: " . $e->getMessage());
    }
    return $posiciones;
}
// ************** Diccionario habilidades *********************************************************
function obtenerInfoHabilidad($nombre) {
    // Normalizamos el nombre a minúsculas para evitar fallos si se escribe distinto
    $nombreLimpio = trim(mb_strtolower($nombre, 'UTF-8'));
    
    $diccionario = [
        // --- MEJORAS DE ATRIBUTOS ---
        '+1 ma' => 'Mejora permanente: +1 casilla de Movimiento máximo.',
        '+1 st' => 'Mejora permanente: +1 de Fuerza.',
        '+1 ag' => 'Mejora permanente: La tirada objetivo de Agilidad baja en 1 (ej: de 3+ a 2+).',
        '+1 pa' => 'Mejora permanente: La tirada objetivo de Pase baja en 1.',
        '+1 av' => 'Mejora permanente: Mejora el blindaje para que sea más difícil de romper.',

        // --- HABILIDADES DE AGILIDAD (AGILITY) ---
        'atrapar' => 'Una vez por turno, permite repetir una tirada fallida de Atrapar el balón.',
        'atrapar en plancha' => 'Suma +1 al atrapar un pase preciso. Permite atrapar pases imprecisos en casillas adyacentes.',
        'echarse a un lado' => 'Si es empujado, el entrenador elige a qué casilla adyacente se mueve en lugar del rival.',
        'en pie de un salto' => 'Permite levantarse sin gastar 3 casillas de movimiento. Permite placar desde el suelo pasando tirada de Agilidad.',
        'esquivar' => 'Una vez por turno, permite repetir una tirada fallida de Esquivar. Protege contra "Defensor Tropieza".',
        'par de manos seguras' => 'Si es derribado o pierde el balón, puede elegir en qué casilla adyacente vacía cae la pelota.',
        'paso ligero' => 'Permite intentar "A por Ellos" (Rush) hasta 3 veces por turno en lugar de las 2 habituales.',
        'pies firmes' => 'Una vez por turno, permite repetir una tirada fallida de "A por Ellos" (Rush).',
        'placaje heroico' => 'Suma +2 a la tirada del rival si intenta esquivar o saltar de la Zona de Defensa de este jugador.',
        'rastreador' => 'Al hacer falta, no es expulsado si saca un doble en Armadura o Lesión, salvo que rompa armadura.',
        'salto' => 'Permite saltar sobre una casilla ocupada realizando una tirada de Agilidad con menos penalización.',

        // --- HABILIDADES GENERALES (GENERAL) ---
        'agallas' => 'Al placar a un rival más fuerte, tira 1D6: si el resultado + Fuerza supera al rival, igualan Fuerzas.',
        'alerta' => 'Permite moverse gratis 3 casillas cuando el rival declara un Pase o el balón sale del campo.',
        'pasar en carrera' => 'Permite moverse gratis 3 casillas cuando el rival declara un Pase o el balón sale del campo.',
        'despojar el balon' => 'Si placa al portador y elige "Empujado", el rival suelta la pelota en la casilla a la que retrocede.',
        'despojar el balón' => 'Si placa al portador y elige "Empujado", el rival suelta la pelota en la casilla a la que retrocede.',
        'forcejeo' => 'Si sale "Ambos Derribados", ambos caen al suelo pero no se tiran armaduras y no hay cambio de turno.',
        'furia' => 'Debe seguir obligatoriamente a su objetivo al empujarlo y realizar un segundo placaje si el rival sigue en pie.',
        'juego sucio' => 'Suma +1 a la tirada de Armadura O a la de Lesiones al cometer una Falta.',
        'manos seguras' => 'Repite tirada fallida de recogida de balón. Es inmune a la habilidad Despojar el Balón.',
        'patada' => 'Reduce a la mitad la dispersión del balón al dar la Patada Inicial.',
        'perseguir' => 'Si un rival esquiva, tira 1D6: si el resultado + Movimiento supera al rival, se mueve con él.',
        'placar' => 'Protege al jugador de caer al suelo cuando sale "Ambos Derribados" (Both Down).',
        'bloqueo' => 'Protege al jugador de caer al suelo cuando sale "Ambos Derribados" (Both Down).',
        'profesional' => 'Una vez por turno, tira 1D6. Con un 3+, repite cualquier tirada propia sin gastar Reroll de equipo.',
        'robar balon' => 'Los jugadores en su Zona de Defensa no pueden usar Esquivar para escapar ni para no caer derribados.',
        'robar balón' => 'Los jugadores en su Zona de Defensa no pueden usar Esquivar para escapar ni para no caer derribados.',
        'zafarse' => 'Si este jugador es empujado, el oponente no puede seguirle (Follow-up) a la casilla vacía.',
        'zancadilla' => 'Reduce en 1 el Movimiento del rival o ayuda a derribarlo si intenta esquivar.',

        // --- HABILIDADES DE FUERZA (STRENGTH) ---
        'abrirse paso' => 'Una vez por turno, usa su Fuerza en lugar de Agilidad para una tirada de Esquivar (o suma +1 si ST<5).',
        'apartar' => 'Al empujar, el entrenador elige a qué casilla adyacente retrocede el rival. Impide usar Echarse a un Lado.',
        'brazo fuerte' => 'Suma +1 a las tiradas de Pase cuando se realiza un Pase Largo o una Bomba.',
        'cabeza dura' => 'Si la tirada de Lesión es 8 (Aturdido), se queda bocarriba. Si es 9, se queda Aturdido en lugar de KO.',
        'camorrista' => 'Al placar (no en Blitz), puede repetir un dado de placaje que muestre "Ambos Derribados".',
        'zafarrancho' => 'Al placar (no en Blitz), puede repetir un dado de placaje que muestre "Ambos Derribados".',
        'defensa' => 'Proporciona apoyos en placajes incluso si se encuentra en la Zona de Defensa de otro jugador rival.',
        'guardia' => 'Proporciona apoyos en placajes incluso si se encuentra en la Zona de Defensa de otro jugador rival.',
        'golpe mortifero' => 'Si derriba al oponente, puede sumar +1 a la tirada de Armadura O a la de Lesión.',
        'golpe mortífero' => 'Si derriba al oponente, puede sumar +1 a la tirada de Armadura O a la de Lesión.',
        'juggernaut' => 'En un Blitz, si saca "Ambos Derribados" puede convertirlo en "Empujado". Ignora Mantenerse Firme y Zafarse.',
        'llave de brazo' => 'Si un rival cae tras fallar una esquiva/salto en su Zona de Defensa, suma +1 a su Armadura o Lesión.',
        'mantenerse firme' => 'No puede ser empujado en un placaje a menos que elija hacerlo.',
        'firme' => 'No puede ser empujado en un placaje a menos que elija hacerlo.',
        'martillo pilon' => 'Tras derribar a un rival, permite hacerle una falta gratis e inmediata, terminando su activación.',
        'martillo pilón' => 'Tras derribar a un rival, permite hacerle una falta gratis e inmediata, terminando su activación.',
        'aplastar' => 'Tras derribar a un rival, permite hacerle una falta gratis e inmediata, terminando su activación.',
        'placaje multiple' => 'Permite placar a dos jugadores adyacentes a la vez, reduciendo la Fuerza propia en 2 para cada placaje.',
        'placaje múltiple' => 'Permite placar a dos jugadores adyacentes a la vez, reduciendo la Fuerza propia en 2 para cada placaje.',

        // --- HABILIDADES DE PASE (PASSING) ---
        'ave maria' => 'Permite lanzar un pase a cualquier casilla del campo ignorando la distancia (se dispersa 3 casillas).',
        'ave maría' => 'Permite lanzar un pase a cualquier casilla del campo ignorando la distancia (se dispersa 3 casillas).',
        'balon suelto preparado' => 'Una vez por turno, el jugador puede abandonar el balón en su casilla sin que provoque Cambio de Turno.',
        'pase precipitado' => 'Si va a ser placado teniendo el balón, puede intentar un Pase Rápido antes de ser golpeado.',
        'cañonero' => 'Suma +1 a las tiradas de Pase al realizar un Pase Largo o Bomba.',
        'lider' => 'Otorga al equipo 1 Segunda Oportunidad (Reroll) extra mientras el jugador esté en el campo.',
        'líder' => 'Otorga al equipo 1 Segunda Oportunidad (Reroll) extra mientras el jugador esté en el campo.',
        'nervios de acero' => 'Ignora los penalizadores por Zonas de Defensa rivales al intentar un Pase, Atrapar o Interceptar.',
        'pase' => 'Una vez por turno, permite repetir una tirada fallida de un Pase.',
        'pase en carrera' => 'Tras realizar un Pase Rápido con éxito, el jugador puede continuar moviéndose las casillas restantes.',
        'pase seguro' => 'Si obtiene un 1 natural al pasar, conserva el balón y el turno termina sin causar un balón suelto.',
        'precision' => 'Suma +1 a las tiradas de Pase al realizar un Pase Rápido o Pase Corto.',
        'precisión' => 'Suma +1 a las tiradas de Pase al realizar un Pase Rápido o Pase Corto.',
        'rompenubes' => 'Obliga al rival a repetir una tirada de Interferencia exitosa si el pase original era Largo o Bomba.',

        // --- MUTACIONES (MUTATION) ---
        'apariencia asquerosa' => 'Cualquier oponente que le declare un placaje debe tirar 1D6. Con un 1, pierde su acción.',
        'brazos adicionales' => 'Suma +1 a todas las tiradas de Atrapar, Recoger el balón o Interceptar.',
        'cola prensil' => 'Resta -1 a las tiradas de Esquivar o Saltar de los oponentes que salgan de su Zona de Defensa.',
        'cuernos' => 'Suma +1 a la Fuerza del jugador cuando realiza un placaje durante un Blitz.',
        'dos cabezas' => 'Suma +1 a las tiradas de Agilidad al intentar Esquivar o huir de una Zona de Defensa.',
        'garras' => 'Cualquier tirada de Armadura de 8 o más romperá la armadura del oponente, sin importar su AV base.',
        'mano grande' => 'Ignora los penalizadores por lluvia o Zonas de Defensa al realizar la tirada de Recoger el balón.',
        'piel de hierro' => 'Inmuniza a este jugador contra los efectos de la habilidad Garras del rival.',
        'piernas muy largas' => 'Reduce en 1 la penalización por saltar. Suma +2 a los intentos de Interferencia.',
        'presencia perturbadora' => 'Penalizador de -1 a tiradas de Pase, Atrapar o Interferencia de cualquier jugador a 3 casillas o menos.',
        'tentaculos' => 'Cuando un rival esquive, tira 1D6 + (Fuerza propia - Fuerza rival). Con 6+, el rival queda atrapado.',
        'tentáculos' => 'Cuando un rival esquive, tira 1D6 + (Fuerza propia - Fuerza rival). Con 6+, el rival queda atrapado.',
    ];

    // Buscamos la habilidad, y si no existe devolvemos un texto de fallback con el nombre original
    return $diccionario[$nombreLimpio] ?? "Habilidad BB2020: " . htmlspecialchars($nombre) . " (Descripción pendiente).";
}

// ************** RECALCULAR VALOR DE EQUIPO ACTUAL (CTV / TV OFICIAL BB2020) *********************************
function recalcularTV($equipoId) {
    include('conexion.php');
    try {
        // Obtener datos del equipo y el coste base del reroll de su raza
        $qEq = "SELECT e.*, r.coste_reroll 
                FROM equipos e 
                LEFT JOIN razas r ON e.raza = r.nombre 
                WHERE e.id = :id";
        $stEq = $baseDatos->prepare($qEq);
        $stEq->execute([':id' => $equipoId]);
        $eq = $stEq->fetch(PDO::FETCH_ASSOC);
        if (!$eq) return 0;

        // Sumar el valor actual de los jugadores vivos que NO tengan MNG (Miss Next Game)
        $qJug = "SELECT COALESCE(SUM(valor_actual), 0) 
                 FROM jugadores 
                 WHERE equipo_id = :id 
                   AND (muerto = 0 OR muerto IS NULL) 
                   AND (mng = 0 OR mng IS NULL)";
        $stJug = $baseDatos->prepare($qJug);
        $stJug->execute([':id' => $equipoId]);
        $tvJugadores = (int)$stJug->fetchColumn();

        // Sumar bienes de equipo (Rerolls, Apotecario 50k, Animadoras 10k, Ayudantes 10k)
        $costeReroll = !empty($eq['coste_reroll']) ? (int)$eq['coste_reroll'] : 50000;
        $tvBienes = ((int)$eq['rerolls'] * $costeReroll)
                  + ((int)$eq['apotecario'] ? 50000 : 0)
                  + ((int)$eq['animadoras'] * 10000)
                  + ((int)$eq['ayudantes_entrenador'] * 10000);

        $tvTotal = $tvJugadores + $tvBienes;

        $up = $baseDatos->prepare("UPDATE equipos SET valor_equipo = :tv WHERE id = :id");
        $up->execute([':tv' => $tvTotal, ':id' => $equipoId]);

        return $tvTotal;
    } catch (PDOException $e) {
        return 0;
    }
}
?>
