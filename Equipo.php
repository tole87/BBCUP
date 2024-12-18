<?php
    class Equipo {

        //atributos

        public $nombre;
        public $logo;
    



        public function __construct($nombre, $logo) {

            $this->nombre = $nombre;
            $this->logo = $logo;        

        }

        public function getNombre() {
            return $this->nombre;
        }   
        public function getLogo() {
            return $this->logo;
        }
        
        //metodo
        
    }

    function crearEquipos(){
            $equipos = [];
            array_push($equipos, new Equipo("Orcos","imagenes/logos/orco.webp"));
            array_push($equipos, new Equipo("Humanos","imagenes/icono_usuario.jpg"));
            array_push($equipos, new Equipo("Skaven","imagenes/icono_usuario.jpg"));
            array_push($equipos, new Equipo("Enanos","imagenes/icono_usuario.jpg"));

            return $equipos;
    }

?>