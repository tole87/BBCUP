// js/funciones_equipo.js

document.addEventListener("DOMContentLoaded", function () {
    // El presupuestoInicial se pasa globalmente desde el archivo PHP

    const reroll = document.getElementById("reroll");
    const asistente = document.getElementById("asistente");
    const animadoras = document.getElementById("animadoras");
    const fans = document.getElementById("fans");
    const medico = document.getElementById("medico");

    const precioReroll = parseInt(document.getElementById("valor_reroll").value) || 0;
    const precioAsistente = parseInt(document.getElementById("valor_asistente").value) || 0;
    const precioAnimadoras = parseInt(document.getElementById("valor_animadoras").value) || 0;
    const precioFans = parseInt(document.getElementById("valor_fans").value) || 0;
    const precioMedico = parseInt(document.getElementById("valor_medico").value) || 0;

    const totalReroll = document.getElementById("total_reroll");
    const totalAsistente = document.getElementById("total_asistente");
    const totalAnimadoras = document.getElementById("total_animadoras");
    const totalFans = document.getElementById("total_fans");
    const totalMedico = document.getElementById("total_medico");

    const valorEquipo = document.getElementById("valor_equipo");
    const tesoreria = document.getElementById("tesoreria");
    const selectoresPosicion = document.querySelectorAll(".selector-posicion");

    function actualizarCalculos() {
        const subReroll = (parseInt(reroll.value) || 0) * precioReroll;
        const subAsistente = (parseInt(asistente.value) || 0) * precioAsistente;
        const subAnimadoras = (parseInt(animadoras.value) || 0) * precioAnimadoras;
        const subMedico = (parseInt(medico.value) || 0) * precioMedico;

        // El primer fan es gratis. Solo se pagan los puntos extra (máximo 5)
        let fansValue = parseInt(fans.value) || 1;
        if (fansValue < 1) fansValue = 1;
        if (fansValue > 6) fansValue = 6;
        const subFans = (fansValue - 1) * precioFans;

        totalReroll.value = subReroll;
        totalAsistente.value = subAsistente;
        totalAnimadoras.value = subAnimadoras;
        totalFans.value = subFans;
        totalMedico.value = subMedico;

        let sumaJugadores = 0;
        document.querySelectorAll(".input-valor").forEach(function (input) {
            sumaJugadores += parseInt(input.value) || 0;
        });

        const gastoTotal = subReroll + subAsistente + subAnimadoras + subFans + subMedico + sumaJugadores;
        const tesoreriaRestante = presupuestoInicial - gastoTotal;

        valorEquipo.value = gastoTotal;
        tesoreria.value = tesoreriaRestante;

        if (tesoreriaRestante < 0) {
            tesoreria.style.color = "red";
            tesoreria.style.fontWeight = "bold";
        } else {
            tesoreria.style.color = "#333";
            tesoreria.style.fontWeight = "normal";
        }
    }

    function actualizarContadoresGlobales() {
        let conteos = {};
        selectoresPosicion.forEach(function (sel) {
            let pos = sel.value;
            if (pos !== "") {
                conteos[pos] = (conteos[pos] || 0) + 1;
            }
        });

        document.querySelectorAll(".fila-jugador").forEach(function (fila) {
            const sel = fila.querySelector(".selector-posicion");
            const inputConteo = fila.querySelector(".input-conteo");
            const posActual = sel.value;

            if (posActual === "") {
                inputConteo.value = "-";
            } else {
                const selectedOption = sel.options[sel.selectedIndex];
                const maxPermitido = selectedOption.getAttribute("data-max");
                const actual = conteos[posActual] || 0;
                inputConteo.value = actual + "-" + maxPermitido;
            }
        });
    }

    selectoresPosicion.forEach(function (select) {
        select.addEventListener("change", function () {
            const selectedPosition = this.value;
            
            if (selectedPosition !== "") {
                const selectedOption = this.options[this.selectedIndex];
                const maxPermitido = parseInt(selectedOption.getAttribute("data-max")) || 16;

                let cuentaActual = 0;
                selectoresPosicion.forEach(function (s) {
                    if (s.value === selectedPosition) {
                        cuentaActual++;
                    }
                });

                if (cuentaActual > maxPermitido) {
                    alert("¡Límite alcanzado! Solo puedes tener un máximo de " + maxPermitido + " unidades de '" + selectedPosition + "'.");
                    this.value = ""; 
                }
            }

            const fila = this.closest(".fila-jugador");
            const selectedOption = this.options[this.selectedIndex];

            const inputPosicion = fila.querySelector(".input-posicion");
            const inputMa = fila.querySelector(".input-ma");
            const inputSt = fila.querySelector(".input-st");
            const inputAg = fila.querySelector(".input-ag");
            const inputPa = fila.querySelector(".input-pa");
            const inputAv = fila.querySelector(".input-av");
            const inputHabilidades = fila.querySelector(".input-habilidades");
            const inputValor = fila.querySelector(".input-valor");

            if (this.value === "") {
                inputPosicion.value = "-";
                inputMa.value = "-";
                inputSt.value = "-";
                inputAg.value = "-";
                inputPa.value = "-";
                inputAv.value = "-";
                inputHabilidades.value = "-";
                inputValor.value = 0;
            } else {
                inputPosicion.value = selectedOption.value;
                inputMa.value = selectedOption.getAttribute("data-ma");
                inputSt.value = selectedOption.getAttribute("data-st");
                inputAg.value = selectedOption.getAttribute("data-ag");
                inputPa.value = selectedOption.getAttribute("data-pa");
                inputAv.value = selectedOption.getAttribute("data-av");
                inputHabilidades.value = selectedOption.getAttribute("data-habilidades");
                inputValor.value = selectedOption.getAttribute("data-coste");
            }

            actualizarContadoresGlobales();
            actualizarCalculos();
        });
    });

    [reroll, asistente, animadoras, fans, medico].forEach(function (el) {
        el.addEventListener("input", actualizarCalculos);
    });

    // Validación de Reglas al enviar el formulario
    document.getElementById("form-equipo").addEventListener("submit", function (e) {
        // 1. Validar el mínimo de 11 jugadores
        let jugadoresContratados = 0;
        document.querySelectorAll(".input-posicion").forEach(function (input) {
            if (input.value !== "-") {
                jugadoresContratados++;
            }
        });

        if (jugadoresContratados < 11) {
            e.preventDefault(); // Detiene el guardado
            alert("Regla oficial: Debes contratar un mínimo de 11 jugadores para crear el equipo (actualmente tienes " + jugadoresContratados + ").");
            return;
        }

        // 2. Validar que la tesorería no sea negativa
        const tesoreriaFinal = parseInt(tesoreria.value);
        if (tesoreriaFinal < 0) {
            e.preventDefault();
            alert("Presupuesto excedido: La tesorería no puede ser negativa.");
            return;
        }
    });

    // Inicializar cálculos por si hay valores por defecto
    actualizarCalculos();
});